<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ToggleBonusRoleConfigRequest extends FormRequest
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
            'bonus_rule_id' => ['required', 'uuid', 'exists:finance_bonus_rules,id'],
            'role_code' => ['required', 'string', 'max:50'],
            'is_active' => ['required', 'boolean'],
            'activated_at' => ['nullable', 'date'],
            'override_bonus_amount_inr' => ['nullable', 'numeric', 'min:0'],
            'override_target_members' => ['nullable', 'integer', 'min:1'],
            'override_timeframe_days' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

