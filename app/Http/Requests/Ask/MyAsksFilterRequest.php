<?php

declare(strict_types=1);

namespace App\Http\Requests\Ask;

use Illuminate\Foundation\Http\FormRequest;

class MyAsksFilterRequest extends FormRequest
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
            'status' => ['nullable', 'string', 'in:all,open,in_progress,fulfilled,expired,closed,draft,published,cancelled,archived'],
            'flow' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'view' => ['nullable', 'string', 'in:legacy,peer'],
        ];
    }
}
