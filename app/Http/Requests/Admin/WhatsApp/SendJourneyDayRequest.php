<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class SendJourneyDayRequest extends FormRequest
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
            'override_phone' => ['nullable', 'string', 'min:8', 'max:20'],
            'force' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'override_phone.min' => 'Override phone number must be at least 8 digits.',
            'override_phone.max' => 'Override phone number must not exceed 20 digits.',
        ];
    }
}
