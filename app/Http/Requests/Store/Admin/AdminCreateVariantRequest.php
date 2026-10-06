<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminCreateVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sku' => 'required|string|max:150|unique:product_variants,sku',
            'name' => 'required|string|max:300',
            'attributes' => 'nullable|array',
            'coin_price' => 'nullable|integer|min:0',
            'stock_qty' => 'required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'allow_backorder' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ];
    }
}
