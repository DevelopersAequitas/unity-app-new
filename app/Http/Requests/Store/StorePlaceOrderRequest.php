<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class StorePlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quote_id' => 'required|uuid',
            'delivery_mode' => 'nullable|in:DELIVERY,PICKUP',
            'address_id' => 'nullable|uuid',
            'pickup_point_id' => 'nullable|uuid',
            'otp_verification_token' => 'nullable|string',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
