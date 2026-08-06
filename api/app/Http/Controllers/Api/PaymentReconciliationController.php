<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentProofStatus;
use App\Http\Controllers\Controller;
use App\Models\PaymentProof;
use App\Services\BankTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Admin queue for bank-transfer proofs: list, inspect the file, accept/reject. */
class PaymentReconciliationController extends Controller
{
    public function __construct(
        private readonly BankTransferService $bankTransfer,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', PaymentProofStatus::Pending->value);

        $proofs = PaymentProof::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with(['invoice:id,invoice_number,total_gross,amount_due,status', 'uploader:id,name,surname,email', 'payment:id,amount,status'])
            ->latest()
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($proofs);
    }

    public function accept(Request $request, PaymentProof $proof): JsonResponse
    {
        $proof = $this->bankTransfer->accept($proof, $request->user());

        return response()->json([
            'status' => $proof->status->value,
            'invoice_status' => $proof->invoice->refresh()->status->value,
        ]);
    }

    public function reject(Request $request, PaymentProof $proof): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $proof = $this->bankTransfer->reject($proof, $request->user(), $validated['reason']);

        return response()->json([
            'status' => $proof->status->value,
            'invoice_status' => $proof->invoice->refresh()->status->value,
        ]);
    }

    /** The slip itself — private disk, admin-only, streamed. */
    public function file(PaymentProof $proof): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($proof->file_path), 404);

        return Storage::disk('local')->response(
            $proof->file_path,
            $proof->original_name,
            ['Content-Type' => $proof->mime_type]
        );
    }
}
