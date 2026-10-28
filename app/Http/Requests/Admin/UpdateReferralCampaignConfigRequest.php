<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReferralCampaignConfigRequest extends FormRequest
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
            'is_active' => ['nullable', 'boolean'],
            'show_floating_badge' => ['nullable', 'boolean'],
            'auto_open_badge_screen' => ['nullable', 'boolean'],
            'floating_badge_delay_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'auto_open_delay_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'reward_title' => ['nullable', 'string', 'max:255'],
            'reward_subtitle' => ['nullable', 'string'],
            'reward_image_url' => ['nullable', 'string', 'max:1000'],
            'show_send_invite_button' => ['nullable', 'boolean'],
        ];
    }
}

