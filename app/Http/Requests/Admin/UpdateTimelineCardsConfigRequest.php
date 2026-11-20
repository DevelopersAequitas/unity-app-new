<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTimelineCardsConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'profile_completion_enabled' => ['sometimes', 'boolean'],
            'you_can_help_enabled' => ['sometimes', 'boolean'],
            'events_spotlight_enabled' => ['sometimes', 'boolean'],
            'circle_vacancy_enabled' => ['sometimes', 'boolean'],
            'suggested_peers_enabled' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'profile_completion_enabled.boolean' => 'The profile completion enabled field must be true or false.',
            'you_can_help_enabled.boolean' => 'The you can help enabled field must be true or false.',
            'events_spotlight_enabled.boolean' => 'The events spotlight enabled field must be true or false.',
            'circle_vacancy_enabled.boolean' => 'The circle vacancy enabled field must be true or false.',
            'suggested_peers_enabled.boolean' => 'The suggested peers enabled field must be true or false.',
        ];
    }
}
