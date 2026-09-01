<?php

declare(strict_types=1);

namespace App\Http\Requests\Leader;

use Illuminate\Foundation\Http\FormRequest;

class LeaderSendOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('email_or_phone')) {
            $identifier = $this->input('email') ?? $this->input('phone') ?? $this->input('mobile');
            if ($identifier !== null) {
                $this->merge([
                    'email_or_phone' => (string) $identifier,
                ]);
            }
        }
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'email_or_phone' => ['required', 'string'],
            'email' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
        ];
    }
}
