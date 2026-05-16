<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\SubscriptionStateException;
use App\Exceptions\TrialAbuseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StartTrialRequest;
use App\Http\Requests\SubscribeRequest;
use App\Models\Invoice;
use App\Models\Package;
use App\Services\InvoiceService;
use App\Services\SubscriptionService;
use App\Services\TrialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly TrialService $trials,
        private readonly InvoiceService $invoices,
    ) {}

    public function startTrial(StartTrialRequest $request): JsonResponse
    {
        $user = $request->user();
        $package = Package::where('id', $request->integer('package_id'))
            ->where('active', true)
            ->firstOrFail();

        if ($package->id !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Trials are only available for the Starter package.',
            ], 403);
        }

        try {
            $this->trials->assertEligible(
                $user,
                $request->ip(),
                $request->header('X-Trial-Fingerprint'),
            );
        } catch (TrialAbuseException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'reason' => $e->reason,
                'trial_blocked' => true,
            ], 403);
        }

        try {
            $subscription = $this->subscriptions->startTrial(
                $user,
                $package,
                $request->string('payment_method')->toString(),
                $request->string('payment_token')->toString(),
                $request->input('payment_brand'),
                $request->ip(),
                $request->header('X-Trial-Fingerprint'),
            );
        } catch (SubscriptionStateException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }

        $this->invoices->recordForPeriod(
            $subscription,
            $package,
            0.00,
            'Starter Trial (7 days)',
            paymentId: null,
            paymentMethod: $subscription->recurring_payment_method,
            status: 'paid',
            isTrial: true,
        );

        return response()->json([
            'success' => true,
            'message' => 'Trial started successfully.',
            'state' => $subscription->state,
            'trial_ends_at' => $subscription->end_date,
        ]);
    }

    public function subscribe(SubscribeRequest $request): JsonResponse
    {
        $user = $request->user();
        $package = Package::where('id', $request->integer('package_id'))
            ->where('active', true)
            ->firstOrFail();

        $billingCycle = $request->string('billing_cycle')->toString();

        try {
            $subscription = $this->subscriptions->subscribe(
                $user,
                $package,
                $billingCycle,
                $request->string('payment_method')->toString(),
                $request->input('payment_token'),
                $request->input('payment_brand'),
            );
        } catch (SubscriptionStateException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }

        $amount = $this->subscriptions->priceFor($package, $billingCycle);

        $this->invoices->recordForPeriod(
            $subscription,
            $package,
            $amount,
            "Subscription ({$billingCycle}) – {$package->name}",
            paymentId: $subscription->paypal_payment_id ?? $subscription->cc_payment_id ?? $subscription->bank_transfer_payment_id,
            paymentMethod: $subscription->recurring_payment_method,
            status: 'paid',
        );

        return response()->json([
            'success' => true,
            'state' => $subscription->state,
            'subscription' => $subscription,
        ]);
    }

    public function upgradeDowngrade(SubscribeRequest $request): JsonResponse
    {
        // Idempotent service handles: convert trial → active, extend active, reactivate past_due.
        return $this->subscribe($request);
    }

    public function generateTrialInvoice(Request $request): JsonResponse
    {
        $user = $request->user();
        $fingerprint = $request->header('X-Trial-Fingerprint');

        if (! $fingerprint) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid device fingerprint.',
            ], 403);
        }

        $existing = $user->invoices()
            ->whereNull('subscription_id')
            ->where('status', 'unpaid')
            ->where('is_trial', true)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Trial invoice already exists.',
                'invoice' => $existing->id,
            ]);
        }

        $invoice = $user->invoices()->create([
            'subscription_id' => null,
            'package_id' => 1,
            'amount' => 0.00,
            'total' => 0.00,
            'currency' => 'EUR',
            'status' => 'unpaid',
            'description' => 'Starter Trial (7 days)',
            'is_trial' => true,
            'trial_fingerprint' => $fingerprint,
            'ip_address' => $request->ip(),
            'issued_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Trial invoice was created.',
            'invoice' => $invoice->id,
        ]);
    }

    public function generateInvoice(Request $request): JsonResponse
    {
        $user = $request->user();
        $package = Package::where('id', $request->integer('plan_id'))
            ->where('active', true)
            ->firstOrFail();

        $price = $request->boolean('is_annual') ? $package->price_yearly : $package->price_monthly;

        $existing = $user->invoices()
            ->whereNull('subscription_id')
            ->where('status', 'unpaid')
            ->where('is_trial', false)
            ->first();

        if ($existing) {
            $existing->update([
                'amount' => $price,
                'total' => $price,
                'currency' => 'EUR',
                'description' => $package->name,
                'package_id' => $package->id,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Invoice already exists.',
                'invoice' => $existing->id,
            ]);
        }

        $invoice = $user->invoices()->create([
            'subscription_id' => null,
            'package_id' => $package->id,
            'amount' => $price,
            'total' => $price,
            'currency' => 'EUR',
            'status' => 'unpaid',
            'description' => $package->description ?? $package->name,
            'is_trial' => false,
            'ip_address' => $request->ip(),
            'issued_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Invoice was created.',
            'invoice' => $invoice->id,
        ]);
    }

    public function changePlanInvoice($package_id, Request $request): JsonResponse
    {
        $user = $request->user();
        $package = Package::where('id', $package_id)
            ->where('active', true)
            ->firstOrFail();

        $price = $request->boolean('is_annual') ? $package->price_yearly : $package->price_monthly;

        $invoice = Invoice::where('id', $request->integer('invoice_id'))
            ->where('user_id', $user->id)
            ->firstOrFail();

        $invoice->update([
            'subscription_id' => null,
            'package_id' => $package->id,
            'amount' => $price,
            'total' => $price,
            'currency' => 'EUR',
            'status' => 'unpaid',
            'description' => $package->name,
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Invoice was updated.',
            'invoice' => $invoice->id,
        ]);
    }
}
