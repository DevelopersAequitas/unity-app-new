<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Finance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAcquisitionChannelRequest extends FormRequest
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
            'category_code' => ['sometimes', 'string', 'max:10'],
            'name' => ['sometimes', 'string', 'max:150'],
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

