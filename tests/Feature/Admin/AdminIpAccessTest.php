<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminIpAccessSetting;
use App\Models\AdminIpAllowlist;
use App\Models\AdminUser;
use App\Models\OtpCode;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\Auth\AdminIpAccessService;
use App\Services\EmailLogs\EmailLogService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminIpAccessTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        if (($_ENV['DB_PASSWORD'] ?? '') === 'root' && file_exists(dirname(__DIR__, 3).'/.env')) {
            $envContent = (string) file_get_contents(dirname(__DIR__, 3).'/.env');
            if (preg_match('/^DB_DATABASE=(.+)$/m', $envContent, $dbMatch)) {
                $dbName = trim($dbMatch[1], " \t\n\r\0\x0B\"'");
                $_ENV['DB_DATABASE'] = $dbName;
                $_SERVER['DB_DATABASE'] = $dbName;
                putenv("DB_DATABASE={$dbName}");
            }
            if (preg_match('/^DB_PASSWORD=(.+)$/m', $envContent, $pwMatch)) {
                $dbPass = trim($pwMatch[1], " \t\n\r\0\x0B\"'");
                $_ENV['DB_PASSWORD'] = $dbPass;
                $_SERVER['DB_PASSWORD'] = $dbPass;
                putenv("DB_PASSWORD={$dbPass}");
            }
        }

        parent::setUp();

        $this->ensureTablesExist();

        AdminIpAllowlist::query()->whereIn('environment', ['testing', 'development', 'production'])->delete();
        AdminIpAccessSetting::query()->whereIn('environment', ['testing', 'development', 'production'])->delete();

        config([
            'auth.trusted_proxies' => '127.0.0.1,::1',
            'auth.ip_access_environment' => 'testing',
        ]);
    }

    private function ensureTablesExist(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('email')->unique();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('display_name')->nullable();
                $table->string('phone')->nullable();
                $table->string('password_hash')->nullable();
                $table->string('password')->nullable();
                $table->string('status')->default('active');
                $table->string('membership_status')->default('active');
                $table->timestamp('last_login_at')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_users')) {
            Schema::create('admin_users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('role')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('key')->unique();
                $table->string('description')->nullable();
                $table->string('role_type')->nullable();
                $table->string('scope_rule')->nullable();
                $table->boolean('is_assignable')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_user_roles')) {
            Schema::create('admin_user_roles', function (Blueprint $table): void {
                $table->uuid('user_id');
                $table->uuid('role_id');
            });
        }

        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table): void {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('otp_codes')) {
            Schema::create('otp_codes', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->nullable();
                $table->string('email');
                $table->string('purpose');
                $table->string('code');
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_ip_access_settings')) {
            Schema::create('admin_ip_access_settings', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('environment', 50)->unique();
                $table->boolean('ip_access_enabled')->default(false);
                $table->uuid('updated_by_user_id')->nullable();
                $table->string('updated_by_email', 255)->nullable();
                $table->timestampsTz();
            });
        }

        if (! Schema::hasTable('admin_ip_allowlists')) {
            Schema::create('admin_ip_allowlists', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('environment', 50)->default('production');
                $table->string('ip_address', 45);
                $table->smallInteger('ip_version')->default(4);
                $table->string('label', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->uuid('created_by_user_id')->nullable();
                $table->string('created_by_email', 255)->nullable();
                $table->uuid('updated_by_user_id')->nullable();
                $table->string('updated_by_email', 255)->nullable();
                $table->timestampsTz();
                $table->unique(['environment', 'ip_address']);
            });
        }

        if (! Schema::hasTable('admin_audit_logs')) {
            Schema::create('admin_audit_logs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('admin_user_id')->nullable();
                $table->string('action');
                $table->string('target_table')->nullable();
                $table->string('target_id')->nullable();
                $table->json('details')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->nullable();
                $table->uuid('actor_id')->nullable();
                $table->string('activity_type');
                $table->string('module');
                $table->text('description')->nullable();
                $table->string('entity_type')->nullable();
                $table->string('entity_id')->nullable();
                $table->json('metadata')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('status')->default('success');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('user_login_histories')) {
            Schema::create('user_login_histories', function (Blueprint $table): void {
                $table->id();
                $table->uuid('user_id')->nullable();
                $table->timestamp('logged_in_at')->nullable();
                $table->string('ip')->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('circle_members') && ! Schema::hasColumn('circle_members', 'paid_ends_at')) {
            Schema::table('circle_members', function (Blueprint $table): void {
                $table->timestamp('paid_ends_at')->nullable();
            });
        }
    }

    private function createActiveUser(string $email = 'admin.peer@peersunity.com', string $password = 'SecretPass123!'): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'email' => $email,
            'first_name' => 'Admin',
            'last_name' => 'Peer',
            'display_name' => 'Admin Peer',
            'password_hash' => Hash::make($password),
            'membership_status' => 'active',
        ]);
    }

    private function createSuperAdminUser(string $email = 'superadmin@peersunity.com'): AdminUser
    {
        $role = Role::query()->firstOrCreate(
            ['key' => 'global_admin'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Global Admin',
            ]
        );

        $admin = AdminUser::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Super Admin',
            'email' => $email,
        ]);

        DB::table('admin_user_roles')->insert([
            'user_id' => $admin->id,
            'role_id' => $role->id,
        ]);

        return $admin;
    }

    private function createCircleLeaderAdminUser(string $email = 'circleleader@peersunity.com'): AdminUser
    {
        $role = Role::query()->firstOrCreate(
            ['key' => 'chair'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Chair',
            ]
        );

        $admin = AdminUser::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Circle Chair',
            'email' => $email,
        ]);

        DB::table('admin_user_roles')->insert([
            'user_id' => $admin->id,
            'role_id' => $role->id,
        ]);

        return $admin;
    }

    /**
     * Scenario 1: Enforcement disabled — existing password login remains unchanged.
     */
    public function test_1_enforcement_disabled_allows_password_login_from_any_ip(): void
    {
        $this->createActiveUser('test1@peersunity.com', 'SecretPass123!');

        $policyResponse = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.42'])
            ->getJson('/api/v1/auth/login-policy');

        $policyResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ip_access_enabled', false)
            ->assertJsonPath('data.password_login_allowed', true)
            ->assertJsonPath('data.available_methods', ['password', 'otp']);

        $loginResponse = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.42'])
            ->postJson('/api/v1/auth/login', [
                'email' => 'test1@peersunity.com',
                'password' => 'SecretPass123!',
            ]);

        $loginResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    /**
     * Scenario 2: Enforcement enabled — authorized IPv4 can use password login.
     */
    public function test_2_enforcement_enabled_allows_authorized_ipv4_password_login(): void
    {
        $this->createActiveUser('test2@peersunity.com', 'SecretPass123!');

        AdminIpAccessSetting::query()->create([
            'environment' => 'testing',
            'ip_access_enabled' => true,
        ]);

        AdminIpAllowlist::query()->create([
            'environment' => 'testing',
            'ip_address' => '203.0.113.10',
            'ip_version' => 4,
            'label' => 'Office IPv4',
            'is_active' => true,
        ]);

        $policyResponse = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->getJson('/api/v1/auth/login-policy');

        $policyResponse->assertOk()
            ->assertJsonPath('data.ip_access_enabled', true)
            ->assertJsonPath('data.password_login_allowed', true)
            ->assertJsonPath('data.available_methods', ['password', 'otp']);

        $loginResponse = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->postJson('/api/v1/auth/login', [
                'email' => 'test2@peersunity.com',
                'password' => 'SecretPass123!',
            ]);

        $loginResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    /**
     * Scenario 3 & 9: Enforcement enabled — unauthorized IPv4 is denied password login (403) and issues no token or session.
     */
    public function test_3_and_9_enforcement_enabled_denies_unauthorized_ipv4_and_issues_no_token(): void
    {
        $user = $this->createActiveUser('test3@peersunity.com', 'SecretPass123!');
        $initialTokensCount = DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count();

        AdminIpAccessSetting::query()->create([
            'environment' => 'testing',
            'ip_access_enabled' => true,
        ]);

        AdminIpAllowlist::query()->create([
            'environment' => 'testing',
            'ip_address' => '203.0.113.10',
            'ip_version' => 4,
            'is_active' => true,
        ]);

        $policyResponse = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.77'])
            ->getJson('/api/v1/auth/login-policy');

        $policyResponse->assertOk()
            ->assertJsonPath('data.ip_access_enabled', true)
            ->assertJsonPath('data.password_login_allowed', false)
            ->assertJsonPath('data.available_methods', ['otp']);

        $loginResponse = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.77'])
            ->postJson('/api/v1/auth/login', [
                'email' => 'test3@peersunity.com',
                'password' => 'SecretPass123!',
            ]);

        $loginResponse->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'IP_NOT_AUTHORIZED_FOR_PASSWORD_LOGIN')
            ->assertJsonPath('data', null);

        $afterTokensCount = DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count();
        $this->assertSame($initialTokensCount, $afterTokensCount, 'No token must be issued for a denied password login.');
        $this->assertGuest();
    }

    /**
     * Scenario 4: Enforcement enabled — authorized IPv6 (including non-canonical representation) can use password login.
     */
    public function test_4_enforcement_enabled_allows_authorized_ipv6_password_login(): void
    {
        $this->createActiveUser('test4@peersunity.com', 'SecretPass123!');

        AdminIpAccessSetting::query()->create([
            'environment' => 'testing',
            'ip_access_enabled' => true,
        ]);

        AdminIpAllowlist::query()->create([
            'environment' => 'testing',
            'ip_address' => '2001:db8::1',
            'ip_version' => 6,
            'label' => 'Office IPv6',
            'is_active' => true,
        ]);

        // Connect using expanded uppercase IPv6 form to verify normalization & binary comparison
        $loginResponse = $this->withServerVariables(['REMOTE_ADDR' => '2001:0DB8:0000:0000:0000:0000:0000:0001'])
            ->postJson('/api/v1/auth/login', [
                'email' => 'test4@peersunity.com',
                'password' => 'SecretPass123!',
            ]);

        $loginResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    /**
     * Scenario 5: Enforcement enabled — unauthorized IPv6 is denied password login.
     */
    public function test_5_enforcement_enabled_denies_unauthorized_ipv6_password_login(): void
    {
        $this->createActiveUser('test5@peersunity.com', 'SecretPass123!');

        AdminIpAccessSetting::query()->create([
            'environment' => 'testing',
            'ip_access_enabled' => true,
        ]);

        AdminIpAllowlist::query()->create([
            'environment' => 'testing',
            'ip_address' => '2001:db8::1',
            'ip_version' => 6,
            'is_active' => true,
        ]);

        $loginResponse = $this->withServerVariables(['REMOTE_ADDR' => '2001:db8::2'])
            ->postJson('/api/v1/auth/login', [
                'email' => 'test5@peersunity.com',
                'password' => 'SecretPass123!',
            ]);

        $loginResponse->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'IP_NOT_AUTHORIZED_FOR_PASSWORD_LOGIN');
    }

    /**
     * Scenario 6: Enforcement enabled with an empty allowlist — all password login attempts are denied.
     */
    public function test_6_enforcement_enabled_with_empty_allowlist_denies_all_password_logins(): void
    {
        $this->createActiveUser('test6@peersunity.com', 'SecretPass123!');

        AdminIpAccessSetting::query()->create([
            'environment' => 'testing',
            'ip_access_enabled' => true,
        ]);

        $loginResponse = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson('/api/v1/auth/login', [
                'email' => 'test6@peersunity.com',
                'password' => 'SecretPass123!',
            ]);

        $loginResponse->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'IP_NOT_AUTHORIZED_FOR_PASSWORD_LOGIN');
    }

    /**
     * Scenario 7 & 8: OTP request and OTP verification remain available from an unauthorized IP when enforcement is enabled.
     */
    public function test_7_and_8_otp_request_and_verification_succeed_from_unauthorized_ip(): void
    {
        Mail::fake();
        $this->mock(EmailLogService::class)->shouldIgnoreMissing();

        $user = $this->createActiveUser('otp.admin@peersunity.com', 'SecretPass123!');

        AdminIpAccessSetting::query()->create([
            'environment' => 'testing',
            'ip_access_enabled' => true,
        ]);

        AdminIpAllowlist::query()->create([
            'environment' => 'testing',
            'ip_address' => '203.0.113.10',
            'ip_version' => 4,
            'is_active' => true,
        ]);

        // 7. Request OTP from unauthorized IP 198.51.100.88
        $otpRequestResponse = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.88'])
            ->postJson('/api/v1/auth/request-otp', [
                'email' => 'otp.admin@peersunity.com',
            ]);

        $otpRequestResponse->assertOk()
            ->assertJsonPath('success', true);

        // Seed known 4-digit OTP code for verification
        OtpCode::query()->where('user_id', $user->id)->delete();
        OtpCode::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'purpose' => 'login_otp',
            'code' => Hash::make('4321'),
            'expires_at' => now()->addMinutes(5),
            'used_at' => null,
        ]);

        // 8. Verify OTP from unauthorized IP 198.51.100.88
        $otpVerifyResponse = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.88'])
            ->postJson('/api/v1/auth/verify-otp', [
                'email' => 'otp.admin@peersunity.com',
                'otp' => '4321',
            ]);

        $otpVerifyResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    /**
     * Scenario 10 & 11: Invalid IP cannot be saved, and duplicate IP entries are rejected cleanly.
     */
    public function test_10_and_11_invalid_and_duplicate_ips_are_rejected(): void
    {
        $superAdmin = $this->createSuperAdminUser();
        Sanctum::actingAs($superAdmin);

        // 10. Invalid IP addresses (malformed, CIDR range, hostname)
        foreach (['999.999.999.999', 'not-an-ip', '203.0.113.0/24', '2001:db8::gggg'] as $invalidIp) {
            $invalidRes = $this->postJson('/api/v1/admin/system/ip-access/ips', [
                'ip_address' => $invalidIp,
                'label' => 'Invalid entry',
            ]);

            $invalidRes->assertStatus(422)
                ->assertJsonValidationErrors(['ip_address']);
        }

        // Valid IPv6 add
        $validRes = $this->postJson('/api/v1/admin/system/ip-access/ips', [
            'ip_address' => '2001:0DB8:0000:0000:0000:0000:0000:0005',
            'label' => 'Primary IPv6',
        ]);

        $validRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ip.ip_address', '2001:db8::5')
            ->assertJsonPath('data.ip.ip_version', 6);

        // 11. Duplicate IPv6 (in compressed form) is detected and rejected
        $duplicateRes = $this->postJson('/api/v1/admin/system/ip-access/ips', [
            'ip_address' => '2001:db8::5',
            'label' => 'Duplicate IPv6',
        ]);

        $duplicateRes->assertStatus(422)
            ->assertJsonPath('error_code', 'DUPLICATE_IP_ADDRESS');
    }

    /**
     * Scenario 12 & 13: Unauthorized users (ordinary peers and non-super circle admins) cannot retrieve or modify IP Access configuration.
     */
    public function test_12_and_13_unauthorized_users_cannot_view_or_modify_configuration(): void
    {
        // Unauthenticated
        $this->getJson('/api/v1/admin/system/ip-access')->assertStatus(401);

        // Ordinary Peer user
        $peer = $this->createActiveUser('ordinary.peer@peersunity.com');
        Sanctum::actingAs($peer);

        $this->getJson('/api/v1/admin/system/ip-access')->assertStatus(403);
        $this->postJson('/api/v1/admin/system/ip-access/ips', ['ip_address' => '203.0.113.5'])->assertStatus(403);
        $this->putJson('/api/v1/admin/system/ip-access/enforcement', ['ip_access_enabled' => true])->assertStatus(403);
        $this->deleteJson('/api/v1/admin/system/ip-access/ips/'.Str::uuid())->assertStatus(403);

        // Non-Super Circle Leader Admin without IP Access permission
        $circleAdmin = $this->createCircleLeaderAdminUser();
        Sanctum::actingAs($circleAdmin);

        $this->getJson('/api/v1/admin/system/ip-access')->assertStatus(403);
        $this->postJson('/api/v1/admin/system/ip-access/ips', ['ip_address' => '203.0.113.5'])->assertStatus(403);
        $this->putJson('/api/v1/admin/system/ip-access/enforcement', ['ip_access_enabled' => true])->assertStatus(403);
        $this->deleteJson('/api/v1/admin/system/ip-access/ips/'.Str::uuid())->assertStatus(403);
    }

    /**
     * Scenario 14 & Phase 7 Lockout Safeguards: Authorized Super Admin can manage configuration, with confirmation safeguards on empty allowlist and last-IP removal.
     */
    public function test_14_authorized_super_admin_can_manage_configuration_with_lockout_safeguards(): void
    {
        $superAdmin = $this->createSuperAdminUser('super.config@peersunity.com');
        Sanctum::actingAs($superAdmin);

        // Enabling enforcement with an empty allowlist without confirmation requires explicit confirmation (409)
        $unconfirmedEnable = $this->putJson('/api/v1/admin/system/ip-access/enforcement', [
            'ip_access_enabled' => true,
        ]);
        $unconfirmedEnable->assertStatus(409)
            ->assertJsonPath('error_code', 'CONFIRMATION_REQUIRED_EMPTY_ALLOWLIST')
            ->assertJsonPath('requires_confirmation', true);

        // Add an authorized IPv4 address
        $addResponse = $this->postJson('/api/v1/admin/system/ip-access/ips', [
            'ip_address' => '203.0.113.99',
            'label' => 'HQ Firewall',
        ]);
        $addResponse->assertStatus(201);
        $ipId = (string) $addResponse->json('data.ip.id');

        // Enable enforcement now that 1 IP is in the allowlist
        $enableResponse = $this->putJson('/api/v1/admin/system/ip-access/enforcement', [
            'ip_access_enabled' => true,
        ]);
        $enableResponse->assertOk()
            ->assertJsonPath('data.ip_access_enabled', true);

        // Removing the last IP while enforcement is enabled requires explicit confirmation (409)
        $unconfirmedDelete = $this->deleteJson("/api/v1/admin/system/ip-access/ips/{$ipId}");
        $unconfirmedDelete->assertStatus(409)
            ->assertJsonPath('error_code', 'CONFIRMATION_REQUIRED_LAST_IP_REMOVAL')
            ->assertJsonPath('requires_confirmation', true);

        // Removing the last IP with confirm_last_ip_removal=true succeeds AND keeps enforcement enabled
        $confirmedDelete = $this->deleteJson("/api/v1/admin/system/ip-access/ips/{$ipId}", [
            'confirm_last_ip_removal' => true,
        ]);
        $confirmedDelete->assertOk()
            ->assertJsonPath('data.remaining_ips_count', 0)
            ->assertJsonPath('data.ip_access_enabled', true);

        // Verify configuration endpoint reflects enforcement still enabled and empty allowlist warning
        $configResponse = $this->getJson('/api/v1/admin/system/ip-access');
        $configResponse->assertOk()
            ->assertJsonPath('data.ip_access_enabled', true)
            ->assertJsonPath('data.allowlist_count', 0);
        $this->assertNotEmpty($configResponse->json('data.warnings'));

        // Verify audit logs were recorded
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'ip_access.ip_added',
            'target_table' => 'admin_ip_allowlists',
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'ip_access.enforcement_enabled',
            'target_table' => 'admin_ip_access_settings',
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'ip_access.ip_removed',
            'target_table' => 'admin_ip_allowlists',
        ]);
    }

    /**
     * Scenario 15: Development configuration cannot affect production configuration.
     */
    public function test_15_development_configuration_cannot_affect_production_configuration(): void
    {
        $service = app(AdminIpAccessService::class);
        $actor = ['id' => (string) Str::uuid(), 'email' => 'admin@peersunity.com'];

        // Configure development: enable enforcement and add 203.0.113.11
        $service->addAllowedIp('203.0.113.11', 'Dev IP', $actor, '127.0.0.1', 'PHPUnit', 'development');
        $service->updateEnforcement(true, true, $actor, '127.0.0.1', 'PHPUnit', 'development');

        // Verify production remains untouched (disabled, 0 IPs)
        $this->assertTrue($service->isEnforcementEnabled('development'));
        $this->assertFalse($service->isEnforcementEnabled('production'));
        $this->assertCount(1, $service->listAllowedIps('development'));
        $this->assertCount(0, $service->listAllowedIps('production'));
        $this->assertTrue($service->isIpAuthorized('203.0.113.11', 'development'));
        $this->assertFalse($service->isIpAuthorized('203.0.113.11', 'production'));
    }

    /**
     * Scenario 16: Forged X-Forwarded-For / Forwarded headers cannot bypass IP restrictions under the verified proxy configuration.
     */
    public function test_16_forged_forwarding_headers_cannot_bypass_ip_restrictions(): void
    {
        $this->createActiveUser('test16@peersunity.com', 'SecretPass123!');

        AdminIpAccessSetting::query()->create([
            'environment' => 'testing',
            'ip_access_enabled' => true,
        ]);

        AdminIpAllowlist::query()->create([
            'environment' => 'testing',
            'ip_address' => '203.0.113.50',
            'ip_version' => 4,
            'is_active' => true,
        ]);

        // Case A: Untrusted client (198.51.100.200) sends forged X-Forwarded-For and Forwarded headers directly
        $directForgedResponse = $this->withServerVariables([
            'REMOTE_ADDR' => '198.51.100.200',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
            'HTTP_FORWARDED' => 'for=203.0.113.50',
        ])->postJson('/api/v1/auth/login', [
            'email' => 'test16@peersunity.com',
            'password' => 'SecretPass123!',
        ]);

        $directForgedResponse->assertStatus(403)
            ->assertJsonPath('error_code', 'IP_NOT_AUTHORIZED_FOR_PASSWORD_LOGIN');

        // Case B: Request arrives via trusted local reverse proxy (127.0.0.1) which appends real client IP (198.51.100.200) after client's forged IP (203.0.113.50)
        $proxiedForgedResponse = $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.50, 198.51.100.200',
        ])->postJson('/api/v1/auth/login', [
            'email' => 'test16@peersunity.com',
            'password' => 'SecretPass123!',
        ]);

        $proxiedForgedResponse->assertStatus(403)
            ->assertJsonPath('error_code', 'IP_NOT_AUTHORIZED_FOR_PASSWORD_LOGIN');

        // Case C: Request arrives via trusted local reverse proxy (127.0.0.1) for genuinely authorized client IP (203.0.113.50)
        $proxiedGenuineResponse = $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
        ])->postJson('/api/v1/auth/login', [
            'email' => 'test16@peersunity.com',
            'password' => 'SecretPass123!',
        ]);

        $proxiedGenuineResponse->assertOk()
            ->assertJsonPath('success', true);
    }

    /**
     * Scenario 17: Existing login throttling and account-status checks (suspended, pending) still work.
     */
    public function test_17_login_throttling_and_account_status_checks_remain_intact(): void
    {
        $suspendedUser = $this->createActiveUser('suspended@peersunity.com', 'SecretPass123!');
        $suspendedUser->membership_status = 'suspended';
        $suspendedUser->save();

        // Suspended account check
        $suspendedRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'suspended@peersunity.com',
            'password' => 'SecretPass123!',
        ]);
        $suspendedRes->assertStatus(403)
            ->assertJsonPath('message', 'Account is suspended');

        // Password brute-force throttling check (5 failed attempts -> 6th attempt returns 429)
        $activeUser = $this->createActiveUser('throttled@peersunity.com', 'SecretPass123!');
        RateLimiter::clear('login-password:throttled@peersunity.com|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
                ->postJson('/api/v1/auth/login', [
                    'email' => 'throttled@peersunity.com',
                    'password' => 'WrongPassword!',
                ])
                ->assertStatus(401);
        }

        $throttledRes = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson('/api/v1/auth/login', [
                'email' => 'throttled@peersunity.com',
                'password' => 'SecretPass123!',
            ]);

        $throttledRes->assertStatus(429)
            ->assertJsonPath('error_code', 'RATE_LIMIT_EXCEEDED');
    }

    /**
     * Scenario 18: The public login-policy endpoint never exposes the allowlist and sets anti-cache headers.
     */
    public function test_18_public_policy_endpoint_never_exposes_allowlist_and_sets_no_store_headers(): void
    {
        AdminIpAccessSetting::query()->create([
            'environment' => 'testing',
            'ip_access_enabled' => true,
        ]);

        AdminIpAllowlist::query()->create([
            'environment' => 'testing',
            'ip_address' => '203.0.113.77',
            'ip_version' => 4,
            'label' => 'Secret Office IP',
            'is_active' => true,
        ]);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
            ->getJson('/api/v1/auth/login-policy');

        $response->assertOk()
            ->assertJsonMissing(['allowlist'])
            ->assertJsonMissing(['ips'])
            ->assertJsonMissing(['203.0.113.77'])
            ->assertJsonMissing(['Secret Office IP']);

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
    }
}
