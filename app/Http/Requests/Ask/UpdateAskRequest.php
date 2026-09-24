<?php

declare(strict_types=1);

namespace App\Http\Requests\Ask;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAskRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'max:255'],
            'answers' => ['nullable', 'array'],
            'answers.*.field_key' => ['required_with:answers', 'string', 'max:100'],
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
