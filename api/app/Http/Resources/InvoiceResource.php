<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'type' => $this->type?->value,
            'status' => $this->status->value,
            'is_overdue' => $this->is_overdue,
            'currency' => $this->currency ?? 'EUR',
            'reference' => $this->reference,
            'terms' => $this->terms,
            'notes' => $this->notes,
            'reverse_charge' => (bool) $this->reverse_charge,
            'subtotal_net' => (float) $this->subtotal_net,
            'discount_total' => (float) $this->discount_total,
            'vat_total' => (float) $this->vat_total,
            'total_gross' => (float) $this->total_gross,
            'amount_paid' => (float) $this->amount_paid,
            'amount_due' => (float) $this->amount_due,
            'issued_at' => $this->issued_at?->toDateString(),
            'due_at' => $this->due_at?->toDateString(),
            'paid_at' => $this->paid_at?->toDateString(),
            'order_number' => $this->whenLoaded('serviceOrder', fn () => $this->serviceOrder?->order_number),
            'billed_to' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => trim($this->user->name.' '.$this->user->surname),
                'email' => $this->user->email,
            ]),
            'account_manager' => $this->whenLoaded('accountManager', fn () => $this->accountManager
                ? trim($this->accountManager->name.' '.$this->accountManager->surname)
                : null),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
