<?php

namespace Tests\Feature\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentProofStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Uploading a transfer receipt against a pay-link token, with no session —
 * what a guest who chose bank transfer does after the money leaves their bank.
 */
class PublicProofUploadTest extends BillingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The route allows 5 uploads a minute per IP. Every test here shares
        // 127.0.0.1, so without this the later ones 429 instead of exercising
        // what they are about — the limit itself is not what is under test.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function payableInvoice(User $client): Invoice
    {
        $invoice = $this->issueInvoice($this->makeOrder($client));

        $invoice->forceFill([
            'public_token' => Str::random(64),
            'public_token_expires_at' => now()->addDays(30),
        ])->save();

        return $invoice;
    }

    public function test_a_guest_uploads_a_receipt_with_only_the_token(): void
    {
        Storage::fake('local');
        $client = $this->makeClient();
        $invoice = $this->payableInvoice($client);

        $this->postJson("/api/v1/public/invoices/{$invoice->public_token}/proof", [
            'file' => UploadedFile::fake()->create('slip.pdf', 200, 'application/pdf'),
            'note' => 'transferred today',
        ])->assertCreated()
            ->assertJsonPath('status', PaymentProofStatus::Pending->value)
            ->assertJsonPath('invoice_status', InvoiceStatus::AwaitingConfirmation->value);

        // The proof belongs to the account the invoice is for, not to nobody.
        $this->assertSame($client->id, $invoice->refresh()->payments()->first()->user_id);
    }

    public function test_uploading_never_settles_the_invoice(): void
    {
        Storage::fake('local');
        $invoice = $this->payableInvoice($this->makeClient());

        $this->postJson("/api/v1/public/invoices/{$invoice->public_token}/proof", [
            'file' => UploadedFile::fake()->image('slip.png'),
        ])->assertCreated();

        $invoice->refresh();
        $this->assertSame(0.00, (float) $invoice->amount_paid);
        $this->assertNotSame(InvoiceStatus::Paid, $invoice->status);
    }

    public function test_an_executable_renamed_to_pdf_is_refused(): void
    {
        Storage::fake('local');
        $invoice = $this->payableInvoice($this->makeClient());

        $this->postJson("/api/v1/public/invoices/{$invoice->public_token}/proof", [
            'file' => UploadedFile::fake()->create('payload.pdf', 10, 'application/x-msdownload'),
        ])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_unknown_and_expired_tokens_are_both_a_bare_404(): void
    {
        Storage::fake('local');
        $client = $this->makeClient();

        $expired = $this->payableInvoice($client);
        $expired->forceFill(['public_token_expires_at' => now()->subDay()])->save();

        $this->postJson("/api/v1/public/invoices/{$expired->public_token}/proof", [
            'file' => UploadedFile::fake()->image('slip.png'),
        ])->assertStatus(404);

        $this->postJson('/api/v1/public/invoices/'.Str::random(64).'/proof', [
            'file' => UploadedFile::fake()->image('slip.png'),
        ])->assertStatus(404);
    }
}
