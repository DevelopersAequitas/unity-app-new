<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_id' => 'required|string|max:255',
            'platform' => 'nullable|string|in:ANDROID,IOS,WEB',
            'push_token' => 'required|string|max:500',
            'app_version' => 'nullable|string|max:50',
        ];
    }
}
