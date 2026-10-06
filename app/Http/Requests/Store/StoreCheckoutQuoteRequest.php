<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class StoreCheckoutQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_mode' => 'required|in:DELIVERY,PICKUP',
            'address_id' => 'required_if:delivery_mode,DELIVERY|nullable|uuid',
            'pickup_point_id' => 'required_if:delivery_mode,PICKUP|nullable|uuid',
        ];
    }
}
