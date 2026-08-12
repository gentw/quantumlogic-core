<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Constrained to the configured list rather than any two-letter code —
     * an unsupported value would silently fall back on every later request.
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', Rule::in(config('locale.supported', ['en']))],
        ];
    }
}
