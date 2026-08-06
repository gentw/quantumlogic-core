<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\PayPalOrderRequest;
use App\Models\Invoice;
use App\Services\PaymentService;
use App\Services\PayPalGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The invoice-based PayPal rail. The legacy PayPalController stays for the
 * retired subscription module; nothing here touches packages or plans.
 */
class BillingPayPalController extends Controller
{
    public function __construct(
        private readonly PayPalGateway $paypal,
        private readonly PaymentService $payments,
    ) {}

    /** Start a PayPal payment: pending payments row + order + approval URL. */
    public function createOrder(PayPalOrderRequest $request, Invoice $invoice): JsonResponse
    {
        abort_unless(in_array($invoice->status, [InvoiceStatus::Sent, InvoiceStatus::Unpaid], true), 422, 'This invoice cannot be paid.');

        $amount = min(
            (float) ($request->validated('amount') ?? $invoice->amount_due),
            (float) $invoice->amount_due
        );

        $payment = $this->payments->recordPending($invoice, PaymentProvider::PayPal, $amount, [
            'user_id' => $request->user()->id,
            'idempotency_key' => null,
        ]);
        $payment->forceFill(['idempotency_key' => 'paypal:pmt:'.$payment->id])->save();

        $order = $this->paypal->createOrder(
            $payment,
            $invoice,
            route('paypal.billing.success'),
            route('paypal.billing.cancel'),
        );

        $payment->forceFill(['metadata' => ['paypal_order_id' => $order['order_id']]])->save();

        return response()->json([
            'payment_id' => $payment->id,
            'approval_url' => $order['approval_url'],
        ]);
    }

    /**
     * Browser return: capture, apply, navigate. Application is idempotent
     * with the webhook — whichever lands first wins, the other no-ops.
     */
    public function success(Request $request)
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $token = $request->query('token');

        if (! $token) {
            return redirect($frontendUrl.'/client/billing?payment=failed');
        }

        try {
            $capture = $this->paypal->captureOrder($token);
            $payment = \App\Models\Payment::findOrFail($capture['payment_id']);

            $this->payments->apply(
                $payment->invoice,
                PaymentProvider::PayPal,
                $payment->idempotency_key ?? 'paypal:pmt:'.$payment->id,
                $capture['amount'],
                ['provider_payment_id' => $capture['capture_id'], 'method_brand' => 'paypal'],
            );
        } catch (\Throwable $e) {
            Log::error('PayPal billing return failed', ['token' => $token, 'error' => $e->getMessage()]);

            return redirect($frontendUrl.'/client/billing?payment=failed');
        }

        return redirect($frontendUrl.'/client/billing?payment=success');
    }

    public function cancel(Request $request)
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        return redirect($frontendUrl.'/client/billing?payment=cancelled');
    }
}
