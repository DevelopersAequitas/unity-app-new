<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAiChatbotSettingRequest extends FormRequest
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
            'is_enabled' => ['nullable', 'boolean'],
            'show_floating_button' => ['nullable', 'boolean'],
            'show_app_bar_icon' => ['nullable', 'boolean'],
            'show_home_banner' => ['nullable', 'boolean'],
            'button_title' => ['required', 'string', 'max:100'],
            'button_subtitle' => ['required', 'string', 'max:255'],
            'bot_name' => ['required', 'string', 'max:100'],
            'bot_subtitle' => ['required', 'string', 'max:255'],
            'bot_avatar_url' => ['nullable', 'string', 'max:500'],
            'welcome_title' => ['required', 'string', 'max:255'],
            'welcome_subtitle' => ['nullable', 'string'],
            'empty_history_title' => ['required', 'string', 'max:255'],
            'empty_history_subtitle' => ['required', 'string', 'max:255'],
            'input_placeholder' => ['required', 'string', 'max:255'],
            'thinking_text' => ['required', 'string', 'max:150'],
            'model_name' => ['required', 'string', 'max:100'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:1'],
            'master_system_prompt' => ['required', 'string'],
            'inner_options_json' => ['nullable', 'string'],
            'starter_chips_json' => ['nullable', 'string'],
            'enabled_tools' => ['nullable', 'array'],
            'enabled_tools.*' => ['string'],
        ];
    }
}

