<?php

namespace Tests\Feature\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentProofStatus;
use App\Enums\PaymentStatus;
use App\Services\BankTransferService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BankProofTest extends BillingTestCase
{
    private function uploadProof($invoice, $client)
    {
        Storage::fake('local');

        return app(BankTransferService::class)->submitProof(
            $invoice,
            UploadedFile::fake()->image('slip.png'),
            'paid this morning',
            $client,
        );
    }

    public function test_a_pending_proof_does_not_mark_the_invoice_paid(): void
    {
        $client = $this->makeClient();
        $invoice = $this->issueInvoice($this->makeOrder($client));

        $proof = $this->uploadProof($invoice, $client);

        $invoice->refresh();
        $this->assertSame(PaymentProofStatus::Pending, $proof->status);
        $this->assertSame(InvoiceStatus::AwaitingConfirmation, $invoice->status);
        $this->assertNotSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame(0.00, (float) $invoice->amount_paid);
        $this->assertSame(PaymentStatus::AwaitingConfirmation, $proof->payment->status);
    }

    public function test_accepting_a_proof_settles_the_invoice_with_the_confirming_admin_recorded(): void
    {
        $client = $this->makeClient();
        $admin = $this->makeAdmin();
        $invoice = $this->issueInvoice($this->makeOrder($client));

        $proof = $this->uploadProof($invoice, $client);
        app(BankTransferService::class)->accept($proof, $admin);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame(0.00, (float) $invoice->amount_due);
        $this->assertSame($admin->id, $proof->payment->refresh()->confirmed_by_admin_id);
        $this->assertSame(PaymentProofStatus::Accepted, $proof->refresh()->status);
    }

    public function test_rejecting_a_proof_reopens_the_invoice_with_the_reason(): void
    {
        $client = $this->makeClient();
        $admin = $this->makeAdmin();
        $invoice = $this->issueInvoice($this->makeOrder($client));

        $proof = $this->uploadProof($invoice, $client);
        app(BankTransferService::class)->reject($proof, $admin, 'amount does not match');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Sent, $invoice->status);
        $this->assertSame(PaymentProofStatus::Rejected, $proof->refresh()->status);
        $this->assertSame('amount does not match', $proof->rejection_reason);
        $this->assertSame(PaymentStatus::Failed, $proof->payment->refresh()->status);
    }

    public function test_reviewing_twice_is_a_noop(): void
    {
        $client = $this->makeClient();
        $admin = $this->makeAdmin();
        $invoice = $this->issueInvoice($this->makeOrder($client));

        $proof = $this->uploadProof($invoice, $client);
        app(BankTransferService::class)->accept($proof, $admin);
        app(BankTransferService::class)->accept($proof, $admin);
        app(BankTransferService::class)->reject($proof->refresh(), $admin, 'too late');

        $this->assertSame(PaymentProofStatus::Accepted, $proof->refresh()->status);
        $this->assertSame((float) $invoice->total_gross, (float) $invoice->refresh()->amount_paid);
    }
}
