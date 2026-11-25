<?php

declare(strict_types=1);

namespace Tests\Feature\Leader;

use App\Leader\Models\LeaderReport;
use App\Models\Circle;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeaderHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Circle $circleA;

    private Circle $circleB;

    private User $superAdmin;

    private User $circleDirectorA;

    private User $secretaryA;

    private User $peerA;

    private User $peerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->circleA = Circle::factory()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Alpha Circle',
        ]);

        $this->circleB = Circle::factory()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Beta Circle',
        ]);

        $this->superAdmin = User::factory()->create([
            'id' => (string) Str::uuid(),
            'email' => 'superadmin-leader-test@example.com',
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->circleDirectorA = User::factory()->create([
            'id' => (string) Str::uuid(),
            'email' => 'cd-alpha@example.com',
            'status' => 'active',
        ]);

        $this->secretaryA = User::factory()->create([
            'id' => (string) Str::uuid(),
            'email' => 'sec-alpha@example.com',
            'status' => 'active',
        ]);

        $this->peerA = User::factory()->create([
            'id' => (string) Str::uuid(),
            'email' => 'peer-alpha@example.com',
            'status' => 'active',
        ]);

        $this->peerB = User::factory()->create([
            'id' => (string) Str::uuid(),
            'email' => 'peer-beta@example.com',
            'status' => 'active',
        ]);

        DB::table('circles')->where('id', $this->circleA->id)->update([
            'director_user_id' => $this->circleDirectorA->id,
            'secretary_user_id' => $this->secretaryA->id,
        ]);

        foreach ([
            [$this->circleA->id, $this->circleDirectorA->id],
            [$this->circleA->id, $this->secretaryA->id],
            [$this->circleA->id, $this->peerA->id],
            [$this->circleB->id, $this->peerB->id],
        ] as [$cId, $uId]) {
            DB::table('circle_members')->insert([
                'id' => (string) Str::uuid(),
                'circle_id' => $cId,
                'user_id' => $uId,
                'status' => 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    // ── REM-01: Leader OTP Hardening ────────────────────────────────────────

    public function test_leader_verify_otp_rejects_hardcoded_123456_without_valid_otp_record(): void
    {
        $this->postJson('/api/v1/leader/auth/verify-otp', [
            'email_or_phone' => $this->circleDirectorA->email,
            'otp' => '123456',
        ])->assertStatus(422);
    }

    public function test_leader_verify_otp_succeeds_with_valid_database_otp(): void
    {
        OtpCode::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->circleDirectorA->id,
            'email' => $this->circleDirectorA->email,
            'channel' => 'email',
            'code' => Hash::make('654321'),
            'purpose' => 'login_otp',
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
        ]);

        $this->postJson('/api/v1/leader/auth/verify-otp', [
            'email_or_phone' => $this->circleDirectorA->email,
            'otp' => '654321',
        ])->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // ── REM-03: Leader Capability Middleware Enforcement ────────────────────

    public function test_secretary_cannot_access_restricted_role_finance_notification_or_moderation_routes(): void
    {
        $token = $this->secretaryA->createToken('sec-test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/leader/roles/matrix')->assertStatus(403);
        $this->withToken($token)->putJson('/api/v1/leader/roles/matrix', [
            'role_id' => 'secretary',
            'enabled_capabilities' => ['manage_roles'],
        ])->assertStatus(403);

        $this->withToken($token)->postJson('/api/v1/leader/finance/transactions/record-offline', [
            'amount' => 1000,
        ])->assertStatus(403);
        $this->withToken($token)->putJson('/api/v1/leader/finance/commission-rates', [])->assertStatus(403);

        $this->withToken($token)->getJson('/api/v1/leader/admin/notifications/config')->assertStatus(403);
        $this->withToken($token)->postJson('/api/v1/leader/admin/notifications/send', [
            'title' => 'Unauthorized',
            'message' => 'Should be blocked',
        ])->assertStatus(403);

        $this->withToken($token)->getJson('/api/v1/leader/pending-requests')->assertStatus(403);
        $this->withToken($token)->postJson('/api/v1/leader/reports', [
            'circle_id' => $this->circleA->id,
        ])->assertStatus(403);

        $fakeId = (string) Str::uuid();
        $this->withToken($token)
            ->deleteJson("/api/v1/leader/members/{$this->peerA->id}/posts/{$fakeId}")
            ->assertStatus(403);
    }

    public function test_super_admin_can_access_role_matrix_and_notification_config(): void
    {
        $token = $this->superAdmin->createToken('sa-test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/leader/roles/matrix')->assertStatus(200);
        $this->withToken($token)->getJson('/api/v1/leader/admin/notifications/config')->assertStatus(200);
    }

    // ── REM-04: Organizational Scope & IDOR Protection ──────────────────────

    public function test_circle_director_cannot_bypass_scope_via_global_or_cross_circle_params(): void
    {
        $token = $this->circleDirectorA->createToken('cd-test')->plainTextToken;

        // ?scope=global must not leak peerB
        $resp = $this->withToken($token)
            ->getJson('/api/v1/leader/peers?scope=global&per_page=100')
            ->assertStatus(200);
        $ids = collect($resp->json('data'))->pluck('id')->all();
        $this->assertNotContains($this->peerB->id, $ids);

        // Explicit cross-circle query must return 403
        $this->withToken($token)
            ->getJson("/api/v1/leader/peers?circle_id={$this->circleB->id}")
            ->assertStatus(403);

        // Direct peer UUID IDOR must return 403
        $this->withToken($token)
            ->getJson("/api/v1/leader/peers/{$this->peerB->id}")
            ->assertStatus(403);

        // Direct circle UUID IDOR must return 403
        $this->withToken($token)
            ->getJson("/api/v1/leader/teams/circles/{$this->circleB->id}")
            ->assertStatus(403);
        $this->withToken($token)
            ->getJson("/api/v1/leader/teams/circles/{$this->circleB->id}/peers")
            ->assertStatus(403);
    }

    public function test_cross_circle_report_and_unauthenticated_report_pdf_are_blocked(): void
    {
        $reportB = LeaderReport::query()->create([
            'id' => (string) Str::uuid(),
            'circle_id' => $this->circleB->id,
            'submitted_by_user_id' => $this->peerB->id,
            'report_type' => 'Monthly',
            'period' => 'Nov 2026',
            'status' => 'Under Review',
        ]);

        $tokenA = $this->circleDirectorA->createToken('cd-test')->plainTextToken;

        $this->withToken($tokenA)
            ->getJson("/api/v1/leader/reports?circle_id={$this->circleB->id}")
            ->assertStatus(403);

        $this->withToken($tokenA)
            ->get("/api/v1/leader/reports/{$reportB->id}/file")
            ->assertStatus(403);

        $this->get("/api/v1/files/{$reportB->id}/download")
            ->assertStatus(401);
    }

    public function test_leader_contacts_never_leak_other_users_contacts(): void
    {
        if (! Schema::hasTable('contact_posts')) {
            $this->markTestSkipped('contact_posts table not present.');
        }

        DB::table('contact_posts')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $this->peerB->id,
            'full_name' => 'Private Prospect',
            'phone' => '9999999999',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tokenA = $this->circleDirectorA->createToken('cd-test')->plainTextToken;

        $resp = $this->withToken($tokenA)
            ->getJson('/api/v1/leader/teams/contacts')
            ->assertStatus(200);

        $this->assertSame([], $resp->json('data'));
    }
}
