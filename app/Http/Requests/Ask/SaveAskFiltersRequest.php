<?php

declare(strict_types=1);

namespace App\Http\Requests\Ask;

use Illuminate\Foundation\Http\FormRequest;

class SaveAskFiltersRequest extends FormRequest
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
            'industry' => ['nullable'],
            'geography' => ['nullable'],
            'business_stage' => ['nullable'],
            'timeline' => ['nullable'],
            'expected_outcome' => ['nullable', 'string'],
            'filters' => ['nullable', 'array'],
        ];
    }
}
