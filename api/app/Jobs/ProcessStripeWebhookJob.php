<?php

namespace App\Jobs;

use App\Enums\PaymentProvider;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\BillingInvoiceService;
use App\Services\PaymentService;
use App\Services\StripeGateway;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Applies a verified Stripe event. The webhook controller verified the
 * signature and acknowledged already — Stripe times out and retries slow
 * handlers, so the work is queued.
 *
 * Replay-safe by construction: application goes through
 * PaymentService::apply, which no-ops on a terminal payment with the same
 * idempotency key.
 */
class ProcessStripeWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $type,
        public readonly array $object,
    ) {}

    public function handle(
        PaymentService $payments,
        BillingInvoiceService $invoices,
        StripeGateway $stripe,
    ): void {
        match ($this->type) {
            'payment_intent.succeeded' => $this->paymentSucceeded($payments),
            'payment_intent.payment_failed' => $this->paymentFailed($payments),
            'charge.refunded' => $this->chargeRefunded($payments),
            'charge.dispute.created' => $this->disputeCreated($invoices),
            'setup_intent.succeeded' => $this->setupSucceeded($stripe),
            default => Log::info('Stripe webhook ignored', ['type' => $this->type]),
        };
    }

    private function paymentSucceeded(PaymentService $payments): void
    {
        $payment = $this->findPayment($this->object['id'] ?? null, $this->object['metadata']['payment_id'] ?? null);
        if (! $payment) {
            return;
        }

        $charge = $this->object['charges']['data'][0] ?? [];
        $card = $charge['payment_method_details']['card'] ?? [];

        $payments->apply(
            $payment->invoice,
            PaymentProvider::Stripe,
            $payment->idempotency_key ?? 'stripe:pi:'.$this->object['id'],
            Money::toEuros((int) ($this->object['amount_received'] ?? 0)),
            [
                'provider_payment_id' => $this->object['id'],
                'method_brand' => $card['brand'] ?? null,
                'method_last4' => $card['last4'] ?? null,
            ],
        );
    }

    private function paymentFailed(PaymentService $payments): void
    {
        $payment = $this->findPayment($this->object['id'] ?? null, $this->object['metadata']['payment_id'] ?? null);
        if (! $payment) {
            return;
        }

        $payments->fail($payment, $this->object['last_payment_error']['message'] ?? 'Payment failed');
    }

    private function chargeRefunded(PaymentService $payments): void
    {
        $payment = $this->findPayment($this->object['payment_intent'] ?? null, null);
        if (! $payment) {
            return;
        }

        $delta = Money::toEuros(
            (int) ($this->object['amount_refunded'] ?? 0) - Money::toCents((float) $payment->refunded_amount)
        );

        if ($delta > 0) {
            $payments->refund($payment, $delta);
        }
    }

    private function disputeCreated(BillingInvoiceService $invoices): void
    {
        $payment = $this->findPayment($this->object['payment_intent'] ?? null, null);
        if (! $payment) {
            return;
        }

        $invoices->log($payment->invoice, 'dispute_created', null, [
            'payment_id' => $payment->id,
            'dispute_id' => $this->object['id'] ?? null,
            'reason' => $this->object['reason'] ?? null,
        ]);
    }

    private function setupSucceeded(StripeGateway $stripe): void
    {
        $userId = (int) ($this->object['metadata']['user_id'] ?? 0);
        $pmId = $this->object['payment_method'] ?? null;
        $user = $userId ? User::find($userId) : null;

        if (! $user || ! $pmId || PaymentMethod::where('provider_token', $pmId)->exists()) {
            return;
        }

        $card = $stripe->retrievePaymentMethod($pmId)->card;

        $user->paymentMethods()->create([
            'provider' => PaymentProvider::Stripe,
            'provider_token' => $pmId,
            'provider_customer_id' => $this->object['customer'] ?? null,
            'brand' => $card?->brand,
            'last4' => $card?->last4,
            'exp_month' => $card?->exp_month,
            'exp_year' => $card?->exp_year,
            'is_default' => ! $user->paymentMethods()->exists(),
            'verified_at' => now(),
        ]);
    }

    private function findPayment(?string $intentId, ?string $paymentId): ?Payment
    {
        $payment = null;

        if ($intentId) {
            $payment = Payment::where('provider_payment_id', $intentId)->first();
        }

        if (! $payment && $paymentId) {
            $payment = Payment::find((int) $paymentId);
        }

        if (! $payment) {
            Log::warning('Stripe webhook: no matching payment', ['type' => $this->type, 'intent' => $intentId]);
        }

        return $payment;
    }
}
