<?php

declare(strict_types=1);

namespace App\Http\Requests\Ask;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAskResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'response_type' => [
                'required',
                'string',
                'in:can_help_directly,can_introduce_peer,know_someone,not_relevant',
            ],
            'message' => ['nullable', 'string'],
            'introduced_user_id' => [
                'required_if:response_type,can_introduce_peer',
                'nullable',
                'string',
            ],
            'contact' => [
                'required_if:response_type,know_someone',
                'nullable',
                'array',
            ],
            'contact.full_name' => [
                'required_if:response_type,know_someone',
                'nullable',
                'string',
                'max:255',
            ],
            'contact.company_name' => ['nullable', 'string', 'max:255'],
            'contact.designation' => ['nullable', 'string', 'max:255'],
            'contact.email' => ['nullable', 'email', 'max:255'],
            'contact.phone' => ['nullable', 'string', 'max:50'],
            'contact.alternate_phone' => ['nullable', 'string', 'max:50'],
            'contact.notes' => ['nullable', 'string'],
            'contact.metadata' => ['nullable', 'array'],
        ];
    }
}
