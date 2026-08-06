<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartTrialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'payment_method' => ['required', Rule::in(['cc', 'paypal', 'bank_transfer'])],
            'payment_token' => ['required', 'string', 'min:6', 'max:255'],
            'payment_brand' => ['nullable', 'string', 'max:32'],
        ];
    }
}
