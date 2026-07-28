<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class StripePaymentIntentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Invoice|null $invoice */
        $invoice = $this->route('invoice');

        return $invoice && $invoice->user_id === $this->user()?->id;
    }

    public function rules(): array
    {
        return [
            // Partial payments are allowed; the cap is what's still owed.
            'amount' => ['nullable', 'numeric', 'min:0.5'],
        ];
    }
}
