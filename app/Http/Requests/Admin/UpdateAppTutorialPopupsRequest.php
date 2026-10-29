<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAppTutorialPopupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'master_tutorials_enabled' => ['nullable'],
            'steps' => ['required', 'array'],
            'steps.*.is_enabled' => ['nullable'],
            'steps.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}

