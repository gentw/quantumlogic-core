<?php

namespace Tests\Feature\Billing;

use App\Enums\RecurringPlanState;
use App\Models\RecurringPlan;
use App\Models\Service;
use App\Services\RecurringBillingService;
use App\Services\StripeGateway;

class RecurringBillingTest extends BillingTestCase
{
    private function makePlan(string $gatewayMode): RecurringPlan
    {
        $this->app->bind(StripeGateway::class, fn () => new class($gatewayMode) extends StripeGateway
        {
            public function __construct(private string $mode) {}

            public function chargeOffSession($payment, $invoice, $token, $customer): array
            {
                return match ($this->mode) {
                    'succeeded' => ['status' => 'succeeded', 'intent_id' => 'pi_ok_'.$payment->id, 'client_secret' => null, 'error' => null],
                    'requires_action' => ['status' => 'requires_action', 'intent_id' => 'pi_sca_'.$payment->id, 'client_secret' => 'cs_test', 'error' => null],
                    default => ['status' => 'failed', 'intent_id' => null, 'client_secret' => null, 'error' => 'card_declined'],
                };
            }
        });

        $client = $this->makeClient();
        $order = $this->makeOrder($client, 39.00);
        $method = $client->paymentMethods()->create([
            'provider' => 'stripe', 'provider_token' => 'pm_t', 'provider_customer_id' => 'cus_t', 'is_default' => true,
        ]);

        return $order->recurringPlans()->create([
            'user_id' => $client->id,
            'service_id' => Service::query()->first()->id,
            'payment_method_id' => $method->id,
            'interval' => 'monthly',
            'amount_net' => 39.00,
            'vat_rate' => 20,
            'currency' => 'EUR',
            'state' => RecurringPlanState::Active,
            'next_charge_at' => now()->subHour(),
        ]);
    }

    public function test_a_successful_charge_settles_the_cycle_and_advances_the_plan(): void
    {
        $plan = $this->makePlan('succeeded');

        $stats = app(RecurringBillingService::class)->chargeDue();

        $this->assertSame(1, $stats['charged']);
        $plan->refresh();
        $this->assertSame(0, $plan->failure_count);
        $this->assertTrue($plan->next_charge_at->isFuture());

        $this->assertSame(0, app(RecurringBillingService::class)->chargeDue()['charged'], 'a second sweep must be a no-op');
    }

    public function test_repeated_failures_walk_the_retry_ladder_into_past_due_never_cancellation(): void
    {
        $plan = $this->makePlan('failed');
        $recurring = app(RecurringBillingService::class);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $recurring->chargeDue();
            $plan->refresh();
            if ($plan->next_charge_at) {
                $plan->forceFill(['next_charge_at' => now()->subHour()])->save();
            }
        }

        $this->assertSame(RecurringPlanState::PastDue, $plan->state);
        $this->assertSame(4, $plan->failure_count);
        $this->assertSame('card_declined', $plan->last_failure_reason);
        $this->assertNull($plan->next_charge_at, 'past_due plans stop charging until a human intervenes');
        $this->assertNotSame(RecurringPlanState::Cancelled, $plan->state);
    }

    public function test_sca_requires_action_is_not_a_failure(): void
    {
        $plan = $this->makePlan('requires_action');

        $stats = app(RecurringBillingService::class)->chargeDue();

        $this->assertSame(1, $stats['requires_action']);
        $plan->refresh();
        $this->assertSame(0, $plan->failure_count);
        $this->assertSame(RecurringPlanState::Active, $plan->state);
        $this->assertTrue($plan->next_charge_at->isFuture());
    }
}
