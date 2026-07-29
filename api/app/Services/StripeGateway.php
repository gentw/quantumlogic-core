<?php

namespace App\Services;

use App\Exceptions\PaymentGatewayException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod as StripePaymentMethod;
use Stripe\SetupIntent;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Thin wrapper over the Stripe SDK: PaymentIntents, SetupIntents, refunds,
 * webhook verification. All money amounts cross this boundary in cents.
 * Payment application never happens here — the webhook handler feeds
 * PaymentService, which owns the invoice math.
 */
class StripeGateway
{
    private ?StripeClient $client = null;

    /**
     * One PaymentIntent per payment attempt; the Stripe idempotency key is
     * derived from the payments row so a retried request cannot double-create.
     */
    public function createPaymentIntent(Payment $payment, Invoice $invoice): PaymentIntent
    {
        try {
            return $this->client()->paymentIntents->create([
                'amount' => Money::toCents((float) $payment->amount),
                'currency' => strtolower($invoice->currency ?? 'EUR'),
                'automatic_payment_methods' => ['enabled' => true],
                'metadata' => [
                    'payment_id' => (string) $payment->id,
                    'invoice_id' => (string) $invoice->id,
                    'invoice_number' => (string) $invoice->invoice_number,
                ],
            ], [
                'idempotency_key' => 'payment-intent-'.$payment->id,
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe createPaymentIntent failed', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
            throw PaymentGatewayException::provider('stripe', 'Could not start the card payment.', $e);
        }
    }

    /** SetupIntent to save a card off-payment (recurring, guest identity). */
    public function createSetupIntent(User $user, ?string $customerId = null): SetupIntent
    {
        try {
            $customerId ??= $this->createCustomer($user);

            return $this->client()->setupIntents->create([
                'customer' => $customerId,
                'automatic_payment_methods' => ['enabled' => true],
                'metadata' => ['user_id' => (string) $user->id],
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe createSetupIntent failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            throw PaymentGatewayException::provider('stripe', 'Could not start saving the card.', $e);
        }
    }

    public function createCustomer(User $user): string
    {
        try {
            return $this->client()->customers->create([
                'email' => $user->email,
                'name' => trim($user->name.' '.$user->surname),
                'metadata' => ['user_id' => (string) $user->id],
            ])->id;
        } catch (ApiErrorException $e) {
            Log::error('Stripe createCustomer failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            throw PaymentGatewayException::provider('stripe', 'Could not register the customer.', $e);
        }
    }

    public function retrievePaymentMethod(string $paymentMethodId): StripePaymentMethod
    {
        try {
            return $this->client()->paymentMethods->retrieve($paymentMethodId);
        } catch (ApiErrorException $e) {
            Log::error('Stripe retrievePaymentMethod failed', ['pm' => $paymentMethodId, 'error' => $e->getMessage()]);
            throw PaymentGatewayException::provider('stripe', 'Could not load the saved card.', $e);
        }
    }

    /**
     * Off-session charge against a saved method (recurring billing). SCA can
     * demand authentication mid-cycle — that comes back as requires_action
     * with the client secret for an emailed authentication link, not as a
     * hard failure.
     *
     * @return array{status: string, intent_id: ?string, client_secret: ?string, error: ?string}
     */
    public function chargeOffSession(Payment $payment, Invoice $invoice, string $methodToken, ?string $customerId): array
    {
        try {
            $intent = $this->client()->paymentIntents->create([
                'amount' => Money::toCents((float) $payment->amount),
                'currency' => strtolower($invoice->currency ?? 'EUR'),
                'customer' => $customerId,
                'payment_method' => $methodToken,
                'off_session' => true,
                'confirm' => true,
                'metadata' => [
                    'payment_id' => (string) $payment->id,
                    'invoice_id' => (string) $invoice->id,
                ],
            ], [
                'idempotency_key' => 'recurring-'.$payment->id,
            ]);

            return [
                'status' => $intent->status === 'succeeded' ? 'succeeded'
                    : ($intent->status === 'requires_action' ? 'requires_action' : 'failed'),
                'intent_id' => $intent->id,
                'client_secret' => $intent->client_secret,
                'error' => null,
            ];
        } catch (ApiErrorException $e) {
            $intent = method_exists($e, 'getError') ? ($e->getError()->payment_intent ?? null) : null;

            if (($e->getError()->code ?? null) === 'authentication_required') {
                return [
                    'status' => 'requires_action',
                    'intent_id' => $intent->id ?? null,
                    'client_secret' => $intent->client_secret ?? null,
                    'error' => null,
                ];
            }

            Log::error('Stripe off-session charge failed', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);

            return [
                'status' => 'failed',
                'intent_id' => $intent->id ?? null,
                'client_secret' => null,
                'error' => $e->getError()->message ?? $e->getMessage(),
            ];
        }
    }

    /** Provider-side refund; bookkeeping happens in PaymentService::refund. */
    public function refund(Payment $payment, int $amountCents): void
    {
        try {
            $this->client()->refunds->create([
                'payment_intent' => $payment->provider_payment_id,
                'amount' => $amountCents,
            ], [
                'idempotency_key' => 'refund-'.$payment->id.'-'.$amountCents,
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe refund failed', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
            throw PaymentGatewayException::provider('stripe', 'The refund was not accepted.', $e);
        }
    }

    /**
     * Verify and parse a webhook payload. Throws on bad signatures — the
     * controller turns that into a 400.
     *
     * @throws SignatureVerificationException
     */
    public function constructWebhookEvent(string $payload, string $signatureHeader): Event
    {
        return Webhook::constructEvent(
            $payload,
            $signatureHeader,
            (string) config('stripe.webhook_secret')
        );
    }

    private function client(): StripeClient
    {
        return $this->client ??= new StripeClient((string) config('stripe.secret'));
    }
}
