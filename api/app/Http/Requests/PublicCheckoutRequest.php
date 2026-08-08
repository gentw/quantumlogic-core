<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublicCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public by design; throttled at the route
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            // Used only when this email is new. An existing account's password
            // is never touched here — otherwise anyone knowing a client's email
            // could take the account over from a public, unauthenticated form.
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'company' => ['nullable', 'string', 'max:255'],
            'vat_id' => ['nullable', 'string', 'max:20'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'services' => ['required', 'array', 'min:1'],
            'services.*.id' => ['required', 'integer'],
            'services.*.quantity' => ['nullable', 'numeric', 'min:0.01', 'max:999'],
        ];
    }
}
