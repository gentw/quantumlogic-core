<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route carries the admin middleware
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.service_id' => ['nullable', 'integer', 'exists:services,id'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            'lines.*.unit' => ['nullable', 'string', 'max:16'],
            'lines.*.unit_price_net' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'lines.*.vat_rate' => ['nullable', 'numeric', 'between:0,100'],
            'deposit_percent' => ['nullable', 'numeric', 'between:1,100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'account_manager_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
