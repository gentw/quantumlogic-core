<?php

namespace App\Jobs;

use App\Enums\PaymentProvider;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Applies a verified PayPal event. resource.custom_id carries the payments
 * row id; application goes through PaymentService::apply, so a webhook
 * arriving after the browser redirect (or twice) is a no-op.
 */
class ProcessPayPalWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $eventType,
        public readonly array $resource,
    ) {}

    public function handle(PaymentService $payments): void
    {
        match ($this->eventType) {
            'PAYMENT.CAPTURE.COMPLETED' => $this->captureCompleted($payments),
            'PAYMENT.CAPTURE.DENIED' => $this->captureDenied($payments),
            'PAYMENT.CAPTURE.REFUNDED' => $this->captureRefunded($payments),
            default => Log::info('PayPal webhook ignored', ['type' => $this->eventType]),
        };
    }

    private function captureCompleted(PaymentService $payments): void
    {
        $payment = $this->findPayment();
        if (! $payment) {
            return;
        }

        $payments->apply(
            $payment->invoice,
            PaymentProvider::PayPal,
            $payment->idempotency_key ?? 'paypal:pmt:'.$payment->id,
            (float) ($this->resource['amount']['value'] ?? $payment->amount),
            [
                'provider_payment_id' => $this->resource['id'] ?? $payment->provider_payment_id,
                'method_brand' => 'paypal',
            ],
        );
    }

    private function captureDenied(PaymentService $payments): void
    {
        $payment = $this->findPayment();
        if (! $payment) {
            return;
        }

        $payments->fail($payment, $this->resource['status_details']['reason'] ?? 'Capture denied');
    }

    private function captureRefunded(PaymentService $payments): void
    {
        $payment = $this->findPayment();
        if (! $payment) {
            return;
        }

        // Refund events carry the refund amount; apply it as a delta only if
        // our bookkeeping hasn't seen it yet (admin-initiated refunds are
        // recorded before PayPal echoes them back).
        $refund = Money::toCents((float) ($this->resource['amount']['value'] ?? 0));
        $known = Money::toCents((float) $payment->refunded_amount);
        $paymentTotal = Money::toCents((float) $payment->amount);

        $delta = min($refund, $paymentTotal - $known);

        if ($delta > 0) {
            $payments->refund($payment, Money::toEuros($delta));
        }
    }

    private function findPayment(): ?Payment
    {
        $customId = (int) ($this->resource['custom_id'] ?? 0);
        $payment = $customId ? Payment::find($customId) : null;

        if (! $payment && isset($this->resource['id'])) {
            $payment = Payment::where('provider_payment_id', $this->resource['id'])->first();
        }

        if (! $payment) {
            Log::warning('PayPal webhook: no matching payment', ['type' => $this->eventType, 'resource_id' => $this->resource['id'] ?? null]);
        }

        return $payment;
    }
}
