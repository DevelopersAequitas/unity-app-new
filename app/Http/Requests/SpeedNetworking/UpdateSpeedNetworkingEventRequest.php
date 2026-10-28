<?php

declare(strict_types=1);

namespace App\Http\Requests\SpeedNetworking;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSpeedNetworkingEventRequest extends FormRequest
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
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'banner_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'scheduled_start_time' => ['sometimes', 'nullable', 'date'],
            'scheduled_end_time' => ['sometimes', 'nullable', 'date'],
            'round_duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:10', 'max:3600'],
            'break_duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:5', 'max:1800'],
            'total_rounds' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'ticket_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'is_paid' => ['sometimes', 'nullable', 'boolean'],
            'is_free' => ['sometimes', 'nullable', 'boolean'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:10'],
            'circle_id' => ['sometimes', 'nullable', 'uuid'],
            'circle_restricted' => ['sometimes', 'nullable', 'boolean'],
            'status' => ['sometimes', 'nullable', 'string', 'in:draft,scheduled,lobby_open,in_progress,paused,completed'],
        ];
    }
}

