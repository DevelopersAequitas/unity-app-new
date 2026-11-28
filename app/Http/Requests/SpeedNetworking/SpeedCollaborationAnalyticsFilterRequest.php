<?php

declare(strict_types=1);

namespace App\Http\Requests\SpeedNetworking;

use Illuminate\Foundation\Http\FormRequest;

class SpeedCollaborationAnalyticsFilterRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'string', 'max:50'],
            'live_status' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'in:all,connection,waiting,skip,completed,reward'],
            'round_number' => ['nullable', 'integer', 'min:0', 'max:100'],
            'peer_id' => ['nullable', 'uuid'],
            'qualifying_only' => ['nullable', 'boolean'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'event_ids' => ['nullable', 'array'],
            'event_ids.*' => ['uuid'],
            'report_type' => ['nullable', 'string', 'in:full,summary,attendees,connections,coins,life_impact,peer_performance,participation,live_activity'],
            'sort_by' => ['nullable', 'string', 'max:50'],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('qualifying_only') && is_string($this->input('qualifying_only'))) {
            $val = filter_var($this->input('qualifying_only'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($val !== null) {
                $this->merge(['qualifying_only' => $val]);
            }
        }
    }
}
