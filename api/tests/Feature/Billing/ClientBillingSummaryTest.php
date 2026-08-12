<?php

namespace Tests\Feature\Billing;

use App\Enums\InvoiceType;

/**
 * The client dashboard reads `next_due` from the billing summary to decide what
 * to show as "the invoice you have to pay". Authenticates with actingAs on the
 * `api` guard, same as the other client-side billing tests.
 */
class ClientBillingSummaryTest extends BillingTestCase
{
    public function test_next_due_is_null_when_nothing_is_outstanding(): void
    {
        $response = $this->actingAs($this->makeClient(), 'api')
            ->getJson('/api/v1/client/billing/summary')
            ->assertOk();

        $this->assertNull($response->json('next_due'));
        $this->assertSame(0, $response->json('open_invoices'));
    }

    public function test_next_due_describes_the_open_invoice(): void
    {
        $client = $this->makeClient();
        $invoice = $this->issueInvoice($this->makeOrder($client), InvoiceType::OneOff);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/billing/summary')
            ->assertOk();

        $this->assertSame($invoice->id, $response->json('next_due.id'));
        $this->assertSame($invoice->invoice_number, $response->json('next_due.invoice_number'));
        $this->assertSame((float) $invoice->amount_due, $response->json('next_due.amount_due'));
        $this->assertSame($invoice->due_at?->toDateString(), $response->json('next_due.due_at'));
    }

    /**
     * The dashboard leads with this invoice, so picking the wrong one is
     * visible to the client. due_at is nullable and MySQL sorts NULLs first,
     * which without the explicit NULLS-LAST ordering would surface an undated
     * invoice ahead of one that is actually due.
     */
    public function test_the_earliest_dated_invoice_wins_over_an_undated_one(): void
    {
        $client = $this->makeClient();

        $undated = $this->issueInvoice($this->makeOrder($client), InvoiceType::OneOff);
        $undated->forceFill(['due_at' => null])->saveQuietly();

        $dated = $this->issueInvoice($this->makeOrder($client), InvoiceType::OneOff);
        $dated->forceFill(['due_at' => now()->addDays(3)])->saveQuietly();

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/billing/summary')
            ->assertOk();

        $this->assertSame($dated->id, $response->json('next_due.id'));
    }

    public function test_a_paid_invoice_is_not_offered_for_payment(): void
    {
        $client = $this->makeClient();
        $invoice = $this->issueInvoice($this->makeOrder($client), InvoiceType::OneOff);

        $invoice->forceFill([
            'status' => \App\Enums\InvoiceStatus::Paid,
            'amount_paid' => $invoice->total_gross,
            'amount_due' => 0,
            'paid_at' => now(),
        ])->save();

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/billing/summary')
            ->assertOk();

        $this->assertNull($response->json('next_due'));
    }

    public function test_another_clients_invoice_never_appears(): void
    {
        $this->issueInvoice($this->makeOrder($this->makeClient()), InvoiceType::OneOff);

        $response = $this->actingAs($this->makeClient(), 'api')
            ->getJson('/api/v1/client/billing/summary')
            ->assertOk();

        $this->assertNull($response->json('next_due'));
    }
}
