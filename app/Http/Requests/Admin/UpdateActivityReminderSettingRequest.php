<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateActivityReminderSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'threshold_days' => ['required', 'integer', 'min:1', 'max:365'],
            'is_enabled' => ['required', 'boolean'],
            'priority' => ['required', 'integer', 'min:1', 'max:100'],
            'notification_title' => ['required', 'string', 'max:255'],
            'notification_body' => ['required', 'string'],
            'target_screen' => ['required', 'string', 'max:100'],
        ];
    }
}
