<?php

declare(strict_types=1);

namespace App\Modules\DynamicAccess\Requests;

use App\Shared\Authorization\AccessConstants;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'default_scope_type' => ['nullable', 'string', Rule::in(array_keys(AccessConstants::SCOPE_TYPES))],
            'hierarchy_level' => ['nullable', 'integer', 'min:1', 'max:999'],
            'hierarchy_depth' => ['nullable', 'integer', 'min:0', 'max:20'],
            'is_leadership_role' => ['nullable', 'boolean'],
            'is_admin_panel_role' => ['nullable', 'boolean'],
            'is_assignable' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ];
    }
}

