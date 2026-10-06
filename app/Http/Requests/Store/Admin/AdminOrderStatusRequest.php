<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|string|in:PROCESSING,PACKED,SHIPPED,OUT_FOR_DELIVERY,DELIVERED,READY_FOR_PICKUP,CANCELLED',
            'note' => 'nullable|string|max:500',
        ];
    }
}
