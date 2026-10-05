<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Categories;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BulkDestroyCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'level2_ids' => ['nullable', 'array'],
            'level2_ids.*' => ['integer'],
            'level3_ids' => ['nullable', 'array'],
            'level3_ids.*' => ['integer'],
            'level4_ids' => ['nullable', 'array'],
            'level4_ids.*' => ['integer'],
        ];
    }
}
