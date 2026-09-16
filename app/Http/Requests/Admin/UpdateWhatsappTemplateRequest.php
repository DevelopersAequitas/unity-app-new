<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWhatsappTemplateRequest extends FormRequest
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
            'template_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'webhook_url' => ['required', 'url', 'max:500'],
            'webhook_secret' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'template_name.required' => 'Template Name is required.',
            'webhook_url.required' => 'Webhook Endpoint URL is required.',
            'webhook_url.url' => 'Please provide a valid URL format for the webhook endpoint.',
            'webhook_secret.required' => 'Webhook Secret / API Token is required.',
            'is_active.required' => 'Active status is required.',
        ];
    }
}
