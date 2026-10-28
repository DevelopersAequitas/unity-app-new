<?php

declare(strict_types=1);

namespace App\Http\Requests\SpeedNetworking;

use Illuminate\Foundation\Http\FormRequest;

class CreateSpeedNetworkingEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'id' => ['nullable', 'uuid'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'banner_url' => ['nullable', 'string', 'max:500'],
            'scheduled_start_time' => ['nullable', 'date'],
            'scheduled_end_time' => ['nullable', 'date', 'after_or_equal:scheduled_start_time'],
            'actual_start_time' => ['nullable', 'date'],
            'actual_end_time' => ['nullable', 'date'],
            'round_duration_seconds' => ['nullable', 'integer', 'min:10', 'max:3600'],
            'break_duration_seconds' => ['nullable', 'integer', 'min:5', 'max:1800'],
            'total_rounds' => ['nullable', 'integer', 'min:1', 'max:100'],
            'ticket_price' => ['nullable', 'numeric', 'min:0'],
            'is_paid' => ['nullable', 'boolean'],
            'is_free' => ['nullable', 'boolean'],
            'currency' => ['nullable', 'string', 'max:10'],
            'circle_id' => ['nullable', 'uuid'],
            'circle_restricted' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'in:draft,scheduled,lobby_open,in_progress,paused,completed'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
