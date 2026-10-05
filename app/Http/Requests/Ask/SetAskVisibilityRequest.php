<?php

declare(strict_types=1);

namespace App\Http\Requests\Ask;

use Illuminate\Foundation\Http\FormRequest;

class SetAskVisibilityRequest extends FormRequest
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
            'visibility_type' => ['nullable', 'string'],
            'visibility' => ['nullable', 'string'],
            'district_id' => ['nullable', 'string'],
            'circle_id' => ['nullable', 'string'],
        ];
    }
}
