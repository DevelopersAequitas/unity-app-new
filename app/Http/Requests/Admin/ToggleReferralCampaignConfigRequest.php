<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ToggleReferralCampaignConfigRequest extends FormRequest
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
        ];
    }
}

