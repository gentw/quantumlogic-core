<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** All payments across clients — the admin money ledger. */
class AdminPaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $payments = Payment::query()
            ->with(['invoice:id,invoice_number', 'user:id,name,surname,email'])
            ->when($request->query('provider'), fn ($q, $provider) => $q->where('provider', $provider))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('search'), fn ($q, $term) => $q->where(function ($q) use ($term) {
                $q->where('provider_payment_id', 'like', "%{$term}%")
                    ->orWhereHas('invoice', fn ($q) => $q->where('invoice_number', 'like', "%{$term}%"))
                    ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$term}%")
                        ->orWhere('surname', 'like', "%{$term}%"));
            }))
            ->latest('id')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        $payments->getCollection()->transform(fn ($p) => [
            'id' => $p->id,
            'provider' => $p->provider->value,
            'status' => $p->status->value,
            'amount' => (float) $p->amount,
            'refunded_amount' => (float) $p->refunded_amount,
            'method' => trim(($p->method_brand ?? '').' '.($p->method_last4 ? '•••• '.$p->method_last4 : '')) ?: null,
            'invoice_id' => $p->invoice?->id,
            'invoice_number' => $p->invoice?->invoice_number,
            'client' => $p->user ? trim($p->user->name.' '.$p->user->surname) : null,
            'paid_at' => $p->paid_at?->toDateTimeString(),
            'created_at' => $p->created_at?->toDateTimeString(),
        ]);

        return response()->json($payments);
    }
}
