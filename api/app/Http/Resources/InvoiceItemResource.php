<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'unit_price_net' => (float) $this->unit_price_net,
            'discount_percent' => (float) $this->discount_percent,
            'vat_rate' => (float) $this->vat_rate,
            'line_total_net' => (float) $this->line_total_net,
            'line_total_gross' => (float) $this->line_total_gross,
        ];
    }
}
