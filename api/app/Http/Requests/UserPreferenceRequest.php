<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

class UserPreferenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true; // Set to false if you want to restrict access based on user roles/permissions
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'email_news_and_updates' => 'required|boolean', // Accepts 1 or 0
            'email_tips_and_tutorials' => 'required|boolean',
            'email_my_tickets' => 'required|boolean',
            'email_invoices' => 'required|boolean',
            'email_reminders' => 'required|boolean',
            'push_my_tickets' => 'required|boolean',
            'push_my_comments' => 'required|boolean',
            'push_reminders' => 'required|boolean',
            'push_invoices' => 'required|boolean',
        ];
    }

    /**
     * Get the custom messages for validation errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'in:1,0' => 'Duhet te jete enable/disable.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new ValidationException($validator, response()->json([
            'status' => 'error',
            'errors' => $validator->errors()
        ], 422));
    }
}