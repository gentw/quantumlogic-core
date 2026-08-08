<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceType;
use App\Enums\PaymentProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicCheckoutRequest;
use App\Models\Service;
use App\Services\BankTransferService;
use App\Services\BillingInvoiceService;
use App\Services\BillingNotifier;
use App\Services\ClientAccountService;
use App\Services\PaymentService;
use App\Services\ServiceCatalogueService;
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
        private readonly BillingNotifier $notifier,
        private readonly ServiceCatalogueService $catalogue,
        private readonly BankTransferService $bank,
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
            'coupon' => ['nullable', 'string', 'max:64'],
        ]);

        $net = $vat = $discount = 0;
        $lines = [];
        $coupon = null;

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

            // A code scoped to another service simply does not apply to this line.
            $lineCoupon = $this->catalogue->couponFor($validated['coupon'] ?? null, $service);
            $coupon ??= $lineCoupon;
            $discountPercent = $lineCoupon ? (float) $lineCoupon->discount_percent : 0.0;

            $listNet = round((float) $service->default_price_net * $quantity, 2);
            $lineNet = round($listNet * (1 - $discountPercent / 100), 2);
            $lineVat = round($lineNet * $resolution->rate / 100, 2);

            $net += $listNet;
            $discount += round($listNet - $lineNet, 2);
            $vat += $lineVat;
            $lines[] = [
                'name' => $service->name,
                'quantity' => $quantity,
                'net' => $lineNet,
                'discount_percent' => $discountPercent,
                'vat_rate' => $resolution->rate,
                'gross' => round($lineNet + $lineVat, 2),
            ];
        }

        $depositable = collect($validated['services'])
            ->contains(fn ($s) => Service::publiclyOrderable()->find($s['id'])?->supports_deposit);

        return response()->json([
            'lines' => $lines,
            // subtotal is the list price before any coupon, so the buyer sees
            // what was taken off rather than just a smaller number.
            'subtotal_net' => round($net, 2),
            'discount_total' => round($discount, 2),
            'vat_total' => round($vat, 2),
            'total_gross' => round($net - $discount + $vat, 2),
            'deposit_percent' => $depositable ? 50 : 100,
            'coupon' => $coupon ? [
                'code' => $coupon->code,
                'label' => $coupon->label,
                'discount_percent' => (float) $coupon->discount_percent,
            ] : null,
        ]);
    }

    /**
     * Start the purchase: account (found or created) + order + deposit invoice
     * + Stripe client secret, inline, either way.
     *
     * A new email creates the account with the password the buyer chose. An
     * existing email attaches the order to that account and pays for it, but
     * the supplied password is ignored and no account data is returned — so
     * the response still cannot be used to reach an established account.
     *
     * Note this makes the two outcomes distinguishable (existing_account), i.e.
     * the endpoint confirms whether an email is registered. Accepted trade-off
     * for letting returning customers buy without signing in first; the route
     * throttle is what limits enumeration.
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
            $validated['password'],
        );

        // Credentials go out now because this is the only point the plaintext
        // exists — activation happens later, in the webhook, where it doesn't.
        if ($created) {
            $this->notifier->guestWelcome($user, plainPassword: $validated['password']);
        }

        return DB::transaction(function () use ($validated, $user, $created) {
            $redeemed = null;

            $lines = collect($validated['services'])
                ->map(fn ($s) => [$s, Service::publiclyOrderable()->find($s['id'])])
                ->filter(fn ($pair) => $pair[1] !== null)
                ->map(function ($pair) use ($validated, &$redeemed) {
                    [$selection, $service] = $pair;
                    $coupon = $this->catalogue->couponFor($validated['coupon'] ?? null, $service);
                    $redeemed ??= $coupon;

                    return [
                        'service_id' => $service->id,
                        'quantity' => (float) ($selection['quantity'] ?? 1),
                        // The coupon becomes an ordinary line discount, so it is
                        // visible on the invoice and the existing cents-based
                        // totals handle it — no parallel discount concept.
                        'discount_percent' => $coupon ? (float) $coupon->discount_percent : 0.0,
                    ];
                })
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

            // Counted here rather than at quote time, so pricing a basket never
            // burns a single-use code.
            if ($redeemed) {
                $this->catalogue->redeemCoupon($redeemed);
            }

            // Bank transfer: the invoice is issued and the buyer gets the
            // beneficiary details and the EPC QR. Nothing is paid until the
            // transfer lands and an admin reconciles it, so the order stays in
            // awaiting_payment — no card is involved at any point.
            if (($validated['payment_method'] ?? 'card') === 'transfer') {
                return response()->json([
                    'order_number' => $order->order_number,
                    'deposit_amount' => (float) $invoice->amount_due,
                    'existing_account' => ! $created,
                    'payment_method' => 'transfer',
                    'invoice_number' => $invoice->invoice_number,
                    'bank_details' => $this->bank->bankDetails($invoice),
                    'client_secret' => null,
                    // Unlike the card rail, transfer needs a way back to this
                    // invoice — the receipt is uploaded later — so the token
                    // goes out for existing accounts too. It reaches only the
                    // invoice this request just created, which holds nothing
                    // the requester did not supply, and the order was already
                    // attached to the account with or without it.
                    'pay_token' => $invoice->public_token,
                ], 201);
            }

            // Both outcomes can pay inline: refusing an existing customer their
            // own purchase helped nobody. Paying does not expose the account —
            // no account data is returned, and the password is untouched, so
            // the worst an email-guesser achieves is paying someone's deposit.
            $payment = $this->payments->recordPending($invoice, PaymentProvider::Stripe, (float) $invoice->amount_due, [
                'user_id' => $user->id,
            ]);
            $intent = $this->stripe->createPaymentIntent($payment, $invoice);
            $payment->forceFill([
                'provider_payment_id' => $intent->id,
                'idempotency_key' => 'stripe:pi:'.$intent->id,
            ])->save();

            return response()->json([
                'payment_method' => 'card',
                'order_number' => $order->order_number,
                'deposit_amount' => (float) $invoice->amount_due,
                // The order joined an account that already existed, so the SPA
                // tells them to sign in with their existing password rather
                // than the one they just typed (which was ignored).
                'existing_account' => ! $created,
                'client_secret' => $intent->client_secret,
                // A standing pay-link credential only goes to brand-new
                // accounts; an established client signs in instead.
                'pay_token' => $created ? $invoice->public_token : null,
            ], 201);
        });
    }
}
