<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class FacebookLoginRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $nullableFields = [
            'token_type',
            'nonce',
            'facebook_id',
            'email',
            'name',
            'first_name',
            'last_name',
            'photo_url',
            'dob',
            'location',
            'address',
            'device_token',
            'fcm_token',
            'push_token',
            'platform',
            'device_type',
            'device_id',
            'app_version',
        ];

        $updates = [];
        foreach ($nullableFields as $field) {
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
            'access_token' => ['required', 'string'],
            'token_type' => ['sometimes', 'nullable', 'string', 'in:classic,limited'],
            'nonce' => ['sometimes', 'nullable', 'string', 'max:512'],
            'facebook_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'first_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'photo_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'dob' => ['sometimes', 'nullable', 'string', 'max:32'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
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
