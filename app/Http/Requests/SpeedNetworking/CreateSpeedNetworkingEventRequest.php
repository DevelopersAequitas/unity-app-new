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
            'id' => ['nullable', 'uuid', 'exists:speed_networking_events,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'banner_url' => ['nullable', 'string', 'max:255'],
            'scheduled_start_time' => ['nullable', 'date'],
            'round_duration_seconds' => ['nullable', 'integer', 'min:30', 'max:3600'],
            'break_duration_seconds' => ['nullable', 'integer', 'min:5', 'max:600'],
            'ticket_price' => ['nullable', 'numeric', 'min:0'],
            'is_paid' => ['nullable', 'boolean'],
        ];
    }
}
