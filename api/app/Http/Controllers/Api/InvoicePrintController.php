<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\BankTransferService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The printable invoice document — §11 UStG content from config/company.php,
 * never hardcoded. The SPA renders it for html2pdf download; mails link it.
 */
class InvoicePrintController extends Controller
{
    public function __construct(
        private readonly BankTransferService $bankTransfer,
    ) {}

    public function client(Request $request, Invoice $invoice): View
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
        abort_if($invoice->status === InvoiceStatus::Draft, 404);

        return $this->render($invoice);
    }

    public function admin(Invoice $invoice): View
    {
        return $this->render($invoice);
    }

    private function render(Invoice $invoice): View
    {
        $invoice->load(['items', 'user']);

        $payable = in_array($invoice->status, [InvoiceStatus::Sent, InvoiceStatus::AwaitingConfirmation, InvoiceStatus::Unpaid], true)
            && (float) $invoice->amount_due > 0;

        return view('billing.invoice', [
            'invoice' => $invoice,
            'client' => $invoice->user,
            'bank' => $payable && config('company.iban') ? $this->bankTransfer->bankDetails($invoice) : null,
        ]);
    }
}
