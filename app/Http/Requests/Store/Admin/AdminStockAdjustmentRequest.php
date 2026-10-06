<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity_change' => 'required|integer|not_in:0',
            'reason' => 'required|string|max:100',
            'note' => 'nullable|string|max:500',
        ];
    }
}
