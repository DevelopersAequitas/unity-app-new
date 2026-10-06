<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PendingRequestApproveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', 'max:50'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'circle_id' => ['nullable', 'uuid'],
            'membership_plan_id' => ['nullable', 'uuid'],
            'membership_starts_at' => ['nullable', 'date'],
            'membership_ends_at' => ['nullable', 'date', 'after_or_equal:membership_starts_at'],
            'coins_awarded' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
