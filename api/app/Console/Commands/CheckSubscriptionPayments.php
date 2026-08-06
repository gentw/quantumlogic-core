<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionState;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckSubscriptionPayments extends Command
{
    protected $signature = 'subscriptions:check-payments';

    protected $description = 'Run auto-renewals, transition past_due, expire subscriptions whose grace period elapsed.';

    public function __construct(private readonly SubscriptionService $subscriptions)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // Retired module — stays registered and scheduled, does nothing while the
        // flag is off. See docs/modules/subscriptions/README.md.
        if (! config('features.subscription_plans')) {
            $this->info('[subs] subscription_plans module is off — skipping sweep.');

            return self::SUCCESS;
        }

        $now = Carbon::now();

        $this->info('[subs] starting renewal sweep at '.$now->toDateTimeString());

        $this->expireGracePeriods($now);
        $this->processDueRenewals($now);

        $this->info('[subs] sweep complete.');

        return self::SUCCESS;
    }

    private function processDueRenewals(Carbon $now): void
    {
        Subscription::query()
            ->whereIn('state', [SubscriptionState::Active->value, SubscriptionState::PastDue->value])
            ->where('auto_renew', true)
            ->where('end_date', '<=', $now)
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) {
                foreach ($subscriptions as $subscription) {
                    $this->renewOne($subscription->id);
                }
            });
    }

    private function renewOne(int $subscriptionId): void
    {
        try {
            DB::transaction(function () use ($subscriptionId) {
                $sub = Subscription::whereKey($subscriptionId)->lockForUpdate()->first();
                if (! $sub) {
                    return;
                }

                if (! $sub->auto_renew || $sub->state === SubscriptionState::Cancelled) {
                    return;
                }

                if ($sub->end_date && $sub->end_date->isFuture() && $sub->state === SubscriptionState::Active) {
                    // Already extended by another path (e.g. manual subscribe between scheduler runs)
                    return;
                }

                $this->info("[subs] processing #{$sub->id}");
                $result = $this->charge($sub);

                if ($result['success']) {
                    $payment = SubscriptionPayment::create([
                        'subscription_id' => $sub->id,
                        'payment_method' => $sub->recurring_payment_method,
                        'payment_token' => $sub->payment_method_token ?? '',
                        'amount' => $this->amountFor($sub),
                        'status' => 'success',
                        'processed_at' => Carbon::now(),
                    ]);

                    $this->subscriptions->recordRenewalSuccess($sub, $payment->id);
                    $this->info("[subs] renewed #{$sub->id}");

                    return;
                }

                $this->warn("[subs] charge failed for #{$sub->id}: {$result['message']}");
                $this->subscriptions->markPastDue($sub);
            }, attempts: 3);
        } catch (\Throwable $e) {
            Log::error('subscription renewal failed', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
            $this->error("[subs] error #{$subscriptionId}: {$e->getMessage()}");
        }
    }

    private function expireGracePeriods(Carbon $now): void
    {
        Subscription::query()
            ->where('state', SubscriptionState::PastDue->value)
            ->whereNotNull('grace_period_ends_at')
            ->where('grace_period_ends_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) {
                foreach ($subscriptions as $sub) {
                    try {
                        $this->subscriptions->markExpired($sub);
                        $this->info("[subs] expired #{$sub->id}");
                    } catch (\Throwable $e) {
                        Log::error('subscription expire failed', [
                            'subscription_id' => $sub->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
    }

    private function amountFor(Subscription $sub): float
    {
        $package = $sub->package;
        if (! $package) {
            return 0.00;
        }

        return (float) ($sub->billing_cycle === 'yearly' ? $package->price_yearly : $package->price_monthly);
    }

    /**
     * Stub gateway charge — wire in Raiffeisen / PayPal recurring billing here.
     *
     * @return array{success: bool, message: string}
     */
    protected function charge(Subscription $subscription): array
    {
        if (! $subscription->payment_method_token && ! $subscription->paypal_payment_id && ! $subscription->cc_payment_id) {
            return ['success' => false, 'message' => 'No stored payment method'];
        }

        return match ($subscription->recurring_payment_method) {
            'cc' => ['success' => true, 'message' => 'Simulated CC charge successful'],
            'paypal' => ['success' => true, 'message' => 'Simulated PayPal charge successful'],
            'bank_transfer' => ['success' => true, 'message' => 'Manual bank transfer confirmed'],
            default => ['success' => false, 'message' => 'Invalid payment method'],
        };
    }
}
