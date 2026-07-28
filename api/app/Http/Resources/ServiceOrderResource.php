<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'subtotal_net' => (float) $this->subtotal_net,
            'discount_total' => (float) $this->discount_total,
            'vat_total' => (float) $this->vat_total,
            'total_gross' => (float) $this->total_gross,
            'deposit_percent' => $this->deposit_percent !== null ? (float) $this->deposit_percent : null,
            'started_at' => $this->started_at?->toDateString(),
            'completed_at' => $this->completed_at?->toDateString(),
            'created_at' => $this->created_at?->toDateString(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit' => $item->unit,
                'line_total_gross' => (float) $item->line_total_gross,
            ])),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
        ];
    }
}
