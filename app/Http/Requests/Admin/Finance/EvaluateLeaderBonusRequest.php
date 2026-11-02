<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Finance;

use Illuminate\Foundation\Http\FormRequest;

class EvaluateLeaderBonusRequest extends FormRequest
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
            'leader_tenure_id' => ['required', 'uuid', 'exists:finance_leader_tenures,id'],
            'metrics' => ['nullable', 'array'],
            'metrics.new_members_count' => ['nullable', 'integer', 'min:0'],
            'metrics.net_member_strength' => ['nullable', 'integer', 'min:0'],
            'metrics.circles_launched_count' => ['nullable', 'integer', 'min:0'],
            'metrics.avg_members_per_circle' => ['nullable', 'numeric', 'min:0'],
            'metrics.meeting_attendance_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'metrics.meetings_evaluated_count' => ['nullable', 'integer', 'min:0'],
            'metrics.annual_renewal_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'metrics.documented_collaborations_count' => ['nullable', 'integer', 'min:0'],
            'metrics.category_disputes_count' => ['nullable', 'integer', 'min:0'],
            'metrics.post_handover_consistent_days' => ['nullable', 'integer', 'min:0'],
        ];
    }
}

