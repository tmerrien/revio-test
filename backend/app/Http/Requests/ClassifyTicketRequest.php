<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Classify Ticket Request
 *
 * Validates incoming ticket classification requests
 */
class ClassifyTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // No authentication required for this demo
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ticket_text' => [
                'required',
                'string',
                'min:10',
                'max:2000',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ticket_text.required' => 'Ticket text is required',
            'ticket_text.string' => 'Ticket text must be a string',
            'ticket_text.min' => 'Ticket text must be at least 10 characters',
            'ticket_text.max' => 'Ticket text cannot exceed 2000 characters',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'ticket_text' => 'ticket text',
        ];
    }
}
