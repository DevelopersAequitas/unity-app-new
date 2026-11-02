<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Finance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBonusRuleRequest extends FormRequest
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
            'bonus_category' => ['nullable', 'string', 'max:50'],
            'metric_type' => [
                'nullable',
                'string',
                'in:NEW_MEMBERS_IN_DAYS,NET_MEMBER_STRENGTH,CIRCLES_LAUNCHED_IN_DAYS,MEETING_ATTENDANCE_RATE,ANNUAL_RENEWAL_RATE,DOCUMENTED_COLLABORATIONS,ZERO_CATEGORY_DISPUTES,POST_HANDOVER_CONSISTENCY',
            ],
            'title' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'target_members_count' => ['nullable', 'integer', 'min:1'],
            'target_circles_count' => ['nullable', 'integer', 'min:1'],
            'min_avg_members_per_circle' => ['nullable', 'integer', 'min:1'],
            'target_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'target_meetings_count' => ['nullable', 'integer', 'min:1'],
            'meeting_window_mode' => ['nullable', 'string', 'in:first_n,last_n'],
            'target_collaborations_count' => ['nullable', 'integer', 'min:1'],
            'max_disputes_allowed' => ['nullable', 'integer', 'min:0'],
            'timeframe_days' => ['nullable', 'integer', 'min:1'],
            'bonus_amount_inr' => ['nullable', 'numeric', 'min:0'],
            'reward_perks' => ['nullable', 'string', 'max:255'],
            'exclusivity_group' => ['nullable', 'string', 'max:80'],
            'requires_approval' => ['nullable', 'boolean'],
            'is_once_per_tenure' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}

