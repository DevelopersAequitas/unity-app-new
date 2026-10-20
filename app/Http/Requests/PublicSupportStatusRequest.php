<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublicSupportStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
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
            'ticket_number' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
        ];
    }

    /**
     * Custom validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ticket_number.required' => 'Please enter your ticket number (e.g. SUP-20261020-0001).',
            'email.required' => 'Please enter the email address used when submitting the ticket.',
            'email.email' => 'Please enter a valid email address.',
        ];
    }
}
