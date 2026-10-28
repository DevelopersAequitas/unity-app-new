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
            'phone' => ['nullable', 'string', 'max:20'],
            'mobile_number' => ['nullable', 'string', 'max:20'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'redirect_uri' => ['nullable', 'string'],
            'password' => ['nullable', 'string', 'min:6'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'main_business_category_id' => ['nullable'],
            'business_category_id' => ['nullable'],
            'city' => ['nullable', 'string', 'max:100'],
            'city_id' => ['nullable'],
            'dob' => ['nullable', 'string'],
            'device_token' => ['nullable', 'string'],
            'fcm_token' => ['nullable', 'string'],
            'push_token' => ['nullable', 'string'],
            'platform' => ['nullable', 'string', 'max:50'],
            'device_type' => ['nullable', 'string', 'max:50'],
            'device_id' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'referral_code' => ['nullable', 'string', 'max:32'],
            'profile_photo_url' => ['nullable', 'string'],
            'avatar' => ['nullable', 'string'],
            'profile_photo_id' => ['nullable', 'string'],
        ];
    }
}
