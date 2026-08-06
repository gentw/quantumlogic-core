<?php

namespace App\Services;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\RecurringPlanState;
use App\Models\RecurringPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Recurring revenue: cycle advance, off-session charge, retry and dunning
 * handoff. Retries run 1 / 3 / 7 days after failures; after the fourth
 * failure the plan goes past_due and a human takes over — a customer's
 * hosting is never silently cancelled.
 */
class RecurringBillingService
{
    private const RETRY_DAYS = [1 => 1, 2 => 3, 3 => 7];

    public function __construct(
        private readonly BillingInvoiceService $invoices,
        private readonly PaymentService $payments,
        private readonly StripeGateway $stripe,
    ) {}

    /**
     * Charge every active plan that is due. Called by billing:charge-recurring
     * (withoutOverlapping); each plan is additionally row-locked.
     *
     * @return array{charged: int, requires_action: int, failed: int}
     */
    public function chargeDue(?Carbon $now = null): array
    {
        $now = $now ?? now();
        $stats = ['charged' => 0, 'requires_action' => 0, 'failed' => 0];

        RecurringPlan::query()
            ->where('state', RecurringPlanState::Active->value)
            ->where('next_charge_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(50, function ($plans) use (&$stats, $now) {
                foreach ($plans as $plan) {
                    try {
                        $outcome = $this->chargeOne($plan->id, $now);
                        if ($outcome) {
                            $stats[$outcome]++;
                        }
                    } catch (\Throwable $e) {
                        Log::error('Recurring charge crashed', ['plan_id' => $plan->id, 'error' => $e->getMessage()]);
                        $stats['failed']++;
                    }
                }
            });

        return $stats;
    }

    /** @return string|null one of charged|requires_action|failed, null if skipped */
    public function chargeOne(int $planId, Carbon $now): ?string
    {
        return DB::transaction(function () use ($planId, $now) {
            $plan = RecurringPlan::whereKey($planId)->lockForUpdate()->with(['service', 'paymentMethod'])->first();

            if (! $plan
                || $plan->state !== RecurringPlanState::Active
                || $plan->next_charge_at === null
                || $plan->next_charge_at->gt($now)) {
                return null; // advanced or transitioned by another path
            }

            $periodStart = $plan->current_period_end ?? $plan->next_charge_at;
            $periodEnd = $plan->interval === 'yearly'
                ? $periodStart->copy()->addYear()
                : $periodStart->copy()->addMonth();

            $invoice = $this->invoices->issue(
                $this->invoices->createDraftForRecurringPlan($plan, $periodStart, $periodEnd)
            );

            if (! $plan->paymentMethod) {
                $this->recordFailure($plan, $invoice->id, 'No payment method on file');

                return 'failed';
            }

            $payment = $this->payments->recordPending($invoice, PaymentProvider::Stripe, (float) $invoice->amount_due, [
                'user_id' => $plan->user_id,
            ]);

            $result = $this->stripe->chargeOffSession(
                $payment,
                $invoice,
                $plan->paymentMethod->provider_token,
                $plan->paymentMethod->provider_customer_id,
            );

            $payment->forceFill([
                'provider_payment_id' => $result['intent_id'],
                'idempotency_key' => $result['intent_id'] ? 'stripe:pi:'.$result['intent_id'] : 'recurring:'.$payment->id,
            ])->save();

            if ($result['status'] === 'succeeded') {
                $this->payments->apply($invoice, PaymentProvider::Stripe, $payment->idempotency_key, (float) $payment->amount, [
                    'provider_payment_id' => $result['intent_id'],
                ]);

                $plan->forceFill([
                    'current_period_start' => $periodStart,
                    'current_period_end' => $periodEnd,
                    'next_charge_at' => $periodEnd,
                    'failure_count' => 0,
                    'last_failure_reason' => null,
                ])->save();

                return 'charged';
            }

            if ($result['status'] === 'requires_action') {
                // SCA wants the customer: not a hard failure. Park the intent
                // secret for the phase-7 authentication mail and retry gently.
                $payment->forceFill([
                    'status' => PaymentStatus::Processing,
                    'metadata' => ['requires_action' => true, 'client_secret' => $result['client_secret']],
                ])->save();

                $plan->forceFill(['next_charge_at' => $now->copy()->addDays(3)])->save();
                $this->invoices->log($invoice, 'authentication_required', null, ['plan_id' => $plan->id]);

                return 'requires_action';
            }

            $this->payments->fail($payment, $result['error'] ?? 'Charge failed');
            $this->recordFailure($plan, $invoice->id, $result['error'] ?? 'Charge failed');

            app(BillingNotifier::class)->recurringChargeFailed(
                $invoice,
                $result['error'] ?? 'Charge failed',
                $plan->refresh()->state === RecurringPlanState::PastDue,
            );

            return 'failed';
        });
    }

    public function pause(RecurringPlan $plan): RecurringPlan
    {
        return $this->transition($plan, RecurringPlanState::Paused);
    }

    /** Resume charges; a lapsed next_charge_at restarts from now. */
    public function resume(RecurringPlan $plan): RecurringPlan
    {
        return DB::transaction(function () use ($plan) {
            $locked = RecurringPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();

            $locked->forceFill([
                'state' => RecurringPlanState::Active,
                'failure_count' => 0,
                'last_failure_reason' => null,
                'next_charge_at' => max($locked->next_charge_at ?? now(), now()),
            ])->save();

            return $locked->refresh();
        });
    }

    public function cancel(RecurringPlan $plan): RecurringPlan
    {
        return DB::transaction(function () use ($plan) {
            $locked = RecurringPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();

            $locked->forceFill([
                'state' => RecurringPlanState::Cancelled,
                'cancelled_at' => now(),
                'next_charge_at' => null,
            ])->save();

            return $locked->refresh();
        });
    }

    /** Price changes apply from the next cycle — never retroactively. */
    public function changePrice(RecurringPlan $plan, float $amountNet): RecurringPlan
    {
        $plan->update(['amount_net' => $amountNet]);

        return $plan->refresh();
    }

    private function transition(RecurringPlan $plan, RecurringPlanState $state): RecurringPlan
    {
        return DB::transaction(function () use ($plan, $state) {
            $locked = RecurringPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill(['state' => $state])->save();

            return $locked->refresh();
        });
    }

    private function recordFailure(RecurringPlan $plan, int $invoiceId, string $reason): void
    {
        $count = $plan->failure_count + 1;
        $retryDays = self::RETRY_DAYS[$count] ?? null;

        $plan->forceFill([
            'failure_count' => $count,
            'last_failure_reason' => $reason,
            'state' => $retryDays === null ? RecurringPlanState::PastDue : RecurringPlanState::Active,
            'next_charge_at' => $retryDays === null ? null : now()->addDays($retryDays),
        ])->save();

        Log::warning('Recurring charge failed', [
            'plan_id' => $plan->id,
            'invoice_id' => $invoiceId,
            'attempt' => $count,
            'handed_to_admin' => $retryDays === null,
        ]);
    }
}
