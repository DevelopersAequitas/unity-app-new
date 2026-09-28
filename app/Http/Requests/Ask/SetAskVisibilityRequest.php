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
            'visibility_type' => ['required', 'string', 'in:all_peers,district,circle'],
            'district_id' => ['nullable', 'string'],
            'circle_id' => ['nullable', 'string'],
        ];
    }
}
