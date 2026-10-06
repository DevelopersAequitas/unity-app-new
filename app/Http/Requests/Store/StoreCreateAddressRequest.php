<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class StoreCreateAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => 'nullable|string|max:100',
            'full_name' => 'required|string|max:300',
            'phone' => 'required|string|regex:/^[0-9+\-\s]{8,20}$/',
            'line1' => 'required|string|max:500',
            'line2' => 'nullable|string|max:500',
            'city' => 'required|string|max:150',
            'state' => 'required|string|max:150',
            'pincode' => 'required|string|regex:/^[0-9]{6}$/',
            'country' => 'nullable|string|max:100',
            'is_default' => 'nullable|boolean',
        ];
    }
}
