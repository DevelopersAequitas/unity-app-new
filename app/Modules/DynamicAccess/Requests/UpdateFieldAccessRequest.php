<?php

declare(strict_types=1);

namespace App\Modules\DynamicAccess\Requests;

use App\Shared\Authorization\AccessConstants;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFieldAccessRequest extends FormRequest
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
            'rules' => ['required', 'array'],
            'rules.*.resource_key' => ['required', 'string', Rule::in(array_keys(AccessConstants::RESOURCE_KEYS))],
            'rules.*.field_key' => ['required', 'string', 'max:100'],
            'rules.*.can_view' => ['required', 'boolean'],
            'rules.*.can_edit' => ['nullable', 'boolean'],
            'rules.*.mask_strategy' => ['nullable', 'string', Rule::in(array_keys(AccessConstants::MASK_STRATEGIES))],
            'rules.*.metadata' => ['nullable', 'array'],
        ];
    }
}

