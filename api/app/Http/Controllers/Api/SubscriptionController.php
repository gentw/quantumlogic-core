<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Subscription;
use App\Models\Package;
use App\Models\Invoice;
use App\Models\SubscriptionPayment;
use App\Http\Requests\StartTrialRequest;


class SubscriptionController extends Controller
{
    //

    public function upgradeDowngrade(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'new_package_id' => ['required', 'exists:packages,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'payment_method' => ['required', 'in:cc,paypal,bank_transfer'],
            'payment_token' => ['nullable', 'string'], // token / intent id
        ]);

        $newPackage = Package::where('id', $validated['new_package_id'])
            ->where('active', true)
            ->firstOrFail();

        // Fetch current active subscription
        $currentSub = $user->subscriptions()->where('status', 'active')->first();
        if (!$currentSub) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found. Please subscribe first.'
            ], 403);
        }
        
        $remainingDays = now()->diffInDays($currentSub->end_date);
        $dailyRate = ($currentSub->billing_cycle === 'yearly'
            ? $currentSub->package->price_yearly
            : $currentSub->package->price_monthly) / ($currentSub->billing_cycle === 'yearly' ? 365 : 30);

        $creditAmount = round($remainingDays * $dailyRate, 2);

      
        $newAmount = $validated['billing_cycle'] === 'yearly'
            ? $newPackage->price_yearly
            : $newPackage->price_monthly;

        $finalAmount = max(0, $newAmount - $creditAmount);

        
        $payment = SubscriptionPayment::create([
            'subscription_id' => $currentSub->id,
            'payment_method' => $validated['payment_method'],
            'payment_token' => $validated['payment_token'],
            'amount' => $finalAmount,
        ]);

        // Update subscription
        $currentSub->update([
            'package_id' => $newPackage->id,
            'billing_cycle' => $validated['billing_cycle'],
            'recurring_payment_method' => $validated['payment_method'],
            'cc_payment_id' => $validated['payment_method'] === 'cc' ? $payment->id : null,
            'paypal_payment_id' => $validated['payment_method'] === 'paypal' ? $payment->id : null,
            'bank_transfer_payment_id' => $validated['payment_method'] === 'bank_transfer' ? $payment->id : null,
            'auto_renew' => true,
            'end_date' => $validated['billing_cycle'] === 'yearly' 
                ? now()->addYear() 
                : now()->addMonth(),
        ]);

        // Create invoice for the upgrade/downgrade
        Invoice::create([
            'user_id' => $user->id,
            'subscription_id' => $currentSub->id,
            'subscribe_payment_id' => $payment->id,
            'amount' => $finalAmount,
            'status' => 'paid',
            'description' => "Subscription Upgrade/Downgrade – {$newPackage->name}",
        ]);

        return response()->json([
            'success' => true,
            'subscription' => $currentSub,
            'invoice_amount' => $finalAmount,
            'payment_id' => $payment->id,
        ]);
    }


    public function changePlanInvoice($package_id, Request $request) {
        
        $user = $request->user();

        $ip = $request->ip();

        $package = Package::find($package_id);

        if($request->is_annual) {
            $price = $package->price_yearly;
        } else {
            $price = $package->price_monthly;
        }

        $user->invoices()
        ->where('id', $request->invoice_id)
        ->update([
            'subscription_id' => null,
            'amount' => $price,
            'total'  => $price,
            'currency' => 'EUR',
            'status' => 'unpaid',
            'description' => $package->name,
            'ip_address' => $ip
        ]);

        $invoice = $user->invoices()
            ->where('id', $request->invoice_id)
            ->where('status', 'unpaid')
            ->firstOrFail();
    
        return response()->json([
            'success' => true,
            'message' => 'Invoice was created.',
            'invoice' => $invoice->id
        ]);
    }

    public function generateTrialInvoice(Request $request) {
        $user = $request->user();

        $fingerprint = $request->header('X-Trial-Fingerprint');
        $ip = $request->ip();

        if (!$fingerprint) {
            return response()->json([
                'message' => 'Invalid device.'
            ], 403);
        }

        $existingInvoiceNotUsed = $user->invoices()
            ->whereNull('subscription_id')
            ->where('status', 'unpaid')
            ->first();

        if ($existingInvoiceNotUsed) {
            return response()->json([
                'success' => true,
                'message' => 'Trial invoice already exists.',
                'invoice' => $existingInvoiceNotUsed->id
            ]);
        }

        $invoice = $user->invoices()->create([
            'subscription_id' => null,
            'amount' => 0.00,
            'total'  => 0.00,
            'currency' => 'EUR',
            'status' => 'unpaid',
            'description' => 'Starter Trial (7 days)',
            'is_trial' => true,
            'trial_fingerprint' => $fingerprint,
            'ip_address' => $ip,
            'package_id' => 1
        ]);
    
        return response()->json([
            'success' => true,
            'message' => 'Trial invoice was created.',
            'invoice' => $invoice->id
        ]);
    }

    public function generateInvoice(Request $request) {
        $user = $request->user();

        $ip = $request->ip();

        $package = Package::find($request->plan_id);

        if($request->is_annual) {
            $price = $package->price_yearly;
        } else {
            $price = $package->price_monthly;
        }

        $existingInvoiceNotUsed = $user->invoices()
            ->whereNull('subscription_id')
            ->where('status', 'unpaid')
            ->first();

        if ($existingInvoiceNotUsed) {

            $existingInvoiceNotUsed
            ->update([
                'subscription_id' => null,
                'amount' => $price,
                'total'  => $price,
                'currency' => 'EUR',
                'status' => 'unpaid',
                'description' => $package->name,
                'package_id' => $request->plan_id,
                'ip_address' => $ip
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Invoice already exists.',
                'invoice' => $existingInvoiceNotUsed->id
            ]);
        }

        $invoice = $user->invoices()->create([
            'subscription_id' => null,
            'amount' => $price,
            'total'  => $price,
            'currency' => 'EUR',
            'status' => 'unpaid',
            'description' => $package->description,
            'is_trial' => false,
            'ip_address' => $ip,
            'package_id' => $request->plan_id
        ]);
    
        return response()->json([
            'success' => true,
            'message' => 'Invoice was created.',
            'invoice' => $invoice->id
        ]);
    }

    // Start trial (7 days)
    public function startTrial(StartTrialRequest $request)
    {
        $user = $request->user();
        $package = Package::findOrFail($request->package_id);

       
        // Check: trial only allowed for Starter package
        if ($package->id != 1) {
            return response()->json([
                'success' => false,
                'message' => 'You are only allowed to start a trial for the Starter package.'
            ], 403);
        }

        // Check if user already has used trial
        $existingTrial = $user->subscriptions()
        ->where('package_id', 1)
        ->where('status', 'trial')
        ->first();

        if ($existingTrial) {
            return response()->json([
                'success' => false,
                'message' => 'You have already used your trial for this package.'
            ], 403);
        }

        $payment = SubscriptionPayment::create([
            'payment_method' => $request->payment_method,
            'payment_token' => $request->payment_token,
            'amount' => 0.00,
        ]);

        // Optional: Check if user has a credit card attached (for Paysera token)
        $subscription = $user->subscriptions()->create([
            'package_id' => 1,
            'status' => 'trial',
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'billing_cycle' => 'monthly',
            'recurring_payment_method' => $request->payment_method,
            'cc_payment_id' => $request->payment_method === 'cc' ? $payment->id : null,
            'paypal_payment_id' => $request->payment_method === 'paypal' ? $payment->id : null,
            'bank_transfer_payment_id' => $request->payment_method === 'bank_transfer' ? $payment->id : null,
            'auto_renew' => true
        ]);

        SubscriptionPayment::where('id', $payment->id)->update(
            [
                'subscription_id' => $subscription->id
            ]
        );

        $user->invoices()->create([
            'subscription_id' => $subscription->id,
            'amount' => 0.00,
            'currency' => 'EUR',
            'status' => 'paid',
            'description' => 'Starter Trial (7 days)',
            'payment_method' => $request->payment_method,
            'subscribe_payment_id' => $payment->id
        ]);
    
        return response()->json([
            'success' => true,
            'message' => 'Trial started successfully.',
            'trial_ends_at' => $subscription->end_date
        ]);
    }


    public function subscribe(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'package_id' => ['required', 'exists:packages,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'payment_method' => ['required', 'in:cc,paypal,bank_transfer'],
            'payment_token' => ['nullable', 'string'], // token / intent id
        ]);

        $package = Package::where('id', $validated['package_id'])
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


        return response()->json(
            [
                'success' => 'true',
                'subscription' => $subscription
            ]
        );

    }

}
