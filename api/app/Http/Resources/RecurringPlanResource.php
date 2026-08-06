<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecurringPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_name' => $this->whenLoaded('service', fn () => $this->service?->name),
            'interval' => $this->interval,
            'amount_net' => (float) $this->amount_net,
            'vat_rate' => (float) $this->vat_rate,
            'state' => $this->state->value,
            'current_period_end' => $this->current_period_end?->toDateString(),
            'next_charge_at' => $this->next_charge_at?->toDateString(),
            'has_payment_method' => $this->payment_method_id !== null,
        ];
    }
}
