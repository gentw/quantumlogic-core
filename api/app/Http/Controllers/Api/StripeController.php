<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\StripePaymentIntentRequest;
use App\Models\Invoice;
use App\Services\PaymentService;
use App\Services\StripeGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeController extends Controller
{
    public function __construct(
        private readonly StripeGateway $stripe,
        private readonly PaymentService $payments,
    ) {}

    /**
     * Start a card payment: a pending payments row plus a PaymentIntent for
     * the Payment Element. The webhook settles it — never the browser.
     */
    public function createIntent(StripePaymentIntentRequest $request, Invoice $invoice): JsonResponse
    {
        abort_unless(in_array($invoice->status, [InvoiceStatus::Sent, InvoiceStatus::Unpaid], true), 422, 'This invoice cannot be paid.');

        $amount = min(
            (float) ($request->validated('amount') ?? $invoice->amount_due),
            (float) $invoice->amount_due
        );

        $payment = $this->payments->recordPending($invoice, PaymentProvider::Stripe, $amount, [
            'user_id' => $request->user()->id,
        ]);

        $intent = $this->stripe->createPaymentIntent($payment, $invoice);

        $payment->forceFill([
            'provider_payment_id' => $intent->id,
            'idempotency_key' => 'stripe:pi:'.$intent->id,
        ])->save();

        return response()->json([
            'payment_id' => $payment->id,
            'client_secret' => $intent->client_secret,
        ]);
    }

    /** SetupIntent for saving a card outside a payment. */
    public function createSetupIntent(Request $request): JsonResponse
    {
        $user = $request->user();

        $customerId = $user->paymentMethods()
            ->where('provider', PaymentProvider::Stripe->value)
            ->whereNotNull('provider_customer_id')
            ->value('provider_customer_id');

        $intent = $this->stripe->createSetupIntent($user, $customerId);

        return response()->json(['client_secret' => $intent->client_secret]);
    }
}
