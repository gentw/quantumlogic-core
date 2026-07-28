<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Client-facing billing reads. Drafts are invisible to clients — an invoice
 * exists for them once it has been issued.
 */
class ClientBillingController extends Controller
{
    private const CLIENT_VISIBLE = [
        InvoiceStatus::Sent,
        InvoiceStatus::AwaitingConfirmation,
        InvoiceStatus::Paid,
        InvoiceStatus::Cancelled,
        InvoiceStatus::Unpaid,
        InvoiceStatus::Failed,
    ];

    /** The KPI strip: last invoice, next due date, open balance. */
    public function summary(Request $request): JsonResponse
    {
        $base = $this->visibleInvoices($request);

        $lastInvoice = (clone $base)->whereNotNull('sent_at')->latest('sent_at')->first();

        $nextDue = (clone $base)
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::AwaitingConfirmation->value])
            ->where('amount_due', '>', 0)
            ->orderBy('due_at')
            ->first();

        return response()->json([
            'last_invoice_total' => (float) ($lastInvoice?->total_gross ?? 0),
            'last_invoice_number' => $lastInvoice?->invoice_number,
            'next_due_at' => $nextDue?->due_at?->toDateString(),
            'outstanding_balance' => (float) (clone $base)->sum('amount_due'),
            'open_invoices' => (clone $base)->where('amount_due', '>', 0)->count(),
        ]);
    }

    /** Server-side paginated list with status filter and number search. */
    public function index(Request $request): JsonResponse
    {
        $invoices = $this->visibleInvoices($request)
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('search'), fn ($q, $term) => $q->where(function ($q) use ($term) {
                $q->where('invoice_number', 'like', "%{$term}%")
                    ->orWhere('reference', 'like', "%{$term}%");
            }))
            ->with('serviceOrder:id,order_number')
            ->latest('sent_at')
            ->paginate(min((int) $request->query('per_page', 10), 50));

        return InvoiceResource::collection($invoices)->response();
    }

    public function show(Request $request, Invoice $invoice): InvoiceResource
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
        abort_if($invoice->status === InvoiceStatus::Draft, 404);

        return new InvoiceResource($invoice->load(['items', 'serviceOrder:id,order_number']));
    }

    private function visibleInvoices(Request $request)
    {
        return Invoice::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', array_map(fn ($s) => $s->value, self::CLIENT_VISIBLE));
    }
}
