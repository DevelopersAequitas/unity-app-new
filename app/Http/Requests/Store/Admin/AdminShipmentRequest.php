<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'courier' => 'required|string|max:100',
            'courier_service' => 'nullable|string|max:100',
            'awb' => 'required|string|max:100',
            'tracking_url' => 'nullable|string|url',
        ];
    }
}
