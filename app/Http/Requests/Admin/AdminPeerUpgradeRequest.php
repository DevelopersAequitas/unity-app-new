<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminPeerUpgradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan' => ['sometimes', 'nullable', 'string', 'max:100'],
            'plan_code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'duration_months' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:120'],
            'membership_start_date' => ['sometimes', 'nullable', 'date'],
            'membership_end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:membership_start_date'],
            'membership_starts_at' => ['sometimes', 'nullable', 'date'],
            'membership_ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:membership_starts_at'],
            'target_status' => ['sometimes', 'nullable', 'string', 'max:100'],
            'amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'attachments' => ['sometimes', 'nullable', 'array'],
            'attachments.*.id' => ['required_with:attachments', 'string'],
            'attachments.*.url' => ['required_with:attachments', 'url'],
            'attachments.*.mime_type' => ['nullable', 'string', 'max:255'],
            'attachments.*.original_name' => ['nullable', 'string', 'max:255'],
            'attachments.*.s3_key' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
