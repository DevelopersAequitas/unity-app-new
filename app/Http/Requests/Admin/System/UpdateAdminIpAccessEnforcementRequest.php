<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\System;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminIpAccessEnforcementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('ip_access_enabled') && $this->has('enabled')) {
            $this->merge([
                'ip_access_enabled' => $this->input('enabled'),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ip_access_enabled' => ['required', 'boolean'],
            'confirm_empty_allowlist' => ['nullable', 'boolean'],
            'confirm' => ['nullable', 'boolean'],
            'force' => ['nullable', 'boolean'],
        ];
    }

    public function confirmedEmptyAllowlist(): bool
    {
        return $this->boolean('confirm_empty_allowlist')
            || $this->boolean('confirm')
            || $this->boolean('force');
    }
}

