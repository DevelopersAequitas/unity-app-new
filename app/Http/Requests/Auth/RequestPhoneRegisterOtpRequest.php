<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RequestPhoneRegisterOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone') ?? $this->input('mobile');
        if (filled($phone)) {
            $this->merge([
                'phone' => trim((string) $phone),
                'mobile' => trim((string) $phone),
            ]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string'],
            'mobile' => ['nullable', 'string'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'channel' => ['nullable', 'string', 'in:whatsapp'],
        ];
    }
}
