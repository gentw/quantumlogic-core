<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use Carbon\Carbon;

class CheckSubscriptionPayments extends Command
{
    protected $signature = 'subscriptions:check-payments';
    protected $description = 'Check recurring payments and update subscription status';

    public function handle()
    {
        $this->info('Checking subscriptions for due payments...');

        $subscriptions = Subscription::where('status', 'active')
        ->where('auto_renew', true)
        ->where('end_date', '<=', Carbon::now())
        ->get();

        foreach ($subscriptions as $subscription) {

            // Check if subscription end_date is reached
            if ($subscription->isPaymentDue()) {
                try {
                    $this->info("Processing subscription ID: {$subscription->id}");

                    // Call payment gateway simulation
                    $paymentResult = $this->processPayment($subscription);

                    if ($paymentResult['success']) {
                        // Extend subscription based on billing cycle
                        $newEndDate = $subscription->billing_cycle === 'monthly'
                            ? Carbon::now()->addMonth()
                            : Carbon::now()->addYear();

                        $subscription->update([
                            'end_date' => $newEndDate,
                            'last_payment_status' => 'success',
                        ]);

                        $this->info("Payment successful for subscription ID: {$subscription->id}");
                    } else {
                        $subscription->update([
                            'status' => 'failed',
                            'last_payment_status' => 'failed',
                        ]);
                        
                        $this->warn("Payment failed for subscription ID: {$subscription->id}");
                        // Optional: send email notification
                    }

                } catch (\Exception $e) {
                    $this->error("Error subscription ID {$subscription->id}: " . $e->getMessage());
                }
            }
        }

        $this->info('Recurring payment check completed.');
    }


    /**
     * Process a recurring payment for a subscription.
     *
     * @param Subscription $subscription
     * @return array ['success' => bool, 'message' => string]
     *
     * DESCRIPTION:
     *  - This function should be called whenever a payment is due (i.e., subscription renewal date reached).
     *  - It decides which payment method to use and either simulates or triggers the real charge via API.
     *  - For real cards, this is where you call Raiffeisen API / 3D Secure redirect.
     *  - For PayPal, call PayPal API.
     *  - For bank transfer, usually manual confirmation.
     */
    protected function processPayment(Subscription $subscription)
    {
        switch ($subscription->recurring_payment_method) {
            case 'cc':
                // ✅ HERE IS WHERE YOU CHARGE THE USER CARD
                // 1. Retrieve $subscription->cc_payment_id (token / saved payment reference)
                // 2. Call Raiffeisen API or redirect to 3D secure popup
                // 3. Handle response: success or failed
                return ['success' => true, 'message' => 'Simulated CC charge successful'];

            case 'paypal':
                // ✅ HERE IS WHERE YOU CALL PAYPAL API
                // Use $subscription->paypal_payment_id to process recurring payment
                return ['success' => true, 'message' => 'Simulated PayPal charge successful'];

            case 'bank_transfer':
                // Usually manual confirmation
                return ['success' => true, 'message' => 'Manual bank transfer confirmed'];

            default:
                return ['success' => false, 'message' => 'Invalid payment method'];
        }
    }

    
}
