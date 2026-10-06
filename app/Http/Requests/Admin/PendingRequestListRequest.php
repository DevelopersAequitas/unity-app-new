<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PendingRequestListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', 'in:all,circle_join_request,circle_join,circle,membership_upgrade,membership,proof,proofs,certification,coin_claim,impact'],
            'status' => ['nullable', 'string', 'max:50'],
            'search' => ['nullable', 'string', 'max:255'],
            'circle_id' => ['nullable', 'uuid'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'string', 'in:created_at,requested_at'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}
