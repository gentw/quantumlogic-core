<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Database\QueryException;

class InvoiceService
{
    /**
     * Idempotent invoice creation for a subscription billing period.
     *
     * Relies on the unique index (subscription_id, billing_period_start) so that
     * two concurrent calls cannot produce two invoices for the same cycle.
     */
    public function recordForPeriod(
        Subscription $subscription,
        Package $package,
        float $amount,
        string $description,
        ?int $paymentId = null,
        ?string $paymentMethod = null,
        string $status = 'paid',
        bool $isTrial = false,
    ): Invoice {
        $periodStart = $subscription->start_date?->copy() ?? $subscription->freshTimestamp();
        $periodEnd = $subscription->end_date?->copy();

        $existing = Invoice::where('subscription_id', $subscription->id)
            ->where('billing_period_start', $periodStart)
            ->first();

        if ($existing) {
            $existing->fill([
                'subscribe_payment_id' => $paymentId ?? $existing->subscribe_payment_id,
                'status' => $status,
                'paid_at' => $status === 'paid' ? ($existing->paid_at ?? now()) : $existing->paid_at,
            ])->save();

            return $existing;
        }

        try {
            return Invoice::create([
                'user_id' => $subscription->user_id,
                'subscription_id' => $subscription->id,
                'package_id' => $package->id,
                'subscribe_payment_id' => $paymentId,
                'amount' => $amount,
                'total' => $amount,
                'currency' => 'EUR',
                'status' => $status,
                'description' => $description,
                'is_trial' => $isTrial,
                'payment_method' => $paymentMethod,
                'billing_period_start' => $periodStart,
                'billing_period_end' => $periodEnd,
                'issued_at' => now(),
                'paid_at' => $status === 'paid' ? now() : null,
            ]);
        } catch (QueryException $e) {
            // Race: another request created the invoice for the same period between
            // our SELECT and INSERT. Fetch the winning row and return it.
            if ($this->isUniqueViolation($e)) {
                return Invoice::where('subscription_id', $subscription->id)
                    ->where('billing_period_start', $periodStart)
                    ->firstOrFail();
            }

            throw $e;
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062
            || str_contains((string) $e->getMessage(), 'invoices_subscription_period_unique');
    }
}
