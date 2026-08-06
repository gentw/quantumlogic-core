<?php

namespace Tests\Feature\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Exceptions\InvalidInvoiceStateException;
use App\Exceptions\InvoiceLockedException;
use App\Services\BillingInvoiceService;

class InvoiceImmutabilityTest extends BillingTestCase
{
    public function test_commercial_content_is_frozen_after_issue(): void
    {
        $invoice = $this->issueInvoice($this->makeOrder($this->makeClient()));

        $this->expectException(InvoiceLockedException::class);

        $invoice->subtotal_net = 1;
        $invoice->save();
    }

    public function test_payment_tracking_still_moves_after_issue(): void
    {
        $invoice = $this->issueInvoice($this->makeOrder($this->makeClient()));

        $invoice->amount_paid = 10.00;
        $invoice->save();

        $this->assertSame(10.00, (float) $invoice->refresh()->amount_paid);
    }

    public function test_an_invoice_can_never_be_unlocked(): void
    {
        $invoice = $this->issueInvoice($this->makeOrder($this->makeClient()));

        $this->expectException(InvoiceLockedException::class);

        $invoice->locked_at = null;
        $invoice->save();
    }

    public function test_issued_invoices_cannot_be_cancelled_only_credit_noted(): void
    {
        $invoice = $this->issueInvoice($this->makeOrder($this->makeClient()));

        $this->expectException(InvalidInvoiceStateException::class);

        app(BillingInvoiceService::class)->cancel($invoice);
    }

    public function test_the_credit_note_negates_and_cancels_the_parent(): void
    {
        $invoice = $this->issueInvoice($this->makeOrder($this->makeClient(), 100.00));

        $note = app(BillingInvoiceService::class)->creditNote($invoice, null, 'dispute');

        $this->assertSame(InvoiceType::CreditNote, $note->type);
        $this->assertSame($invoice->id, $note->parent_invoice_id);
        $this->assertSame(-(float) $invoice->total_gross, (float) $note->total_gross);
        $this->assertNotNull($note->locked_at, 'credit notes are issued and locked immediately');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Cancelled, $invoice->status);
        $this->assertSame(0.00, (float) $invoice->amount_due);
        $this->assertContains('credit_noted', $invoice->activities()->pluck('event')->all());
    }

    public function test_a_credit_note_cannot_be_credit_noted(): void
    {
        $invoice = $this->issueInvoice($this->makeOrder($this->makeClient()));
        $note = app(BillingInvoiceService::class)->creditNote($invoice);

        $this->expectException(InvalidInvoiceStateException::class);

        app(BillingInvoiceService::class)->creditNote($note);
    }
}
