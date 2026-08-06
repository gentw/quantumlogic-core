<?php

namespace Tests\Feature\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentAlreadyAppliedException;
use App\Jobs\ProcessPayPalWebhookJob;
use App\Jobs\ProcessStripeWebhookJob;
use App\Services\PaymentService;

class PaymentIdempotencyTest extends BillingTestCase
{
    public function test_replaying_the_same_idempotency_key_applies_exactly_once(): void
    {
        $client = $this->makeClient();
        $invoice = $this->issueInvoice($this->makeOrder($client, 100.00));
        $payments = app(PaymentService::class);

        $first = $payments->apply($invoice, PaymentProvider::Stripe, 'test-key', 60.00, []);
        $second = $payments->apply($invoice, PaymentProvider::Stripe, 'test-key', 60.00, []);

        $this->assertSame($first->id, $second->id);
        $invoice->refresh();
        $this->assertSame(60.00, (float) $invoice->amount_paid);
        $this->assertSame(1, $invoice->payments()->count());
    }

    public function test_a_new_key_against_a_settled_invoice_is_refused_but_replays_stay_noops(): void
    {
        $client = $this->makeClient();
        $invoice = $this->issueInvoice($this->makeOrder($client, 100.00));
        $payments = app(PaymentService::class);

        $payments->apply($invoice, PaymentProvider::Stripe, 'settle-key', (float) $invoice->amount_due, []);
        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);

        $this->expectException(PaymentAlreadyAppliedException::class);

        try {
            $payments->apply($invoice, PaymentProvider::Stripe, 'another-key', 5.00, []);
        } finally {
            // The replay of the original key must still be a silent no-op.
            $replay = $payments->apply($invoice, PaymentProvider::Stripe, 'settle-key', 1.00, []);
            $this->assertSame(PaymentStatus::Succeeded, $replay->status);
        }
    }

    public function test_a_replayed_stripe_webhook_event_is_a_noop(): void
    {
        $client = $this->makeClient();
        $invoice = $this->issueInvoice($this->makeOrder($client, 100.00));
        $payments = app(PaymentService::class);

        $payment = $payments->recordPending($invoice, PaymentProvider::Stripe, (float) $invoice->amount_due, []);
        $payment->forceFill(['provider_payment_id' => 'pi_replay', 'idempotency_key' => 'stripe:pi:pi_replay'])->save();

        $event = [
            'id' => 'pi_replay',
            'amount_received' => (int) round($invoice->amount_due * 100),
            'metadata' => ['payment_id' => (string) $payment->id],
        ];

        app()->call([new ProcessStripeWebhookJob('payment_intent.succeeded', $event), 'handle']);
        app()->call([new ProcessStripeWebhookJob('payment_intent.succeeded', $event), 'handle']);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame((float) $invoice->total_gross, (float) $invoice->amount_paid);
        $this->assertSame(1, $invoice->payments()->count());
    }

    public function test_an_unsigned_stripe_webhook_is_rejected_with_400(): void
    {
        $this->postJson('/api/v1/webhooks/stripe', ['type' => 'payment_intent.succeeded'])
            ->assertStatus(400);
    }

    public function test_a_double_paypal_capture_callback_applies_once(): void
    {
        $client = $this->makeClient();
        $invoice = $this->issueInvoice($this->makeOrder($client, 100.00));
        $payments = app(PaymentService::class);

        $payment = $payments->recordPending($invoice, PaymentProvider::PayPal, (float) $invoice->amount_due, []);
        $payment->forceFill(['idempotency_key' => 'paypal:pmt:'.$payment->id])->save();

        $resource = [
            'id' => 'CAP-1',
            'custom_id' => (string) $payment->id,
            'amount' => ['value' => number_format((float) $invoice->amount_due, 2, '.', '')],
        ];

        app()->call([new ProcessPayPalWebhookJob('PAYMENT.CAPTURE.COMPLETED', $resource), 'handle']);
        app()->call([new ProcessPayPalWebhookJob('PAYMENT.CAPTURE.COMPLETED', $resource), 'handle']);

        $this->assertSame(1, $invoice->refresh()->payments()->count());
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
    }

    public function test_a_refund_reopens_a_settled_invoice(): void
    {
        $client = $this->makeClient();
        $invoice = $this->issueInvoice($this->makeOrder($client, 100.00));
        $payments = app(PaymentService::class);

        $payment = $payments->apply($invoice, PaymentProvider::Stripe, 'refund-key', (float) $invoice->amount_due, []);
        $payments->refund($payment, 50.00);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Sent, $invoice->status);
        $this->assertSame(50.00, (float) $invoice->amount_due);
        $this->assertNull($invoice->paid_at);
        $this->assertSame(PaymentStatus::PartiallyRefunded, $payment->refresh()->status);
    }
}
