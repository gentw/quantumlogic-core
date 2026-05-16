<?php

namespace App\Services;

use App\Enums\SubscriptionState;
use App\Exceptions\SubscriptionStateException;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for subscription lifecycle transitions.
 *
 * Every write goes through `withUserLock()` which opens a transaction and
 * `lockForUpdate`s the user's existing subscription rows so that concurrent
 * subscribe/PayPal-callback/scheduler runs cannot race.
 */
class SubscriptionService
{
    private const ALLOWED_PAYMENT_METHODS = ['cc', 'paypal', 'bank_transfer'];

    /**
     * Run a callable inside a transaction with all of $user's subscription rows
     * locked. The closure receives the user's currently-relevant subscription
     * (active/trial/past_due) or null if none.
     */
    public function withUserLock(User $user, Closure $fn)
    {
        return DB::transaction(function () use ($user, $fn) {
            $current = Subscription::where('user_id', $user->id)
                ->whereIn('state', [
                    SubscriptionState::TrialActive->value,
                    SubscriptionState::Active->value,
                    SubscriptionState::PastDue->value,
                ])
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            return $fn($current);
        });
    }

    /**
     * Idempotent subscribe entry point. Converts a trial, extends an active sub,
     * or creates a new row — never produces duplicates for the same user.
     */
    public function subscribe(
        User $user,
        Package $package,
        string $billingCycle,
        string $paymentMethod,
        ?string $paymentToken,
        ?string $paymentBrand = null,
    ): Subscription {
        $this->guardPaymentMethod($paymentMethod);
        $this->guardBillingCycle($billingCycle);

        return $this->withUserLock($user, function (?Subscription $current) use (
            $user, $package, $billingCycle, $paymentMethod, $paymentToken, $paymentBrand
        ) {
            $amount = $this->priceFor($package, $billingCycle);

            if ($current && $current->state === SubscriptionState::TrialActive) {
                return $this->convertTrial(
                    $current, $package, $billingCycle,
                    $paymentMethod, $paymentToken, $paymentBrand, $amount
                );
            }

            if ($current && in_array($current->state, [SubscriptionState::Active, SubscriptionState::PastDue], true)) {
                return $this->extend(
                    $current, $package, $billingCycle,
                    $paymentMethod, $paymentToken, $paymentBrand, $amount
                );
            }

            return $this->createFresh(
                $user, $package, $billingCycle,
                $paymentMethod, $paymentToken, $paymentBrand, $amount
            );
        });
    }

    /**
     * Issue a new trial. Caller MUST have already validated abuse rules via TrialService.
     * Stores payment method as token only — no charge is created.
     */
    public function startTrial(
        User $user,
        Package $package,
        string $paymentMethod,
        string $paymentToken,
        ?string $paymentBrand,
        ?string $trialIp,
        ?string $trialDeviceHash,
        int $trialDays = 7,
    ): Subscription {
        $this->guardPaymentMethod($paymentMethod);

        return $this->withUserLock($user, function (?Subscription $current) use (
            $user, $package, $paymentMethod, $paymentToken, $paymentBrand,
            $trialIp, $trialDeviceHash, $trialDays
        ) {
            if ($current) {
                throw new SubscriptionStateException(
                    'User already has a subscription; trial cannot be granted.'
                );
            }

            $now = Carbon::now();
            $payment = SubscriptionPayment::create([
                'payment_method' => $paymentMethod,
                'payment_token' => $paymentToken,
                'amount' => 0.00,
                'status' => 'pending',
            ]);

            $subscription = $user->subscriptions()->create([
                'package_id' => $package->id,
                'state' => SubscriptionState::TrialActive,
                'status' => 'trial',
                'start_date' => $now,
                'end_date' => $now->copy()->addDays($trialDays),
                'billing_cycle' => 'monthly',
                'recurring_payment_method' => $paymentMethod,
                'payment_method_token' => $paymentToken,
                'payment_method_brand' => $paymentBrand,
                'cc_payment_id' => $paymentMethod === 'cc' ? $payment->id : null,
                'paypal_payment_id' => $paymentMethod === 'paypal' ? $payment->id : null,
                'bank_transfer_payment_id' => $paymentMethod === 'bank_transfer' ? $payment->id : null,
                'auto_renew' => true,
                'trial_used' => true,
                'trial_started_at' => $now,
                'trial_used_at' => $now,
                'trial_ip' => $trialIp,
                'trial_device_hash' => $trialDeviceHash,
            ]);

            $payment->update(['subscription_id' => $subscription->id]);

            return $subscription;
        });
    }

