<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Auth;

use App\Services\Admin\Auth\AdminAuthService;
use Illuminate\Foundation\Http\FormRequest;

class AdminRequestOtpRequest extends FormRequest
{
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
        $enabled = app(AdminAuthService::class)->getEnabledLoginMethods();
        $allowedChannels = ! empty($enabled) ? implode(',', array_column($enabled, 'key')) : 'email,whatsapp';

        return [
            'identifier' => ['required_without_all:email,mobile,phone', 'nullable', 'string'],
            'channel' => ['nullable', 'string', 'in:'.$allowedChannels],
            'email' => ['nullable', 'string'],
            'mobile' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
        ];
    }

    /**
     * Get the resolved identifier (email or mobile number).
     */
    public function resolvedIdentifier(): string
    {
        $identifier = $this->input('identifier')
            ?? $this->input('email')
            ?? $this->input('mobile')
            ?? $this->input('phone')
            ?? '';

        return trim((string) $identifier);
    }

    /**
     * Get the resolved authentication channel ('email' or 'whatsapp').
     */
    public function resolvedChannel(): string
    {
        $channel = strtolower(trim((string) $this->input('channel', '')));
        if (in_array($channel, ['email', 'whatsapp'], true)) {
            return $channel;
        }

        return app(AdminAuthService::class)->detectChannel($this->resolvedIdentifier());
    }
}
