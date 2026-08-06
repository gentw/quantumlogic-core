<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentProofStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidInvoiceStateException;
use App\Models\Invoice;
use App\Models\PaymentProof;
use App\Models\User;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * SEPA bank transfer: EPC QR (Giro-Code) payload, client proof intake, admin
 * accept/reject. An invoice is never paid on upload — only an admin
 * acceptance settles it, through PaymentService::confirm.
 */
class BankTransferService
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly BillingInvoiceService $invoices,
        private readonly BillingNotifier $notifier,
    ) {}

    /**
     * Beneficiary details + the EPC QR the client scans in a banking app.
     *
     * @return array{account_holder: string, iban: string, bic: string, bank_name: string, reference: string, amount: float, epc_payload: string, epc_qr_png: string}
     */
    public function bankDetails(Invoice $invoice): array
    {
        $payload = $this->epcPayload($invoice);

        $png = (new Builder(
            writer: new PngWriter,
            data: $payload,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 240,
            margin: 8,
        ))->build();

        return [
            'account_holder' => (string) config('company.legal_name'),
            'iban' => (string) config('company.iban'),
            'bic' => (string) config('company.bic'),
            'bank_name' => (string) config('company.bank_name'),
            'reference' => (string) $invoice->invoice_number,
            'amount' => (float) $invoice->amount_due,
            'epc_payload' => $payload,
            'epc_qr_png' => $png->getDataUri(),
        ];
    }

    /**
     * EPC069-12 "Giro-Code" payload — the string EU banking apps parse.
     * Reference = the invoice number, so reconciliation is by eye-match.
     */
    public function epcPayload(Invoice $invoice): string
    {
        return implode("\n", [
            'BCD',
            '002',
            '1', // UTF-8
            'SCT',
            (string) config('company.bic'),
            mb_substr((string) config('company.legal_name'), 0, 70),
            (string) config('company.iban'),
            'EUR'.number_format((float) $invoice->amount_due, 2, '.', ''),
            '', // purpose
            '', // structured reference
            mb_substr((string) $invoice->invoice_number, 0, 140),
        ]);
    }

    /**
     * Client announces a transfer with a bank slip. Creates the
     * awaiting_confirmation payment + pending proof and moves the invoice to
     * awaiting_confirmation. Private disk; the file is served only through
     * an authorised route.
     */
    public function submitProof(Invoice $invoice, UploadedFile $file, ?string $note, User $uploader): PaymentProof
    {
        return DB::transaction(function () use ($invoice, $file, $note, $uploader) {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [InvoiceStatus::Sent, InvoiceStatus::AwaitingConfirmation, InvoiceStatus::Unpaid], true)) {
                throw InvalidInvoiceStateException::make($locked, 'upload a payment proof for');
            }

            $payment = $this->payments->recordPending(
                $locked,
                PaymentProvider::BankTransfer,
                (float) $locked->amount_due,
                ['user_id' => $uploader->id],
                PaymentStatus::AwaitingConfirmation,
            );

            $proof = $payment->proofs()->create([
                'invoice_id' => $locked->id,
                'uploaded_by_user_id' => $uploader->id,
                'file_path' => $file->store('payment-proofs/'.$locked->id, 'local'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => (string) $file->getMimeType(), // content-sniffed, not the extension
                'size_bytes' => (int) $file->getSize(),
                'note' => $note,
                'status' => PaymentProofStatus::Pending,
            ]);

            $locked->forceFill(['status' => InvoiceStatus::AwaitingConfirmation])->save();
            $this->invoices->log($locked, 'proof_uploaded', $uploader, ['proof_id' => $proof->id]);

            $this->notifier->proofReceived($locked, trim($uploader->name.' '.$uploader->surname));

            return $proof;
        });
    }

    /** Admin accepts: proof accepted, payment confirmed, invoice recomputed. */
    public function accept(PaymentProof $proof, User $admin): PaymentProof
    {
        return DB::transaction(function () use ($proof, $admin) {
            $proof = PaymentProof::whereKey($proof->id)->lockForUpdate()->firstOrFail();

            if ($proof->status !== PaymentProofStatus::Pending) {
                return $proof; // already reviewed
            }

            $proof->forceFill([
                'status' => PaymentProofStatus::Accepted,
                'reviewed_by_user_id' => $admin->id,
                'reviewed_at' => now(),
            ])->save();

            $this->payments->confirm($proof->payment, $admin);

            return $proof->refresh();
        });
    }

    /** Admin rejects: reasoned, payment failed, invoice reopened for payment. */
    public function reject(PaymentProof $proof, User $admin, string $reason): PaymentProof
    {
        return DB::transaction(function () use ($proof, $admin, $reason) {
            $proof = PaymentProof::whereKey($proof->id)->lockForUpdate()->firstOrFail();

            if ($proof->status !== PaymentProofStatus::Pending) {
                return $proof;
            }

            $proof->forceFill([
                'status' => PaymentProofStatus::Rejected,
                'reviewed_by_user_id' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            $this->payments->fail($proof->payment, 'Proof rejected: '.$reason);

            $invoice = Invoice::whereKey($proof->invoice_id)->lockForUpdate()->firstOrFail();

            $stillPending = $invoice->payments()
                ->where('status', PaymentStatus::AwaitingConfirmation->value)
                ->exists();

            if ($invoice->status === InvoiceStatus::AwaitingConfirmation && ! $stillPending) {
                $invoice->forceFill(['status' => InvoiceStatus::Sent])->save();
            }

            $this->invoices->log($invoice, 'proof_rejected', $admin, [
                'proof_id' => $proof->id,
                'reason' => $reason,
            ]);

            $this->notifier->proofRejected($invoice, $reason);

            return $proof->refresh();
        });
    }
}
