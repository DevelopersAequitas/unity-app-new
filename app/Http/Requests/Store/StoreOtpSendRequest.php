<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class StoreOtpSendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purpose' => 'required|string|in:ORDER,LOGIN,VERIFICATION',
            'quote_id' => 'nullable|uuid',
        ];
    }
}
