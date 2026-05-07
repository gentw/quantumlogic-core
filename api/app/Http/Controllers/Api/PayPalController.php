<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Package;
use App\Models\Invoice;
use App\Models\SubscriptionPayment;

use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PayPalController extends Controller
{
   public function createPayment(Request $request)
   {
       $provider = new PayPalClient;
       $provider->setApiCredentials(config('paypal'));
       $paypalToken = $provider->getAccessToken();

       $response = $provider->createOrder([
           "intent" => "CAPTURE",
           "purchase_units" => [
               [
                   "amount" => [
                       "currency_code" => "EUR",
                       "value" => $request->amount
                   ],
                   "invoice_id" => $request->invoice_id,
               ]
           ],
           "application_context" => [
               "cancel_url" => route('paypal.cancel'),
               "return_url" => route('paypal.success'),
           ]
       ]);

       if (isset($response['id']) && $response['id'] != null) {
           foreach ($response['links'] as $link) {
               if ($link['rel'] === 'approve') {
                   //return redirect()->away($link['href']);
                   return response()->json([
                        'approval_url' => $link['href']
                    ]);
               }
           }
       }


       return response()->json("Cancelled", 400);
   }

   public function success(Request $request)
   {
        $token = $request->query('token');
        $user = $request->user();
        if (!$token) {
            return response()->json('Missing PayPal token', 400);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        $order = $provider->showOrderDetails($token);

        if (!isset($order['status']) || $order['status'] !== 'APPROVED') {
            return response()->json([
                'error' => 'Order not approved',
                'order' => $order
            ], 400);
        }

        $response = $provider->capturePaymentOrder($token);

        $purchaseUnit = $response['purchase_units'][0];
        $invoiceId = $purchaseUnit['invoice_id'];

        if (isset($response['status']) && $response['status'] === 'COMPLETED') {
            
            $invoice = Invoice::where('id', $invoiceId)->where('user_id', $user->id)->first();
            
            $package = Package::where('id', $invoice->package_id)
            ->where('active', true)
            ->firstOrFail();

            // Check for existing subscription
            
            // Check if a trial exists
            $trial = $user->subscriptions()
                ->where('status', 'trial')
                ->where('package_id', 1) // Starter package
                ->first();

            $amount = $validated['billing_cycle'] === 'yearly' ? $package->price_yearly : $package->price_monthly;

            if ($trial) {
                $payment = SubscriptionPayment::create([
                    'subscription_id' => $trial->id,
                    'payment_method' => $validated['payment_method'],
                    'payment_token' => $validated['payment_token'],
                    'amount' => $amount,
                ]);

                // Upgrade trial to paid subscription
                $trial->update([
                    'status' => 'active',
                    'start_date' => now(),
                    'end_date' => now()->addMonth(), // or addYear if yearly
                    'billing_cycle' => $validated['billing_cycle'],
                    'recurring_payment_method' => $validated['payment_method'],
                    'cc_payment_id' => $validated['payment_method'] === 'cc' ? $payment->id : null,
                    'paypal_payment_id' => $validated['payment_method'] === 'paypal' ? $payment->id : null,
                    'bank_transfer_payment_id' => $validated['payment_method'] === 'bank_transfer' ? $payment->id : null,
                    'auto_renew' => true
                ]);

                $subscription = $trial;
            } else {
                // Check for existing active subscription
                $existingSub = $user->subscriptions()->where('status', 'active')->first();

                if ($existingSub) {

                    $payment = SubscriptionPayment::create([
                        'subscription_id' => $existingSub->id,
                        'payment_method' => $validated['payment_method'],
                        'payment_token' => $validated['payment_token'],
                        'amount' => $amount,
                    ]);

                    // Extend existing subscription
                    $newEndDate = $validated['billing_cycle'] === 'yearly'
                        ? $existingSub->end_date->addYear()
                        : $existingSub->end_date->addMonth();

                    $existingSub->update([
                        'package_id' => $package->id, // optional: update package if different
                        'end_date' => $newEndDate,
                        'billing_cycle' => $validated['billing_cycle'],
                        'recurring_payment_method' => $validated['payment_method'],
                        'cc_payment_id' => $validated['payment_method'] === 'cc' ? $payment->id : null,
                        'paypal_payment_id' => $validated['payment_method'] === 'paypal' ? $payment->id : null,
                        'bank_transfer_payment_id' => $validated['payment_method'] === 'bank_transfer' ? $payment->id : null,
                        'auto_renew' => true
                    ]);

                    $subscription = $existingSub;
                } else {
                    // No trial or subscription exists → create new subscription
                    $startDate = now();
                    $endDate = $validated['billing_cycle'] === 'yearly' ? now()->addYear() : now()->addMonth();

                    $payment = SubscriptionPayment::create([
                        //'subscription_id' => $existingSub->id,
                        'payment_method' => $validated['payment_method'],
                        'payment_token' => $validated['payment_token'],
                        'amount' => $amount,
                    ]);

                    $subscription = $user->subscriptions()->create([
                        'package_id' => $package->id,
                        'status' => 'active',
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'billing_cycle' => $validated['billing_cycle'],
                        'recurring_payment_method' => $validated['payment_method'],
                        'cc_payment_id' => $validated['payment_method'] === 'cc' ? $payment->id : null,
                        'paypal_payment_id' => $validated['payment_method'] === 'paypal' ? $payment->id : null,
                        'bank_transfer_payment_id' => $validated['payment_method'] === 'bank_transfer' ? $payment->id: null,
                        'auto_renew' => true
                    ]);

                    $payment = SubscriptionPayment::where('id', $payment->id)->update(
                        [
                        'subscription_id' => $subscription->id
                        ]
                    );
                }
            }

            // Create invoice
            Invoice::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'subscribe_payment_id' => $payment->id,
                'amount' => $amount,
                'status' => 'paid',
                'description' => "Subscription ({$validated['billing_cycle']}) – {$package->name}",
            ]);

        }

        return response()->json([
            'error' => 'Capture failed',
            'response' => $response
        ], 400);
    }


   public function cancel()
   {
       return "Payment cancelled!";
   }
}