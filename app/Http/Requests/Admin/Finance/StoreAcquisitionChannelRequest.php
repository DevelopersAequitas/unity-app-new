<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StoreAcquisitionChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_code' => ['required', 'string', 'max:10'],
            'channel_key' => ['required', 'string', 'max:60', 'unique:finance_acquisition_channels,channel_key'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'conditions_list' => ['nullable', 'array'],
            'conditions_list.*' => ['string'],
            'responsibilities_list' => ['nullable', 'array'],
            'responsibilities_list.*' => ['string'],
            'priority_order' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}

