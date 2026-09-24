<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SocialLoginRequest extends FormRequest
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'in:google,facebook,linkedin'],
            'token' => ['required', 'string'],
            'device_token' => ['nullable', 'string'],
            'fcm_token' => ['nullable', 'string'],
            'push_token' => ['nullable', 'string'],
            'platform' => ['nullable', 'string', 'max:50'],
            'device_type' => ['nullable', 'string', 'max:50'],
            'device_id' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'timezone' => ['nullable', 'string', 'max:100'],
        ];
    }
}
