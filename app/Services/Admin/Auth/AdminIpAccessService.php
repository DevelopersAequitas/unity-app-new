<?php

declare(strict_types=1);

namespace App\Services\Admin\Auth;

use App\Models\AdminAuditLog;
use App\Models\AdminIpAccessSetting;
use App\Models\AdminIpAllowlist;
use App\Models\AdminUser;
use App\Models\User;
use App\Services\Audit\ActivityLoggerService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Throwable;

class AdminIpAccessService
{
    /**
     * Resolve the active environment key for IP Access scoping.
     * Ensures development and production configurations remain strictly isolated.
     */
    public function resolveEnvironment(?string $environment = null): string
    {
        $raw = strtolower(trim((string) ($environment ?? config('auth.ip_access_environment') ?? app()->environment())));

        return match ($raw) {
            'prod', 'production' => 'production',
            'dev', 'development', 'staging', 'qa' => 'development',
            'local' => 'local',
            'testing', 'test' => 'testing',
            default => $raw !== '' ? $raw : 'production',
        };
    }

    /**
     * Apply configured trusted proxies and resolve the client's normalized IP address.
     * Prevents spoofing of X-Forwarded-For or Forwarded headers from untrusted sources.
     */
    public function resolveClientIp(Request $request): ?string
    {
        $this->applyTrustedProxies($request);

        $rawIp = $request->ip();
        if (! is_string($rawIp) || trim($rawIp) === '') {
            return null;
        }

        $normalized = $this->normalizeIp($rawIp);

        return $normalized['ip'] ?? null;
    }

    /**
     * Apply the verified trusted-proxy configuration to the incoming request.
     */
    public function applyTrustedProxies(Request $request): void
    {
        $configured = config('auth.trusted_proxies', '127.0.0.1,::1');

        if (is_array($configured)) {
            $proxies = array_values(array_filter(array_map('trim', array_map('strval', $configured))));
        } else {
            $configuredStr = trim((string) $configured);
            $proxies = $configuredStr === ''
                ? []
                : array_values(array_filter(array_map('trim', explode(',', $configuredStr))));
        }

        $headers = SymfonyRequest::HEADER_X_FORWARDED_FOR
            | SymfonyRequest::HEADER_X_FORWARDED_HOST
            | SymfonyRequest::HEADER_X_FORWARDED_PORT
            | SymfonyRequest::HEADER_X_FORWARDED_PROTO
            | SymfonyRequest::HEADER_FORWARDED;

        SymfonyRequest::setTrustedProxies($proxies, $headers);
    }

