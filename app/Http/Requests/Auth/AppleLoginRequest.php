<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class AppleLoginRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $updates = [];

        $resolvedToken = $this->input('identity_token')
            ?? $this->input('id_token')
            ?? $this->input('token');

        if (is_string($resolvedToken)) {
            $trimmedToken = trim($resolvedToken);
            if ($trimmedToken !== '') {
                $updates['identity_token'] = $trimmedToken;
            }
        }

        $nullableStringFields = [
            'authorization_code',
            'code',
            'nonce',
            'email',
            'name',
            'first_name',
            'last_name',
            'device_token',
            'fcm_token',
            'push_token',
            'platform',
            'device_type',
            'device_id',
            'app_version',
        ];

        foreach ($nullableStringFields as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $trimmed = trim((string) $this->input($field));
                $updates[$field] = $trimmed === '' ? null : $trimmed;
            }
        }

        if ($updates !== []) {
            $this->merge($updates);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'identity_token' => ['required', 'string'],
            'authorization_code' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'code' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'nonce' => ['sometimes', 'nullable', 'string', 'max:512'],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'first_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'user' => ['sometimes', 'nullable'],
            'device_token' => ['sometimes', 'nullable', 'string', 'max:512'],
            'fcm_token' => ['sometimes', 'nullable', 'string', 'max:512'],
            'push_token' => ['sometimes', 'nullable', 'string', 'max:512'],
            'platform' => ['sometimes', 'nullable', 'string', 'max:50'],
            'device_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'device_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'app_version' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
