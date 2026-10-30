<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\System;

use App\Services\Admin\Auth\AdminIpAccessService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreAdminIpAllowlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $ip = $this->input('ip_address') ?? $this->input('ip');
        if ($ip !== null) {
            $this->merge([
                'ip_address' => is_string($ip) ? trim($ip) : $ip,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ip_address' => [
                'required',
                'string',
                'max:45',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || app(AdminIpAccessService::class)->normalizeIp($value) === null) {
                        $fail('The provided IP address must be a valid IPv4 or IPv6 address.');
                    }
                },
            ],
            'label' => ['nullable', 'string', 'max:255'],
        ];
    }
}

