<?php

declare(strict_types=1);

namespace App\Http\Requests\Ask;

use Illuminate\Foundation\Http\FormRequest;

class SaveAskDetailsRequest extends FormRequest
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
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.field_key' => ['required', 'string', 'max:100'],
            'answers.*.value_text' => ['nullable', 'string'],
            'answers.*.value_number' => ['nullable', 'numeric'],
            'answers.*.value_boolean' => ['nullable', 'boolean'],
            'answers.*.value_json' => ['nullable', 'array'],
            'answers.*.option_id' => ['nullable', 'string'],
            'answers.*.option_ids' => ['nullable', 'array'],
            'answers.*.option_ids.*' => ['string'],
            'answers.*.option_group_id' => ['nullable', 'string'],
        ];
    }
}
