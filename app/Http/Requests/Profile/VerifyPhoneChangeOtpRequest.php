<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPhoneChangeOtpRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $rawPhone = $this->input('phone', $this->input('mobile'));
        if (is_string($rawPhone)) {
            $this->merge([
                'phone' => trim($rawPhone),
            ]);
        }

        if ($this->has('otp') && is_string($this->input('otp'))) {
            $this->merge([
                'otp' => trim($this->input('otp')),
            ]);
        }
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'min:8', 'max:30'],
            'mobile' => ['sometimes', 'nullable', 'string', 'min:8', 'max:30'],
            'country_code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'otp' => ['required', 'string', 'digits:6'],
            'channel' => ['sometimes', 'nullable', 'string', 'in:whatsapp'],
        ];
    }
}
