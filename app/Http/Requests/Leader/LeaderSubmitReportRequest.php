<?php

declare(strict_types=1);

namespace App\Http\Requests\Leader;

use Illuminate\Foundation\Http\FormRequest;

class LeaderSubmitReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'circle_id' => ['nullable', 'string'],
            'report_type' => ['required', 'string'],
            'period' => ['nullable', 'string'],
            'attendance_percentage' => ['nullable', 'numeric'],
            'deals_closed_value' => ['nullable', 'string'],
            'total_revenue' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'summary_text' => ['nullable', 'string'],
            'highlights' => ['nullable', 'string'],
            'challenges_faced' => ['nullable', 'string'],
            'action_items' => ['nullable', 'string'],
            'included_sections' => ['nullable', 'array'],
            'peers_roster' => ['nullable', 'array'],
        ];
    }
}
