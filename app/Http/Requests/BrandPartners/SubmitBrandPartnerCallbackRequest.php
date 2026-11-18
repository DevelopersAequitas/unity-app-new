<?php

declare(strict_types=1);

namespace App\Http\Requests\BrandPartners;

use Illuminate\Foundation\Http\FormRequest;

class SubmitBrandPartnerCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $user = $this->user() ?? auth('sanctum')->user();

        $name = trim((string) ($this->input('name') ?? $this->input('full_name') ?? ''));
        if ($name === '' && $user) {
            $name = trim((string) ($user->display_name ?: (($user->first_name ?? '').' '.($user->last_name ?? '')) ?: ($user->name ?? '')));
        }

        $phone = trim((string) ($this->input('phone') ?? $this->input('mobile_number') ?? $this->input('contact_number') ?? ''));
        if ($phone === '' && $user) {
            $phone = trim((string) ($user->phone ?? ''));
        }

        $email = trim((string) ($this->input('email') ?? ''));
        if ($email === '' && $user) {
            $email = trim((string) ($user->email ?? ''));
        }

        $message = trim((string) ($this->input('message') ?? $this->input('requirement') ?? $this->input('notes') ?? ''));

        $this->merge([
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'message' => $message,
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:254'],
            'message' => ['required', 'string', 'max:2000'],
        ];
    }
}
