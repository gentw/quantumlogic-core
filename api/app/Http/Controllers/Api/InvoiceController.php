<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function show($id, Request $request)
    {
        $invoice = Invoice::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json([
            'id' => $invoice->id,
            'is_trial' => $invoice->description === 'Starter Trial (7 days)',
            'amount' => $invoice->total,
            'currency' => $invoice->currency,
        ]);
    }
}