    public function markPastDue(Subscription $subscription, int $graceDays = 3): Subscription
    {
        return DB::transaction(function () use ($subscription, $graceDays) {
            $locked = Subscription::whereKey($subscription->id)->lockForUpdate()->first();
            if (! $locked) {
                return $subscription;
            }

            if ($locked->state === SubscriptionState::Cancelled || $locked->state === SubscriptionState::Expired) {
                return $locked;
            }

            $locked->update([
                'state' => SubscriptionState::PastDue,
                'status' => 'failed',
                'last_payment_status' => 'failed',
                'renewal_failure_count' => ($locked->renewal_failure_count ?? 0) + 1,
                'grace_period_ends_at' => Carbon::now()->addDays($graceDays),
            ]);

            return $locked->refresh();
        });
    }

    public function markExpired(Subscription $subscription): Subscription
    {
        return DB::transaction(function () use ($subscription) {
            $locked = Subscription::whereKey($subscription->id)->lockForUpdate()->first();
            if (! $locked) {
                return $subscription;
            }

            $locked->update([
                'state' => SubscriptionState::Expired,
                'status' => 'pending',
                'auto_renew' => false,
            ]);

            return $locked->refresh();
        });
    }

    public function cancel(Subscription $subscription): Subscription
    {
        return DB::transaction(function () use ($subscription) {
            $locked = Subscription::whereKey($subscription->id)->lockForUpdate()->first();
            if (! $locked) {
                return $subscription;
            }

            $locked->update([
                'state' => SubscriptionState::Cancelled,
                'status' => 'canceled',
                'auto_renew' => false,
                'cancelled_at' => Carbon::now(),
            ]);

            return $locked->refresh();
        });
    }

