<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentProofRequest;
use App\Models\Invoice;
use App\Services\BankTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankTransferController extends Controller
{
    public function __construct(
        private readonly BankTransferService $bankTransfer,
    ) {}

    /** Beneficiary details + EPC QR for the invoice's open amount. */
    public function bankDetails(Request $request, Invoice $invoice): JsonResponse
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
        abort_unless(in_array($invoice->status, [InvoiceStatus::Sent, InvoiceStatus::AwaitingConfirmation, InvoiceStatus::Unpaid], true), 422, 'This invoice cannot be paid.');

        return response()->json($this->bankTransfer->bankDetails($invoice));
    }

    /** Client uploads the bank slip; invoice waits for admin review. */
    public function uploadProof(PaymentProofRequest $request, Invoice $invoice): JsonResponse
    {
        $proof = $this->bankTransfer->submitProof(
            $invoice,
            $request->file('file'),
            $request->validated('note'),
            $request->user(),
        );

        return response()->json([
            'proof_id' => $proof->id,
            'status' => $proof->status->value,
            'invoice_status' => $invoice->refresh()->status->value,
        ], 201);
    }
}
