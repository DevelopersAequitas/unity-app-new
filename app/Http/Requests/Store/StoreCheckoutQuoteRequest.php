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
            'delivery_mode' => 'nullable|in:DELIVERY,PICKUP',
            'address_id' => 'nullable|uuid',
            'pickup_point_id' => 'nullable|uuid',
        ];
    }
}
