<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublicSupportTicketRequest extends FormRequest
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
            'contact_name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'category' => ['nullable', 'string', 'max:100'],
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
            'subject' => ['required', 'string', 'min:5', 'max:255'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'media' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,mp4,mov', 'max:20480'],
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
            'contact_name.required' => 'Please provide your full name.',
            'contact_name.min' => 'Your name must be at least 2 characters.',
            'email.required' => 'Please provide a valid email address so we can reply to you.',
            'email.email' => 'Please provide a valid email address.',
            'subject.required' => 'Please enter a brief subject for your request.',
            'subject.min' => 'Subject must be at least 5 characters.',
            'description.required' => 'Please describe your inquiry or issue.',
            'description.min' => 'Please provide more details (minimum 10 characters).',
            'media.max' => 'The attached file may not be greater than 20 MB.',
            'media.mimes' => 'Allowed attachment formats are JPG, PNG, WEBP, PDF, MP4, and MOV.',
        ];
    }
}
