<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PaymentConfirmationMail;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PayPalController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly InvoiceService $invoices,
    ) {}

    public function createPayment(Request $request)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
        ]);

        $user = $request->user();

        $invoice = Invoice::where('id', $validated['invoice_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        $response = $provider->createOrder([
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => 'EUR',
                        'value' => number_format((float) $validated['amount'], 2, '.', ''),
                    ],
                    // Unique per attempt — PayPal rejects reused invoice IDs (DUPLICATE_INVOICE_ID)
                    'invoice_id' => $invoice->id.'_'.uniqid(),
                    // custom_id is echoed back in the capture response; use it to look up the invoice
                    'custom_id' => (string) $invoice->id,
                ],
            ],
            'application_context' => [
                'cancel_url' => route('paypal.cancel'),
                'return_url' => route('paypal.success'),
            ],
        ]);

        if (isset($response['id']) && $response['id'] !== null) {
            foreach ($response['links'] as $link) {
                if ($link['rel'] === 'approve') {
                    return response()->json(['approval_url' => $link['href']]);
                }
            }
        }

        Log::error('PayPal createOrder failed', ['response' => $response]);

        return response()->json(['error' => 'Failed to create PayPal order'], 400);
    }

    public function success(Request $request)
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        $token = $request->query('token');
        if (! $token) {
            return redirect($frontendUrl.'/client?payment=failed');
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        // showOrderDetails includes custom_id/invoice_id; capturePaymentOrder does not
        $order = $provider->showOrderDetails($token);
        $status = $order['status'] ?? null;

        if (! isset($order['purchase_units'])) {
            Log::error('PayPal order not in expected state', ['token' => $token, 'status' => $status]);

            return redirect($frontendUrl.'/client?payment=failed');
        }

        $orderUnit = $order['purchase_units'][0] ?? null;
        if (! $orderUnit) {
            return redirect($frontendUrl.'/client?payment=failed');
        }

        $invoiceId = $orderUnit['custom_id'] ?? null;
        $captureId = $token;

        if ($status === 'APPROVED') {
            $capture = $provider->capturePaymentOrder($token);
            if (($capture['status'] ?? null) !== 'COMPLETED' || ! isset($capture['purchase_units'])) {
                Log::error('PayPal capture failed', ['token' => $token, 'response' => $capture]);

                return redirect($frontendUrl.'/client?payment=failed');
            }
            $captureId = $capture['purchase_units'][0]['payments']['captures'][0]['id'] ?? $token;
        } elseif ($status === 'COMPLETED') {
            $captureId = $orderUnit['payments']['captures'][0]['id'] ?? $token;
        } else {
            Log::error('PayPal order not in expected state', ['token' => $token, 'status' => $status]);

            return redirect($frontendUrl.'/client?payment=failed');
        }

        $invoice = Invoice::find($invoiceId);
        if (! $invoice) {
            Log::error('PayPal success: invoice not found', ['invoice_id' => $invoiceId]);

            return redirect($frontendUrl.'/client?payment=failed');
        }

        // Idempotency — same callback hit twice (refresh, retry, etc.)
        if ($invoice->status === 'paid' && $invoice->subscription_id) {
            return redirect($frontendUrl.'/client?payment=success');
        }

        $user = User::find($invoice->user_id);
        if (! $user || ! $invoice->package_id) {
            return redirect($frontendUrl.'/client?payment=failed');
        }

        $package = Package::where('id', $invoice->package_id)
            ->where('active', true)
            ->firstOrFail();

        $billingCycle = $this->resolveBillingCycle($invoice, $package);
        $amount = $this->subscriptions->priceFor($package, $billingCycle);

        // Subscribe is idempotent + locks the user's subscription rows; safe under concurrent callbacks.
        $subscription = $this->subscriptions->subscribe(
            user: $user,
            package: $package,
            billingCycle: $billingCycle,
            paymentMethod: 'paypal',
            paymentToken: $captureId,
            paymentBrand: 'paypal',
        );

        DB::transaction(function () use ($invoice, $subscription) {
            $invoice->fresh()->update([
                'subscription_id' => $subscription->id,
                'package_id' => $subscription->package_id,
                'subscribe_payment_id' => $subscription->paypal_payment_id,
                'payment_method' => 'paypal',
                'status' => 'paid',
                'paid_at' => now(),
                'billing_period_start' => $subscription->start_date,
                'billing_period_end' => $subscription->end_date,
            ]);
        });

        try {
            Mail::to($user->email)->send(new PaymentConfirmationMail(
                userName: $user->name,
                packageName: $package->name,
                amount: (float) $amount,
                captureId: $captureId,
                billingCycle: $billingCycle,
            ));
        } catch (\Throwable $e) {
            Log::error('PayPal payment email failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        return redirect($frontendUrl.'/client?payment=success');
    }

    public function cancel()
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        return redirect($frontendUrl.'/client/plans-billing?payment=cancelled');
    }

    private function resolveBillingCycle(Invoice $invoice, Package $package): string
    {
        if ($invoice->subscription_id && $invoice->subscription && $invoice->subscription->billing_cycle) {
            return $invoice->subscription->billing_cycle;
        }

        $amount = (float) $invoice->amount;
        if ((float) $package->price_yearly > 0 && abs($amount - (float) $package->price_yearly) < 0.01) {
            return 'yearly';
        }

        return 'monthly';
    }
}
