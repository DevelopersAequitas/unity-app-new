<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => 'required|uuid|exists:orders,id',
            'refund_coins' => 'required|integer|min:1',
            'reason' => 'required|string|max:500',
            'return_id' => 'nullable|uuid|exists:returns,id',
        ];
    }
}
