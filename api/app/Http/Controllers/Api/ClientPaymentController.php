<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientPaymentController extends Controller
{
    /**
     * Polling target for the browser return page. The webhook does the
     * settling; the SPA just watches for it here.
     */
    public function status(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 403);

        $invoice = $payment->invoice;

        return response()->json([
            'payment_status' => $payment->status->value,
            'invoice_status' => $invoice->status->value,
            'amount_due' => (float) $invoice->amount_due,
        ]);
    }
}
