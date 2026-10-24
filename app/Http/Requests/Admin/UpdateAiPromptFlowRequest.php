<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAiPromptFlowRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_-]+$/i'],
            'description' => ['nullable', 'string', 'max:1000'],
            'model' => ['required', 'string', 'max:100'],
            'system_instruction' => ['nullable', 'string'],
            'prompt_template' => ['required', 'string'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
