<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidInvoiceStateException;
use App\Exceptions\PaymentAlreadyAppliedException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Provider-agnostic payment orchestration. Gateways (Stripe/PayPal/bank
 * transfer) normalise their events and call into here; this service owns the
 * invoice math and the idempotency story.
 *
 * apply() is safe under a simultaneous webhook and browser redirect: both
 * carry the same idempotency key, the invoice row lock serialises them, and
 * the second caller finds the first's terminal payment and no-ops.
 */
class PaymentService
{
    public function __construct(
        private readonly BillingInvoiceService $invoices,
    ) {}

    /**
     * Apply a settled payment to an invoice, exactly once per idempotency
     * key. Returns the payment — the existing one when replayed.
     */
    public function apply(
        Invoice $invoice,
        PaymentProvider $provider,
        string $idempotencyKey,
        float $amount,
        array $details = [],
    ): Payment {
        return DB::transaction(function () use ($invoice, $provider, $idempotencyKey, $amount, $details) {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing && $existing->status->isTerminal()) {
                return $existing; // replay — no-op
            }

            $this->guardPayable($locked);

            $payment = $existing
                ? $this->settle($existing, $amount, $details)
                : $this->createSettled($locked, $provider, $idempotencyKey, $amount, $details);

            $this->applyToInvoice($locked, $payment);

            return $payment->refresh();
        });
    }

    /**
     * Record a not-yet-settled payment (bank transfer announced, proof
     * uploaded, redirect started). Applies nothing to the invoice.
     */
    public function recordPending(
        Invoice $invoice,
        PaymentProvider $provider,
        float $amount,
        array $details = [],
        PaymentStatus $status = PaymentStatus::Pending,
    ): Payment {
        return Payment::create([
            'invoice_id' => $invoice->id,
            'user_id' => $details['user_id'] ?? $invoice->user_id,
            'provider' => $provider,
            'provider_payment_id' => $details['provider_payment_id'] ?? null,
            'provider_customer_id' => $details['provider_customer_id'] ?? null,
            'idempotency_key' => $details['idempotency_key'] ?? null,
            'amount' => $amount,
            'currency' => $invoice->currency ?? 'EUR',
            'status' => $status,
            'method_brand' => $details['method_brand'] ?? null,
            'method_last4' => $details['method_last4'] ?? null,
            'metadata' => $details['metadata'] ?? null,
        ]);
    }

    /**
     * Promote a pending/awaiting payment to settled and apply it — the admin
     * reconciliation path. Idempotent: an already-settled payment no-ops.
     */
    public function confirm(Payment $payment, ?User $admin = null): Payment
    {
        return DB::transaction(function () use ($payment, $admin) {
            $locked = Invoice::whereKey($payment->invoice_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status->isTerminal()) {
                return $payment;
            }

            $this->guardPayable($locked);

            $payment->forceFill([
                'status' => PaymentStatus::Succeeded,
                'paid_at' => $payment->paid_at ?? now(),
                'confirmed_by_admin_id' => $admin?->id,
                'confirmed_at' => now(),
            ])->save();

            $this->applyToInvoice($locked, $payment, $admin);

            return $payment->refresh();
        });
    }

    /** Terminal failure. Never touches the invoice totals. */
    public function fail(Payment $payment, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $reason) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->isTerminal()) {
                return $locked;
            }

            $locked->forceFill([
                'status' => PaymentStatus::Failed,
                'failure_reason' => $reason,
            ])->save();

            $this->invoices->log($locked->invoice, 'payment_failed', null, [
                'payment_id' => $locked->id,
                'reason' => $reason,
            ]);

            return $locked->refresh();
        });
    }

    /**
     * Bookkeep a refund against a settled payment and pull the amount back
     * off the invoice (which may flip it from paid back to sent). The actual
     * provider refund call is the gateway's job.
     */
    public function refund(Payment $payment, float $amount, ?User $actor = null): Payment
    {
        return DB::transaction(function () use ($payment, $amount, $actor) {
            $invoice = Invoice::whereKey($payment->invoice_id)->lockForUpdate()->firstOrFail();
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PaymentStatus::Succeeded && $locked->status !== PaymentStatus::PartiallyRefunded) {
                throw new InvalidInvoiceStateException(
                    "Payment {$locked->id} is not refundable in status \"{$locked->status->value}\"."
                );
            }

            $refundedCents = Money::toCents((float) $locked->refunded_amount) + Money::toCents($amount);
            $paymentCents = Money::toCents((float) $locked->amount);

            $locked->forceFill([
                'refunded_amount' => Money::toEuros(min($refundedCents, $paymentCents)),
                'status' => $refundedCents >= $paymentCents
                    ? PaymentStatus::Refunded
                    : PaymentStatus::PartiallyRefunded,
            ])->save();

            $invoice->forceFill([
                'amount_paid' => Money::toEuros(max(0, Money::toCents((float) $invoice->amount_paid) - Money::toCents($amount))),
            ])->save();
            $this->invoices->recomputeAmountDue($invoice);

            $this->invoices->log($invoice, 'payment_refunded', $actor, [
                'payment_id' => $locked->id,
                'amount' => $amount,
            ]);

            return $locked->refresh();
        });
    }

    private function guardPayable(Invoice $invoice): void
    {
        if ($invoice->status === InvoiceStatus::Paid) {
            throw PaymentAlreadyAppliedException::for($invoice);
        }

        if (! in_array($invoice->status, [InvoiceStatus::Sent, InvoiceStatus::Unpaid], true)) {
            throw InvalidInvoiceStateException::make($invoice, 'pay');
        }
    }

    private function settle(Payment $payment, float $amount, array $details): Payment
    {
        $payment->forceFill([
            'status' => PaymentStatus::Succeeded,
            'amount' => $amount,
            'paid_at' => now(),
            'provider_payment_id' => $details['provider_payment_id'] ?? $payment->provider_payment_id,
            'method_brand' => $details['method_brand'] ?? $payment->method_brand,
            'method_last4' => $details['method_last4'] ?? $payment->method_last4,
            'metadata' => $details['metadata'] ?? $payment->metadata,
        ])->save();

        return $payment;
    }

    private function createSettled(
        Invoice $invoice,
        PaymentProvider $provider,
        string $idempotencyKey,
        float $amount,
        array $details,
    ): Payment {
        try {
            return Payment::create([
                'invoice_id' => $invoice->id,
                'user_id' => $details['user_id'] ?? $invoice->user_id,
                'provider' => $provider,
                'provider_payment_id' => $details['provider_payment_id'] ?? null,
                'provider_customer_id' => $details['provider_customer_id'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'amount' => $amount,
                'currency' => $invoice->currency ?? 'EUR',
                'status' => PaymentStatus::Succeeded,
                'method_brand' => $details['method_brand'] ?? null,
                'method_last4' => $details['method_last4'] ?? null,
                'paid_at' => now(),
                'metadata' => $details['metadata'] ?? null,
            ]);
        } catch (QueryException $e) {
            // Unique idempotency_key lost a race outside our lock scope —
            // treat exactly like a replay.
            if (($e->errorInfo[1] ?? null) === 1062) {
                return Payment::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();
            }

            throw $e;
        }
    }

    /** Caller holds the invoice row lock. */
    private function applyToInvoice(Invoice $locked, Payment $payment, ?User $actor = null): void
    {
        // Only payment-tracking columns — the invoice is locked, and the
        // provider lives on the payment row, not the legacy invoice column.
        $locked->forceFill([
            'amount_paid' => Money::toEuros(
                Money::toCents((float) $locked->amount_paid) + Money::toCents((float) $payment->amount)
            ),
        ])->save();

        $this->invoices->recomputeAmountDue($locked);

        $this->invoices->log($locked, 'payment_received', $actor, [
            'payment_id' => $payment->id,
            'provider' => $payment->provider->value,
            'amount' => (float) $payment->amount,
        ]);
    }
}
