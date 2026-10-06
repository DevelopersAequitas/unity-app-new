<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|uuid',
            'variant_id' => 'nullable|uuid',
            'quantity' => 'required|integer|min:1|max:100',
        ];
    }
}