    /**
     * Validate and normalize an IPv4 or IPv6 address into canonical form and binary representation.
     *
     * @return array{ip: string, version: int, binary: string}|null
     */
    public function normalizeIp(?string $ip): ?array
    {
        if ($ip === null) {
            return null;
        }

        $trimmed = trim($ip);
        if ($trimmed === '' || str_contains($trimmed, '/') || preg_match('/\s/', $trimmed) === 1) {
            return null;
        }

        $isIpv4 = filter_var($trimmed, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        $isIpv6 = ! $isIpv4 && filter_var($trimmed, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;

        if (! $isIpv4 && ! $isIpv6) {
            return null;
        }

        $binary = @inet_pton($trimmed);
        if ($binary === false) {
            return null;
        }

        $canonical = @inet_ntop($binary);
        if ($canonical === false || $canonical === '') {
            return null;
        }

        return [
            'ip' => strtolower($canonical),
            'version' => $isIpv4 ? 4 : 6,
            'binary' => $binary,
        ];
    }

    /**
     * Check whether IP-based password login enforcement is enabled for the given environment.
     */
    public function isEnforcementEnabled(?string $environment = null): bool
    {
        if (! Schema::hasTable('admin_ip_access_settings')) {
            return false;
        }

        $env = $this->resolveEnvironment($environment);

        try {
            $setting = AdminIpAccessSetting::query()
                ->where('environment', $env)
                ->first();

            return (bool) ($setting?->ip_access_enabled ?? false);
        } catch (Throwable $e) {
            Log::error('AdminIpAccessService::isEnforcementEnabled failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Get or initialize the settings record for the specified environment.
     */
    public function getOrCreateSetting(?string $environment = null): AdminIpAccessSetting
    {
        $env = $this->resolveEnvironment($environment);

        return AdminIpAccessSetting::query()->firstOrCreate(
            ['environment' => $env],
            [
                'id' => (string) Str::uuid(),
                'ip_access_enabled' => false,
            ]
        );
    }

    /**
     * Check whether the given client IP is authorized in the active allowlist for the environment.
     * Uses exact binary comparison via inet_pton() + hash_equals().
     */
    public function isIpAuthorized(?string $clientIp, ?string $environment = null): bool
    {
        $normalizedClient = $this->normalizeIp($clientIp);
        if ($normalizedClient === null) {
            return false;
        }

        if (! Schema::hasTable('admin_ip_allowlists')) {
            return false;
        }

        $env = $this->resolveEnvironment($environment);

        try {
            $allowedIps = AdminIpAllowlist::query()
                ->where('environment', $env)
                ->where('is_active', true)
                ->pluck('ip_address');

            if ($allowedIps->isEmpty()) {
                return false;
            }

            foreach ($allowedIps as $storedIp) {
                $normalizedStored = $this->normalizeIp((string) $storedIp);
                if (
                    $normalizedStored !== null
                    && $normalizedStored['version'] === $normalizedClient['version']
                    && hash_equals($normalizedStored['binary'], $normalizedClient['binary'])
                ) {
                    return true;
                }
            }
        } catch (Throwable $e) {
            Log::error('AdminIpAccessService::isIpAuthorized failed: '.$e->getMessage());

            return false;
        }

        return false;
    }

    /**
     * Determine whether password login is allowed for the given HTTP request.
     */
    public function isPasswordLoginAllowedForRequest(Request $request, ?string $environment = null): bool
    {
        $env = $this->resolveEnvironment($environment);

        if (! $this->isEnforcementEnabled($env)) {
            return false;
        }

        $clientIp = $this->resolveClientIp($request);

        return $this->isIpAuthorized($clientIp, $env);
    }

    /**
     * Evaluate the public login policy for the requesting client.
     * Never exposes the allowlist or sensitive configuration.
     *
     * @return array{
     *     ip_access_enabled: bool,
     *     password_login_allowed: bool,
     *     otp_login_allowed: bool,
     *     available_methods: array<int, string>
     * }
     */
    public function evaluateLoginPolicy(Request $request, ?string $environment = null): array
    {
        $env = $this->resolveEnvironment($environment);
        $enabled = $this->isEnforcementEnabled($env);
        $clientIp = $this->resolveClientIp($request);
        $ipAuthorized = $this->isIpAuthorized($clientIp, $env);
        $passwordAllowed = $enabled && $ipAuthorized;

        return [
            'ip_access_enabled' => $enabled,
            'ip_authorized' => $ipAuthorized,
            'password_login_allowed' => $passwordAllowed,
            'otp_login_allowed' => true,
            'available_methods' => $passwordAllowed ? ['password', 'otp'] : ['otp'],
        ];
    }

    /**
     * List all configured IP allowlist entries for the specified environment.
     *
     * @return Collection<int, AdminIpAllowlist>
     */
    public function listAllowedIps(?string $environment = null): Collection
    {
        $env = $this->resolveEnvironment($environment);

        if (! Schema::hasTable('admin_ip_allowlists')) {
            return collect();
        }

        return AdminIpAllowlist::query()
            ->where('environment', $env)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Retrieve the full IP Access configuration for authorized administrators.
     *
     * @return array<string, mixed>
     */
    public function getConfiguration(Request $request, ?string $environment = null): array
    {
        $env = $this->resolveEnvironment($environment);
        $setting = Schema::hasTable('admin_ip_access_settings')
            ? $this->getOrCreateSetting($env)
            : null;

        $ips = $this->listAllowedIps($env);
        $activeCount = $ips->where('is_active', true)->count();
        $enabled = (bool) ($setting?->ip_access_enabled ?? false);
        $currentIp = $this->resolveClientIp($request);
        $currentIpAuthorized = $this->isIpAuthorized($currentIp, $env);

        $warnings = [];
        if ($enabled && $activeCount === 0) {
            $warnings[] = 'IP-based password login enforcement is enabled with an empty allowlist. Password login is currently disabled for all IP addresses. OTP login remains available.';
        } elseif ($enabled && ! $currentIpAuthorized) {
            $warnings[] = 'Your current IP address is not in the authorized allowlist. Password login will not be available from your current IP, though OTP login remains available.';
        }

        return [
            'environment' => $env,
            'ip_access_enabled' => $enabled,
            'current_client_ip' => $currentIp,
            'current_ip_authorized' => $currentIpAuthorized,
            'allowlist_count' => $activeCount,
            'available_methods_for_current_ip' => ($enabled && $currentIpAuthorized) ? ['password', 'otp'] : ['otp'],
            'updated_by_user_id' => $setting?->updated_by_user_id,
            'updated_by_email' => $setting?->updated_by_email,
            'updated_at' => $setting?->updated_at?->toIso8601String(),
            'warnings' => $warnings,
            'allowlist' => $ips->map(fn (AdminIpAllowlist $item): array => $this->formatAllowlistItem($item))->values()->all(),
        ];
    }

    /**
     * Add a validated IPv4 or IPv6 address to the allowlist for the specified environment.
     *
     * @param  array{id: string|null, email: string|null}  $actor
     * @return array{status: int, success: bool, message: string, error_code?: string, errors?: array<string, array<int, string>>, data: array<string, mixed>|null}
     */
    public function addAllowedIp(
        string $rawIp,
        ?string $label,
        array $actor,
        ?string $requestIp = null,
        ?string $userAgent = null,
        ?string $environment = null
    ): array {
        $normalized = $this->normalizeIp($rawIp);
        if ($normalized === null) {
            return [
                'status' => 422,
                'success' => false,
                'error_code' => 'INVALID_IP_ADDRESS',
                'message' => 'The provided IP address is not a valid IPv4 or IPv6 address.',
                'errors' => [
                    'ip_address' => ['The provided IP address must be a valid IPv4 or IPv6 address.'],
                ],
                'data' => null,
            ];
        }

        $env = $this->resolveEnvironment($environment);
        $canonicalIp = $normalized['ip'];
        $ipVersion = $normalized['version'];
        $cleanLabel = $label !== null && trim($label) !== '' ? trim($label) : null;

        return DB::transaction(function () use ($env, $canonicalIp, $ipVersion, $cleanLabel, $actor, $requestIp, $userAgent): array {
            $existing = AdminIpAllowlist::query()
                ->where('environment', $env)
                ->where('ip_address', $canonicalIp)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return [
                    'status' => 422,
                    'success' => false,
                    'error_code' => 'DUPLICATE_IP_ADDRESS',
                    'message' => 'This IP address is already configured in the allowlist.',
                    'errors' => [
                        'ip_address' => ['This IP address is already configured in the allowlist.'],
                    ],
                    'data' => null,
                ];
            }

            $entry = AdminIpAllowlist::query()->create([
                'id' => (string) Str::uuid(),
                'environment' => $env,
                'ip_address' => $canonicalIp,
                'ip_version' => $ipVersion,
                'label' => $cleanLabel,
                'is_active' => true,
                'created_by_user_id' => $actor['id'] ?? null,
                'created_by_email' => $actor['email'] ?? null,
                'updated_by_user_id' => $actor['id'] ?? null,
                'updated_by_email' => $actor['email'] ?? null,
            ]);

            $this->recordConfigurationAudit(
                actor: $actor,
                action: 'ip_access.ip_added',
                description: "Added authorized IPv{$ipVersion} address ({$canonicalIp}) to Admin IP Access allowlist ({$env})",
                targetTable: 'admin_ip_allowlists',
                targetId: (string) $entry->id,
                details: [
                    'environment' => $env,
                    'ip_address' => $canonicalIp,
                    'ip_version' => $ipVersion,
                    'label' => $cleanLabel,
                ],
                requestIp: $requestIp,
                userAgent: $userAgent
            );

            return [
                'status' => 201,
                'success' => true,
                'message' => 'Authorized IP address added successfully.',
                'data' => [
                    'ip' => $this->formatAllowlistItem($entry),
                ],
            ];
        });
    }

    /**
     * Update an existing IP address entry in the allowlist.
     *
     * @param  array{id: string|null, email: string|null}  $actor
     * @return array{status: int, success: bool, message: string, error_code?: string, errors?: array<string, array<int, string>>, data: array<string, mixed>|null}
     */
    public function updateAllowedIp(
        string $id,
        ?string $rawIp,
        ?string $label,
        bool $hasLabel,
        ?bool $isActive,
        array $actor,
        ?string $requestIp = null,
        ?string $userAgent = null,
        ?string $environment = null
    ): array {
        $env = $this->resolveEnvironment($environment);

        return DB::transaction(function () use ($id, $env, $rawIp, $label, $hasLabel, $isActive, $actor, $requestIp, $userAgent): array {
            $entry = AdminIpAllowlist::query()
                ->where('environment', $env)
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (! $entry) {
                return [
                    'status' => 404,
                    'success' => false,
                    'error_code' => 'IP_ENTRY_NOT_FOUND',
                    'message' => 'Authorized IP address entry not found.',
                    'data' => null,
                ];
            }

            $canonicalIp = (string) $entry->ip_address;
            $ipVersion = (int) $entry->ip_version;

            if ($rawIp !== null && trim($rawIp) !== '') {
                $normalized = $this->normalizeIp($rawIp);
                if ($normalized === null) {
                    return [
                        'status' => 422,
                        'success' => false,
                        'error_code' => 'INVALID_IP_ADDRESS',
                        'message' => 'The provided IP address is not a valid IPv4 or IPv6 address.',
                        'errors' => [
                            'ip_address' => ['The provided IP address must be a valid IPv4 or IPv6 address.'],
                        ],
                        'data' => null,
                    ];
                }

                $canonicalIp = $normalized['ip'];
                $ipVersion = $normalized['version'];

                $duplicate = AdminIpAllowlist::query()
                    ->where('environment', $env)
                    ->where('ip_address', $canonicalIp)
                    ->where('id', '!=', $id)
                    ->lockForUpdate()
                    ->first();

                if ($duplicate) {
                    return [
                        'status' => 422,
                        'success' => false,
                        'error_code' => 'DUPLICATE_IP_ADDRESS',
                        'message' => 'This IP address is already configured in the allowlist.',
                        'errors' => [
                            'ip_address' => ['This IP address is already configured in the allowlist.'],
                        ],
                        'data' => null,
                    ];
                }
            }

            $entry->ip_address = $canonicalIp;
            $entry->ip_version = $ipVersion;
            if ($hasLabel) {
                $entry->label = $label !== null && trim($label) !== '' ? trim($label) : null;
            }
            if ($isActive !== null) {
                $entry->is_active = $isActive;
            }
            $entry->updated_by_user_id = $actor['id'] ?? null;
            $entry->updated_by_email = $actor['email'] ?? null;
            $entry->save();

            $this->recordConfigurationAudit(
                actor: $actor,
                action: 'ip_access.ip_updated',
                description: "Updated authorized IPv{$ipVersion} address ({$canonicalIp}) in Admin IP Access allowlist ({$env})",
                targetTable: 'admin_ip_allowlists',
                targetId: (string) $entry->id,
                details: [
                    'environment' => $env,
                    'ip_address' => $canonicalIp,
                    'ip_version' => $ipVersion,
                    'label' => $entry->label,
                    'is_active' => (bool) $entry->is_active,
                ],
                requestIp: $requestIp,
                userAgent: $userAgent
            );

            return [
                'status' => 200,
                'success' => true,
                'message' => 'Authorized IP address updated successfully.',
                'data' => [
                    'ip' => $this->formatAllowlistItem($entry),
                ],
            ];
        });
    }

    /**
     * Remove an IP address from the allowlist, enforcing lockout safeguards when removing the final IP.
     *
     * @param  array{id: string|null, email: string|null}  $actor
     * @return array{status: int, success: bool, message: string, error_code?: string, requires_confirmation?: bool, warning?: string|null, data: array<string, mixed>|null}
     */
    public function removeAllowedIp(
        string $id,
        bool $confirmLastIpRemoval,
        array $actor,
        ?string $requestIp = null,
        ?string $userAgent = null,
        ?string $environment = null
    ): array {
        $env = $this->resolveEnvironment($environment);

        return DB::transaction(function () use ($id, $env, $confirmLastIpRemoval, $actor, $requestIp, $userAgent): array {
            $entry = AdminIpAllowlist::query()
                ->where('environment', $env)
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (! $entry) {
                return [
                    'status' => 404,
                    'success' => false,
                    'error_code' => 'IP_ENTRY_NOT_FOUND',
                    'message' => 'Authorized IP address entry not found.',
                    'data' => null,
                ];
            }

            $activeCount = AdminIpAllowlist::query()
                ->where('environment', $env)
                ->where('is_active', true)
                ->count();

            $isLastActiveIp = $entry->is_active && $activeCount <= 1;
            $enforcementEnabled = $this->isEnforcementEnabled($env);

            if ($isLastActiveIp && $enforcementEnabled && ! $confirmLastIpRemoval) {
                return [
                    'status' => 409,
                    'success' => false,
                    'error_code' => 'CONFIRMATION_REQUIRED_LAST_IP_REMOVAL',
                    'requires_confirmation' => true,
                    'message' => 'Removing the final authorized IP address while enforcement is enabled will disable password login for all IP addresses. OTP login will remain available. Set confirm_last_ip_removal to true to confirm.',
                    'data' => [
                        'ip_access_enabled' => true,
                        'remaining_ips_count' => $activeCount,
                    ],
                ];
            }

            $removedIp = (string) $entry->ip_address;
            $removedVersion = (int) $entry->ip_version;
            $removedLabel = $entry->label;

            $entry->delete();

            $remainingCount = max(0, $activeCount - ($entry->is_active ? 1 : 0));

            $warning = null;
            if ($remainingCount === 0 && $enforcementEnabled) {
                $warning = 'The final authorized IP address has been removed while enforcement is enabled. Password login is now disabled for all IP addresses; OTP login remains available.';
            } elseif ($remainingCount === 0) {
                $warning = 'The authorized IP allowlist is now empty. If enforcement is enabled without adding an IP address, password login will be disabled for everyone.';
            }

            $this->recordConfigurationAudit(
                actor: $actor,
                action: 'ip_access.ip_removed',
                description: "Removed authorized IPv{$removedVersion} address ({$removedIp}) from Admin IP Access allowlist ({$env})",
                targetTable: 'admin_ip_allowlists',
                targetId: $id,
                details: [
                    'environment' => $env,
                    'ip_address' => $removedIp,
                    'ip_version' => $removedVersion,
                    'label' => $removedLabel,
                    'remaining_ips_count' => $remainingCount,
                    'ip_access_enabled' => $enforcementEnabled,
                ],
                requestIp: $requestIp,
                userAgent: $userAgent
            );

            return [
                'status' => 200,
                'success' => true,
                'message' => 'Authorized IP address removed successfully.',
                'warning' => $warning,
                'data' => [
                    'removed_id' => $id,
                    'removed_ip' => $removedIp,
                    'remaining_ips_count' => $remainingCount,
                    'ip_access_enabled' => $enforcementEnabled,
                    'warning' => $warning,
                ],
            ];
        });
    }

    /**
     * Enable or disable IP-based password login enforcement for the specified environment.
     *
     * @param  array{id: string|null, email: string|null}  $actor
     * @return array{status: int, success: bool, message: string, error_code?: string, requires_confirmation?: bool, warning?: string|null, data: array<string, mixed>|null}
     */
    public function updateEnforcement(
        bool $enabled,
        bool $confirmEmptyAllowlist,
        array $actor,
        ?string $requestIp = null,
        ?string $userAgent = null,
        ?string $environment = null
    ): array {
        $env = $this->resolveEnvironment($environment);

        return DB::transaction(function () use ($env, $enabled, $confirmEmptyAllowlist, $actor, $requestIp, $userAgent): array {
            $setting = $this->getOrCreateSetting($env);
            $previousState = (bool) $setting->ip_access_enabled;

            $activeCount = Schema::hasTable('admin_ip_allowlists')
                ? AdminIpAllowlist::query()
                    ->where('environment', $env)
                    ->where('is_active', true)
                    ->count()
                : 0;

            if ($enabled && $activeCount === 0 && ! $confirmEmptyAllowlist) {
                return [
                    'status' => 409,
                    'success' => false,
                    'error_code' => 'CONFIRMATION_REQUIRED_EMPTY_ALLOWLIST',
                    'requires_confirmation' => true,
                    'message' => 'Enabling IP-based password login enforcement with an empty allowlist will disable password login for everyone. OTP login will remain available. Set confirm_empty_allowlist to true to confirm.',
                    'data' => [
                        'ip_access_enabled' => $previousState,
                        'allowlist_count' => 0,
                    ],
                ];
            }

            $setting->ip_access_enabled = $enabled;
            $setting->updated_by_user_id = $actor['id'] ?? null;
            $setting->updated_by_email = $actor['email'] ?? null;
            $setting->save();

            $warning = null;
            if ($enabled && $activeCount === 0) {
                $warning = 'IP-based password login enforcement is enabled with an empty allowlist. Password login is disabled for all IP addresses until an authorized IP is added. OTP login remains available.';
            } elseif ($enabled && $requestIp !== null && ! $this->isIpAuthorized($requestIp, $env)) {
                $warning = 'IP-based password login enforcement is enabled, and your current IP address is not on the allowlist. You will need to use OTP verification on your next login.';
            }

            $this->recordConfigurationAudit(
                actor: $actor,
                action: $enabled ? 'ip_access.enforcement_enabled' : 'ip_access.enforcement_disabled',
                description: ($enabled ? 'Enabled' : 'Disabled')." IP-based password login enforcement ({$env})",
                targetTable: 'admin_ip_access_settings',
                targetId: (string) $setting->id,
                details: [
                    'environment' => $env,
                    'previous_ip_access_enabled' => $previousState,
                    'ip_access_enabled' => $enabled,
                    'allowlist_count' => $activeCount,
                    'confirmed_empty_allowlist' => $enabled && $activeCount === 0 ? $confirmEmptyAllowlist : null,
                ],
                requestIp: $requestIp,
                userAgent: $userAgent
            );

            return [
                'status' => 200,
                'success' => true,
                'message' => $enabled
                    ? 'IP-based password login enforcement enabled.'
                    : 'IP-based password login enforcement disabled.',
                'warning' => $warning,
                'data' => [
                    'environment' => $env,
                    'ip_access_enabled' => (bool) $setting->ip_access_enabled,
                    'allowlist_count' => $activeCount,
                    'updated_by_user_id' => $setting->updated_by_user_id,
                    'updated_by_email' => $setting->updated_by_email,
                    'updated_at' => $setting->updated_at?->toIso8601String(),
                    'warning' => $warning,
                ],
            ];
        });
    }

    /**
     * Record a denied password login attempt due to an unauthorized IP address.
     * Rate-limited per IP to prevent audit log flooding.
     */
    public function recordPasswordLoginDenied(Request $request, ?string $email = null, ?string $environment = null): void
    {
        $clientIp = $this->resolveClientIp($request) ?? ($request->ip() ?: 'unknown');
        $env = $this->resolveEnvironment($environment);

        $rateLimitKey = 'ip-access-deny-audit:'.$env.':'.$clientIp;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            return;
        }
        RateLimiter::hit($rateLimitKey, 60);

        $normalizedEmail = $email !== null ? strtolower(trim($email)) : null;
        $userId = null;

        if ($normalizedEmail !== null && $normalizedEmail !== '' && Schema::hasTable('users')) {
            try {
                $userId = User::query()
                    ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                    ->value('id');
            } catch (Throwable) {
                $userId = null;
            }
        }

        if (! $userId && $normalizedEmail !== null && $normalizedEmail !== '' && Schema::hasTable('admin_users')) {
            try {
                $userId = AdminUser::query()
                    ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                    ->value('id');
            } catch (Throwable) {
                $userId = null;
            }
        }

        $details = [
            'environment' => $env,
            'email' => $normalizedEmail,
            'client_ip' => $clientIp,
            'reason' => 'IP_NOT_AUTHORIZED_FOR_PASSWORD_LOGIN',
            'login_method' => 'password',
        ];

        $validUserId = null;
        if ($userId && Schema::hasTable('users')) {
            try {
                if (User::query()->whereKey((string) $userId)->exists()) {
                    $validUserId = (string) $userId;
                }
            } catch (Throwable) {
                $validUserId = null;
            }
        }

        if (Schema::hasTable('admin_audit_logs')) {
            try {
                DB::transaction(function () use ($validUserId, $details, $clientIp, $request): void {
                    AdminAuditLog::query()->create([
                        'id' => (string) Str::uuid(),
                        'admin_user_id' => $validUserId,
                        'action' => 'auth.password_login_ip_denied',
                        'target_table' => 'admin_ip_access_settings',
                        'target_id' => null,
                        'details' => $details,
                        'ip_address' => substr((string) $clientIp, 0, 45),
                        'user_agent' => $request->userAgent(),
                        'created_at' => now(),
                    ]);
                });
            } catch (Throwable $e) {
                Log::warning('Failed writing AdminAuditLog for denied IP password login: '.$e->getMessage());
            }
        }

        if ($validUserId !== null && Schema::hasTable('activity_logs')) {
            try {
                DB::transaction(function () use ($validUserId, $details, $clientIp, $request): void {
                    ActivityLoggerService::log([
                        'user_id' => $validUserId,
                        'actor_id' => $validUserId,
                        'activity_type' => 'auth.password_login_ip_denied',
                        'module' => 'auth',
                        'description' => 'Password login denied because client IP is not in the authorized IP allowlist',
                        'metadata' => $details,
                        'ip_address' => substr((string) $clientIp, 0, 45),
                        'user_agent' => $request->userAgent(),
                        'status' => 'denied',
                    ]);
                });
            } catch (Throwable $e) {
                Log::warning('Failed writing ActivityLog for denied IP password login: '.$e->getMessage());
            }
        }
    }

    /**
     * Format an allowlist model for API responses.
     *
     * @return array<string, mixed>
     */
    public function formatAllowlistItem(AdminIpAllowlist $item): array
    {
        return [
            'id' => (string) $item->id,
            'environment' => (string) $item->environment,
            'ip_address' => (string) $item->ip_address,
            'ip_version' => (int) $item->ip_version,
            'label' => $item->label,
            'is_active' => (bool) $item->is_active,
            'created_by_user_id' => $item->created_by_user_id,
            'created_by_email' => $item->created_by_email,
            'updated_by_user_id' => $item->updated_by_user_id,
            'updated_by_email' => $item->updated_by_email,
            'created_at' => $item->created_at?->toIso8601String(),
            'updated_at' => $item->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Record configuration changes into existing audit log tables without storing sensitive credentials.
     *
     * @param  array{id: string|null, email: string|null}  $actor
     * @param  array<string, mixed>  $details
     */
    private function recordConfigurationAudit(
        array $actor,
        string $action,
        string $description,
        string $targetTable,
        ?string $targetId,
        array $details,
        ?string $requestIp,
        ?string $userAgent
    ): void {
        $actorId = isset($actor['id']) && $actor['id'] !== '' ? (string) $actor['id'] : null;
        $actorEmail = isset($actor['email']) && $actor['email'] !== '' ? (string) $actor['email'] : null;

        $metadata = array_merge($details, [
            'actor_id' => $actorId,
            'actor_email' => $actorEmail,
            'timestamp' => now()->toIso8601String(),
        ]);

        $validUserId = null;
        if (Schema::hasTable('users')) {
            try {
                if ($actorId !== null && User::query()->whereKey($actorId)->exists()) {
                    $validUserId = $actorId;
                } elseif ($actorEmail !== null) {
                    $foundId = User::query()
                        ->whereRaw('LOWER(email) = ?', [strtolower($actorEmail)])
                        ->value('id');
                    if ($foundId !== null) {
                        $validUserId = (string) $foundId;
                    }
                }
            } catch (Throwable) {
                $validUserId = null;
            }
        }

        if (Schema::hasTable('admin_audit_logs')) {
            try {
                DB::transaction(function () use ($validUserId, $action, $targetTable, $targetId, $metadata, $requestIp, $userAgent): void {
                    AdminAuditLog::query()->create([
                        'id' => (string) Str::uuid(),
                        'admin_user_id' => $validUserId,
                        'action' => $action,
                        'target_table' => $targetTable,
                        'target_id' => $targetId,
                        'details' => $metadata,
                        'ip_address' => $requestIp ? substr($requestIp, 0, 45) : null,
                        'user_agent' => $userAgent,
                        'created_at' => now(),
                    ]);
                });
            } catch (Throwable $e) {
                Log::warning('Failed writing AdminAuditLog for IP Access change: '.$e->getMessage());
            }
        }

        if ($validUserId !== null && Schema::hasTable('activity_logs')) {
            try {
                DB::transaction(function () use ($validUserId, $action, $description, $targetTable, $targetId, $metadata, $requestIp, $userAgent): void {
                    ActivityLoggerService::log([
                        'user_id' => $validUserId,
                        'actor_id' => $validUserId,
                        'activity_type' => $action,
                        'module' => 'system',
                        'description' => $description,
                        'entity_type' => $targetTable,
                        'entity_id' => $targetId,
                        'metadata' => $metadata,
                        'ip_address' => $requestIp ? substr($requestIp, 0, 45) : null,
                        'user_agent' => $userAgent,
                        'status' => 'success',
                    ]);
                });
            } catch (Throwable $e) {
                Log::warning('Failed writing ActivityLog for IP Access change: '.$e->getMessage());
            }
        }
    }
}
