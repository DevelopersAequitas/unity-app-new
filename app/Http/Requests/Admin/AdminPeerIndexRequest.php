<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminPeerIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'state' => ['sometimes', 'nullable', 'string', 'max:100'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'city_id' => ['sometimes', 'nullable', 'uuid'],
            'industry' => ['sometimes', 'nullable', 'string', 'max:150'],
            'industry_id' => ['sometimes', 'nullable', 'string'],
            'sub_category' => ['sometimes', 'nullable', 'string', 'max:150'],
            'subcategory' => ['sometimes', 'nullable', 'string', 'max:150'],
            'circle_id' => ['sometimes', 'nullable', 'uuid'],
            'peer_type' => ['sometimes', 'nullable', 'string', 'in:pro,paid,global,circle,multi_circle,free_trial,trial,free,sponsored,all,global_peer,circle_peer,multi_circle_peer,only_unity_peer'],
            'membership_status' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', 'string', 'in:active,inactive,suspended,expired,pending,awaiting_review,all'],
            'is_active' => ['sometimes', 'nullable'],
            'role' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sort_by' => ['sometimes', 'nullable', 'string', 'in:created_at,name,first_name,display_name,email,coins,coins_balance,life_impacted,life_impacted_count,last_login_at,last_login,membership_expiry,expiry'],
            'sort_dir' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
