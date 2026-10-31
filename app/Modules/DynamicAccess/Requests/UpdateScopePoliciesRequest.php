<?php

declare(strict_types=1);

namespace App\Modules\DynamicAccess\Requests;

use App\Shared\Authorization\AccessConstants;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScopePoliciesRequest extends FormRequest
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
            'policies' => ['required', 'array', 'min:1'],
            'policies.*.resource_key' => ['required', 'string', Rule::in(array_keys(AccessConstants::RESOURCE_KEYS))],
            'policies.*.scope_type' => ['required', 'string', Rule::in(array_keys(AccessConstants::SCOPE_TYPES))],
            'policies.*.include_assigned_circles' => ['nullable', 'boolean'],
            'policies.*.include_child_circles' => ['nullable', 'boolean'],
            'policies.*.include_same_district' => ['nullable', 'boolean'],
            'policies.*.include_same_industry' => ['nullable', 'boolean'],
            'policies.*.include_all_peers_in_scope' => ['nullable', 'boolean'],
            'policies.*.allow_cross_circle_read' => ['nullable', 'boolean'],
            'policies.*.allow_cross_circle_write' => ['nullable', 'boolean'],
            'policies.*.allow_export' => ['nullable', 'boolean'],
            'policies.*.custom_filter_config' => ['nullable', 'array'],
        ];
    }
}

