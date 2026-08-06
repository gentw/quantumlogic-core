<?php

namespace App\Services;

use App\Exceptions\PaymentGatewayException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Support\Facades\Log;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

/**
 * Orders-v2 wrapper over srmklive/paypal for the Billing & Payments rail.
 * custom_id always carries the payments row id — never the invoice id — so
 * redirect and webhook resolve the same row and share one idempotency key.
 */
class PayPalGateway
{
    /**
     * Create a PayPal order for a pending payment. Returns the approval URL
     * and the provider order id.
     *
     * @return array{order_id: string, approval_url: string}
     */
    public function createOrder(Payment $payment, Invoice $invoice, string $returnUrl, string $cancelUrl): array
    {
        try {
            $response = $this->client()->createOrder([
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'amount' => [
                            'currency_code' => $invoice->currency ?? 'EUR',
                            'value' => number_format((float) $payment->amount, 2, '.', ''),
                        ],
                        // Unique per attempt — PayPal rejects reused invoice ids.
                        'invoice_id' => 'pmt-'.$payment->id.'-'.uniqid(),
                        'custom_id' => (string) $payment->id,
                    ],
                ],
                'application_context' => [
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('PayPal createOrder failed', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
            throw PaymentGatewayException::provider('paypal', 'Could not start the PayPal payment.', $e);
        }

        $approval = collect($response['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;

        if (! isset($response['id']) || ! $approval) {
            Log::error('PayPal createOrder malformed response', ['response' => $response]);
            throw PaymentGatewayException::provider('paypal', 'Could not start the PayPal payment.');
        }

        return ['order_id' => $response['id'], 'approval_url' => $approval];
    }

    /**
     * Capture an approved order — or read the capture off one that completed
     * already (double callback, webhook-first, refresh). The sound
     * idempotency shape from the legacy controller, repointed at payments.
     *
     * @return array{capture_id: string, payment_id: int, amount: float}
     */
    public function captureOrder(string $orderToken): array
    {
        try {
            $order = $this->client()->showOrderDetails($orderToken);
            $status = $order['status'] ?? null;
            $unit = $order['purchase_units'][0] ?? null;

            if (! $unit) {
                throw PaymentGatewayException::provider('paypal', 'Order has no purchase units.');
            }

            if ($status === 'APPROVED') {
                $capture = $this->client()->capturePaymentOrder($orderToken);

                if (($capture['status'] ?? null) !== 'COMPLETED') {
                    throw PaymentGatewayException::provider('paypal', 'Capture did not complete.');
                }

                $unit = $capture['purchase_units'][0] ?? $unit;
            } elseif ($status !== 'COMPLETED') {
                throw PaymentGatewayException::provider('paypal', "Order in unexpected state: {$status}.");
            }

            $captureData = $unit['payments']['captures'][0] ?? [];

            return [
                'capture_id' => $captureData['id'] ?? $orderToken,
                'payment_id' => (int) ($captureData['custom_id'] ?? $unit['custom_id'] ?? 0),
                'amount' => (float) ($captureData['amount']['value'] ?? 0),
            ];
        } catch (PaymentGatewayException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('PayPal capture failed', ['token' => $orderToken, 'error' => $e->getMessage()]);
            throw PaymentGatewayException::provider('paypal', 'Could not capture the PayPal payment.', $e);
        }
    }

    /** verify-webhook-signature; false on anything but an explicit SUCCESS. */
    public function verifyWebhook(array $headers, array $event): bool
    {
        try {
            $response = $this->client()->verifyWebHook([
                'auth_algo' => $headers['paypal-auth-algo'] ?? '',
                'cert_url' => $headers['paypal-cert-url'] ?? '',
                'transmission_id' => $headers['paypal-transmission-id'] ?? '',
                'transmission_sig' => $headers['paypal-transmission-sig'] ?? '',
                'transmission_time' => $headers['paypal-transmission-time'] ?? '',
                'webhook_id' => (string) config('paypal.webhook_id'),
                'webhook_event' => $event,
            ]);

            return ($response['verification_status'] ?? null) === 'SUCCESS';
        } catch (\Throwable $e) {
            Log::error('PayPal webhook verification failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /** Provider-side refund; bookkeeping happens in PaymentService::refund. */
    public function refundCapture(Payment $payment, int $amountCents, string $note = ''): void
    {
        try {
            $this->client()->refundCapturedPayment(
                (string) $payment->provider_payment_id,
                'refund-'.$payment->id.'-'.$amountCents,
                Money::toEuros($amountCents),
                $note ?: 'Refund'
            );
        } catch (\Throwable $e) {
            Log::error('PayPal refund failed', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
            throw PaymentGatewayException::provider('paypal', 'The refund was not accepted.', $e);
        }
    }

    private function client(): PayPalClient
    {
        $client = new PayPalClient;
        $client->setApiCredentials(config('paypal'));
        $client->getAccessToken();

        return $client;
    }
}
