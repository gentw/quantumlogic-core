<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminInvoiceDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route carries the admin middleware
    }

    public function rules(): array
    {
        return [
            'reference' => ['nullable', 'string', 'max:255'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'due_at' => ['nullable', 'date'],
            'account_manager_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
