<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class StoreMembershipRenewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => 'nullable|uuid',
            'product_id' => 'nullable|uuid',
            'coins' => 'nullable|integer|min:1',
            'price_coins' => 'nullable|integer|min:1',
            'duration_months' => 'nullable|integer|min:1',
        ];
    }
}
