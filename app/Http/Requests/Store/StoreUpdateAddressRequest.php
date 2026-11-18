<?php

declare(strict_types=1);

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class StoreUpdateAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $fullName = $this->input('full_name') ?? $this->input('name');
        if (filled($fullName)) {
            $this->merge([
                'full_name' => $fullName,
                'name' => $fullName,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'label' => 'nullable|string|max:100',
            'full_name' => 'sometimes|required|string|max:300',
            'name' => 'sometimes|required|string|max:300',
            'phone' => 'sometimes|required|string|regex:/^[0-9+\-\s]{8,20}$/',
            'line1' => 'sometimes|required|string|max:500',
            'line2' => 'nullable|string|max:500',
            'landmark' => 'nullable|string|max:500',
            'city' => 'sometimes|required|string|max:150',
            'state' => 'sometimes|required|string|max:150',
            'pincode' => 'sometimes|required|string|regex:/^[0-9]{6}$/',
            'country' => 'nullable|string|max:100',
            'is_default' => 'nullable|boolean',
        ];
    }
}
