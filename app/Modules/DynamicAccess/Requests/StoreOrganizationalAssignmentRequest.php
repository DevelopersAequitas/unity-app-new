<?php

declare(strict_types=1);

namespace App\Modules\DynamicAccess\Requests;

use App\Shared\Authorization\AccessConstants;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationalAssignmentRequest extends FormRequest
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
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'role_id' => ['nullable', 'string', 'max:100'],
            'role_key' => ['nullable', 'string', 'max:100'],
            'scope_type' => ['nullable', 'string', Rule::in(array_keys(AccessConstants::SCOPE_TYPES))],
            'circle_id' => ['nullable', 'uuid', 'exists:circles,id'],
            'district_id' => ['nullable', 'uuid'],
            'industry_id' => ['nullable', 'uuid'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'committee_key' => ['nullable', 'string', 'max:100'],
            'title_override' => ['nullable', 'string', 'max:150'],
            'is_primary' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

