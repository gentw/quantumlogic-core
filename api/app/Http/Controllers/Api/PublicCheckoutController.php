<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceType;
use App\Enums\PaymentProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicCheckoutRequest;
use App\Models\Service;
use App\Services\BillingInvoiceService;
use App\Services\ClientAccountService;
use App\Services\PaymentService;
use App\Services\ServiceOrderService;
use App\Services\StripeGateway;
use App\Services\TaxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Guest checkout: a prospect buys with no account. Card details plus a real
 * 50% deposit are the identity check; the account is a by-product, created
 * through ClientAccountService — deliberately not a fifth signup endpoint.
 */
class PublicCheckoutController extends Controller
{
    public function __construct(
        private readonly ClientAccountService $accounts,
        private readonly ServiceOrderService $orders,
        private readonly BillingInvoiceService $invoices,
        private readonly PaymentService $payments,
        private readonly StripeGateway $stripe,
        private readonly TaxService $tax,
    ) {}

    /** The publicly orderable catalogue. */
    public function services(): JsonResponse
    {
        return response()->json([
            'data' => Service::publiclyOrderable()->orderBy('sort_order')->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'slug' => $s->slug,
                    'description' => $s->description,
                    'billing_type' => $s->billing_type->value,
                    'price_net' => (float) $s->default_price_net,
                    'billing_interval' => $s->default_billing_interval,
                    'vat_rate' => (float) $s->vat_rate,
                    'supports_deposit' => $s->supports_deposit,
                    'deposit_percent' => $s->default_deposit_percent !== null ? (float) $s->default_deposit_percent : null,
                ]),
        ]);
    }

    /** Price a selection without persisting anything. */
    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'services' => ['required', 'array', 'min:1'],
            'services.*.id' => ['required', 'integer'],
            'services.*.quantity' => ['nullable', 'numeric', 'min:0.01', 'max:999'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'vat_id' => ['nullable', 'string', 'max:20'],
        ]);

        $net = $vat = 0;
        $lines = [];

        foreach ($validated['services'] as $selection) {
            $service = Service::publiclyOrderable()->find($selection['id']);
            if (! $service) {
                continue;
            }

            $quantity = (float) ($selection['quantity'] ?? 1);
            $resolution = $this->tax->resolve(
                $validated['country_code'] ?? null,
                $validated['vat_id'] ?? null,
                (float) $service->vat_rate,
            );

            $lineNet = round((float) $service->default_price_net * $quantity, 2);
            $lineVat = round($lineNet * $resolution->rate / 100, 2);

            $net += $lineNet;
            $vat += $lineVat;
            $lines[] = [
                'name' => $service->name,
                'quantity' => $quantity,
                'net' => $lineNet,
                'vat_rate' => $resolution->rate,
                'gross' => round($lineNet + $lineVat, 2),
            ];
        }

        $depositable = collect($validated['services'])
            ->contains(fn ($s) => Service::publiclyOrderable()->find($s['id'])?->supports_deposit);

        return response()->json([
            'lines' => $lines,
            'subtotal_net' => round($net, 2),
            'vat_total' => round($vat, 2),
            'total_gross' => round($net + $vat, 2),
            'deposit_percent' => $depositable ? 50 : 100,
        ]);
    }

    /**
     * Start the purchase. New email: provisional account + order + deposit
     * invoice + Stripe client secret, inline. Existing email: the order
     * attaches to that account but payment requires login — an anonymous
     * request must never pay onto an established account. Both answers share
     * one JSON shape.
     */
    public function start(PublicCheckoutRequest $request): JsonResponse
    {
        $validated = $request->validated();

        ['user' => $user, 'created' => $created] = $this->accounts->findOrCreateForBilling(
            $validated['email'],
            $validated['name'],
            $validated['company'] ?? null,
            $validated['vat_id'] ?? null,
            $validated['country_code'] ?? null,
        );

        return DB::transaction(function () use ($validated, $user, $created) {
            $lines = collect($validated['services'])
                ->filter(fn ($s) => Service::publiclyOrderable()->whereKey($s['id'])->exists())
                ->map(fn ($s) => ['service_id' => $s['id'], 'quantity' => (float) ($s['quantity'] ?? 1)])
                ->values()->all();

            abort_if($lines === [], 422, 'Nothing orderable selected.');

            $order = $this->orders->create($user, $lines, ['deposit_percent' => 50]);
            $this->orders->submit($order);

            $invoice = $this->invoices->issue(
                $this->invoices->createDraftFromOrder($order, InvoiceType::Deposit, 0.5)
            );

            $invoice->forceFill([
                'public_token' => Str::random(64),
                'public_token_expires_at' => now()->addDays(30),
            ])->save();

            $clientSecret = null;

            if ($created) {
                $payment = $this->payments->recordPending($invoice, PaymentProvider::Stripe, (float) $invoice->amount_due, [
                    'user_id' => $user->id,
                ]);
                $intent = $this->stripe->createPaymentIntent($payment, $invoice);
                $payment->forceFill([
                    'provider_payment_id' => $intent->id,
                    'idempotency_key' => 'stripe:pi:'.$intent->id,
                ])->save();
                $clientSecret = $intent->client_secret;
            }

            // One shape for both outcomes; requires_login carries the
            // difference and the route throttle blunts email enumeration.
            return response()->json([
                'order_number' => $order->order_number,
                'deposit_amount' => (float) $invoice->amount_due,
                'requires_login' => ! $created,
                'client_secret' => $clientSecret,
                'pay_token' => $created ? $invoice->public_token : null,
            ], 201);
        });
    }
}
