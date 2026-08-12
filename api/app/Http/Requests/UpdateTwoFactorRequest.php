<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

class UpdateTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'current_password' => ['required', 'string'],
        ];
    }

    /**
     * Re-check the password rather than trusting the session.
     *
     * A toggle that turns the second factor off from an already-hijacked
     * session is worse than not having one, because the owner believes they are
     * protected. Checked here rather than with the `current_password` rule so
     * the failure is attached to the field and reads like any other validation
     * error.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = $this->user();

            if (! $user || ! Hash::check((string) $this->input('current_password'), $user->password)) {
                $validator->errors()->add('current_password', __('auth.password'));
            }
        });
    }
}
