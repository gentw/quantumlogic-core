<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentProvider;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\BankTransferService;
use App\Services\PaymentService;
use App\Services\StripeGateway;
use Illuminate\Http\JsonResponse;

/**
 * Public pay links for clients who never log in. The 64-char expiring token
 * is the only credential; the payload exposes exactly what the pay page
 * needs — no other invoices, no account data.
 */
class PublicInvoiceController extends Controller
{
    public function __construct(
        private readonly StripeGateway $stripe,
        private readonly PaymentService $payments,
        private readonly BankTransferService $bankTransfer,
    ) {}

    public function show(string $token): JsonResponse
    {
        $invoice = $this->resolve($token);

        $payable = $invoice->status->isPayable() && (float) $invoice->amount_due > 0;

        return response()->json([
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status->value,
            'payable' => $payable,
            'currency' => $invoice->currency ?? 'EUR',
            'due_at' => $invoice->due_at?->toDateString(),
            'total_gross' => (float) $invoice->total_gross,
            'amount_due' => (float) $invoice->amount_due,
            'reverse_charge' => (bool) $invoice->reverse_charge,
            'lines' => $invoice->items->map(fn ($item) => [
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'line_total_gross' => (float) $item->line_total_gross,
            ]),
            'seller' => [
                'name' => (string) config('company.legal_name'),
                'address' => (string) config('company.address'),
                'uid' => (string) config('company.uid'),
                'email' => (string) config('company.email'),
            ],
            'bank' => $payable ? $this->bankTransfer->bankDetails($invoice) : null,
        ]);
    }

    /** Start a card payment against the linked invoice. */
    public function pay(string $token): JsonResponse
    {
        $invoice = $this->resolve($token);

        abort_unless($invoice->status->isPayable(), 422, 'This invoice cannot be paid.');
        abort_if((float) $invoice->amount_due <= 0, 422, 'This invoice is settled.');

        $payment = $this->payments->recordPending($invoice, PaymentProvider::Stripe, (float) $invoice->amount_due, [
            'user_id' => $invoice->user_id,
        ]);

        $intent = $this->stripe->createPaymentIntent($payment, $invoice);

        $payment->forceFill([
            'provider_payment_id' => $intent->id,
            'idempotency_key' => 'stripe:pi:'.$intent->id,
        ])->save();

        return response()->json(['client_secret' => $intent->client_secret]);
    }

    /** Unknown and expired tokens are indistinguishable: both 404. */
    private function resolve(string $token): Invoice
    {
        $invoice = Invoice::query()
            ->where('public_token', $token)
            ->where('public_token_expires_at', '>', now())
            ->with('items')
            ->first();

        abort_unless($invoice !== null, 404);

        return $invoice;
    }
}
