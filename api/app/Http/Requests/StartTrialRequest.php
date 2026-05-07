<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartTrialRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        return [
            'package_id' => ['required', 'exists:packages,id'],

            'payment_method' => [
                'required',
                Rule::in(['cc', 'paypal', 'bank_transfer']),
            ],

            'payment_token' => [
                'required',
                'string',
                'min:10',
                'max:255',
            ],
        ];
    }

}