    public function recordRenewalSuccess(Subscription $subscription, ?int $paymentId = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $paymentId) {
            $locked = Subscription::whereKey($subscription->id)->lockForUpdate()->first();
            if (! $locked) {
                return $subscription;
            }

            $newEnd = $locked->billing_cycle === 'yearly'
                ? Carbon::now()->addYear()
                : Carbon::now()->addMonth();

            $locked->update([
                'state' => SubscriptionState::Active,
                'status' => 'active',
                'start_date' => Carbon::now(),
                'end_date' => $newEnd,
                'grace_period_ends_at' => null,
                'renewal_failure_count' => 0,
                'last_payment_status' => 'success',
            ]);

            if ($paymentId) {
                $field = match ($locked->recurring_payment_method) {
                    'cc' => 'cc_payment_id',
                    'paypal' => 'paypal_payment_id',
                    'bank_transfer' => 'bank_transfer_payment_id',
                    default => null,
                };
                if ($field) {
                    $locked->update([$field => $paymentId]);
                }
            }

            return $locked->refresh();
        });
    }

    public function priceFor(Package $package, string $billingCycle): float
    {
        return (float) ($billingCycle === 'yearly' ? $package->price_yearly : $package->price_monthly);
    }

    private function convertTrial(
        Subscription $trial,
        Package $package,
        string $billingCycle,
        string $paymentMethod,
        ?string $paymentToken,
        ?string $paymentBrand,
        float $amount,
    ): Subscription {
        $payment = SubscriptionPayment::create([
            'subscription_id' => $trial->id,
            'payment_method' => $paymentMethod,
            'payment_token' => $paymentToken,
            'amount' => $amount,
            'status' => 'success',
            'processed_at' => Carbon::now(),
        ]);

        $now = Carbon::now();
        $trial->update([
            'package_id' => $package->id,
            'state' => SubscriptionState::Active,
            'status' => 'active',
            'start_date' => $now,
            'end_date' => $billingCycle === 'yearly' ? $now->copy()->addYear() : $now->copy()->addMonth(),
            'billing_cycle' => $billingCycle,
            'recurring_payment_method' => $paymentMethod,
            'payment_method_token' => $paymentToken ?? $trial->payment_method_token,
            'payment_method_brand' => $paymentBrand ?? $trial->payment_method_brand,
            'cc_payment_id' => $paymentMethod === 'cc' ? $payment->id : null,
            'paypal_payment_id' => $paymentMethod === 'paypal' ? $payment->id : null,
            'bank_transfer_payment_id' => $paymentMethod === 'bank_transfer' ? $payment->id : null,
            'auto_renew' => true,
            'last_payment_status' => 'success',
            'renewal_failure_count' => 0,
            'grace_period_ends_at' => null,
        ]);

        return $trial->refresh();
    }

    private function extend(
        Subscription $current,
        Package $package,
        string $billingCycle,
        string $paymentMethod,
        ?string $paymentToken,
        ?string $paymentBrand,
        float $amount,
    ): Subscription {
        $payment = SubscriptionPayment::create([
            'subscription_id' => $current->id,
            'payment_method' => $paymentMethod,
            'payment_token' => $paymentToken,
            'amount' => $amount,
            'status' => 'success',
            'processed_at' => Carbon::now(),
        ]);

        $base = $current->end_date && $current->end_date->isFuture()
            ? $current->end_date->copy()
            : Carbon::now();

        $current->update([
            'package_id' => $package->id,
            'state' => SubscriptionState::Active,
            'status' => 'active',
            'start_date' => Carbon::now(),
            'end_date' => $billingCycle === 'yearly' ? $base->addYear() : $base->addMonth(),
            'billing_cycle' => $billingCycle,
            'recurring_payment_method' => $paymentMethod,
            'payment_method_token' => $paymentToken ?? $current->payment_method_token,
            'payment_method_brand' => $paymentBrand ?? $current->payment_method_brand,
            'cc_payment_id' => $paymentMethod === 'cc' ? $payment->id : $current->cc_payment_id,
            'paypal_payment_id' => $paymentMethod === 'paypal' ? $payment->id : $current->paypal_payment_id,
            'bank_transfer_payment_id' => $paymentMethod === 'bank_transfer' ? $payment->id : $current->bank_transfer_payment_id,
            'auto_renew' => true,
            'last_payment_status' => 'success',
            'renewal_failure_count' => 0,
            'grace_period_ends_at' => null,
        ]);

        return $current->refresh();
    }

    private function createFresh(
        User $user,
        Package $package,
        string $billingCycle,
        string $paymentMethod,
        ?string $paymentToken,
        ?string $paymentBrand,
        float $amount,
    ): Subscription {
        $now = Carbon::now();

        $subscription = $user->subscriptions()->create([
            'package_id' => $package->id,
            'state' => SubscriptionState::Active,
            'status' => 'active',
            'start_date' => $now,
            'end_date' => $billingCycle === 'yearly' ? $now->copy()->addYear() : $now->copy()->addMonth(),
            'billing_cycle' => $billingCycle,
            'recurring_payment_method' => $paymentMethod,
            'payment_method_token' => $paymentToken,
            'payment_method_brand' => $paymentBrand,
            'auto_renew' => true,
            'last_payment_status' => 'success',
        ]);

        $payment = SubscriptionPayment::create([
            'subscription_id' => $subscription->id,
            'payment_method' => $paymentMethod,
            'payment_token' => $paymentToken,
            'amount' => $amount,
            'status' => 'success',
            'processed_at' => Carbon::now(),
        ]);

        $subscription->update([
            'cc_payment_id' => $paymentMethod === 'cc' ? $payment->id : null,
            'paypal_payment_id' => $paymentMethod === 'paypal' ? $payment->id : null,
            'bank_transfer_payment_id' => $paymentMethod === 'bank_transfer' ? $payment->id : null,
        ]);

        return $subscription->refresh();
    }

    private function guardPaymentMethod(string $method): void
    {
        if (! in_array($method, self::ALLOWED_PAYMENT_METHODS, true)) {
            throw new SubscriptionStateException("Unknown payment method: {$method}");
        }
    }

    private function guardBillingCycle(string $cycle): void
    {
        if (! in_array($cycle, ['monthly', 'yearly'], true)) {
            throw new SubscriptionStateException("Unknown billing cycle: {$cycle}");
        }
    }
}
