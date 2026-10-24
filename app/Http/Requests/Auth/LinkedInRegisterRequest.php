<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LinkedInRegisterRequest extends FormRequest
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
            'code' => ['required_without:token', 'nullable', 'string'],
            'token' => ['required_without:code', 'nullable', 'string'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'redirect_uri' => ['nullable', 'string'],
            'password' => ['nullable', 'string', 'min:8'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'city_id' => ['nullable', 'uuid'],
            'device_token' => ['nullable', 'string'],
            'fcm_token' => ['nullable', 'string'],
            'push_token' => ['nullable', 'string'],
            'platform' => ['nullable', 'string', 'max:50'],
            'device_type' => ['nullable', 'string', 'max:50'],
            'device_id' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'referral_code' => ['nullable', 'string', 'max:32'],
        ];
    }
}
