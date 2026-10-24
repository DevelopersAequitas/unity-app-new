<?php

declare(strict_types=1);

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class AiCategorySuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'business_description' => ['nullable', 'string', 'max:5000'],
            'industry' => ['nullable', 'string', 'max:255'],
            'linkedin_headline' => ['nullable', 'string', 'max:255'],
        ];
    }
}
