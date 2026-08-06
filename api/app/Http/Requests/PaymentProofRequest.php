<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class PaymentProofRequest extends FormRequest
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
            // mimetypes sniffs the real content via fileinfo — an .exe
            // renamed to .pdf fails here regardless of its extension.
            'file' => ['required', 'file', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:10240'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
