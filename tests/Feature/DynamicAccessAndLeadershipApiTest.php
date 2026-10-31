<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\Role;
use App\Models\User;
use App\Shared\Authorization\FieldAccessService;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DynamicAccessAndLeadershipApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Parse DB credentials from .env directly without mutating global env state
        $envVars = Dotenv::parse((string) file_get_contents(base_path('.env')));

        config([
            'database.connections.pgsql.database' => $envVars['DB_DATABASE'] ?? 'unityappbackend',
            'database.connections.pgsql.username' => $envVars['DB_USERNAME'] ?? 'postgres',
            'database.connections.pgsql.password' => $envVars['DB_PASSWORD'] ?? '',
        ]);
        DB::purge('pgsql');
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function createTestUser(string $membershipStatus = 'free_peer', ?string $emailPrefix = null): User
    {
        $prefix = $emailPrefix ?? 'peer.'.Str::lower(Str::random(6));
        $id = (string) Str::uuid();

        DB::table('users')->insert([
            'id' => $id,
            'email' => $prefix.'@peersunity.test',
            'phone' => '+9198'.random_int(10000000, 99999999),
            'password_hash' => bcrypt('password123'),
            'first_name' => 'Test',
            'last_name' => ucfirst($prefix),
            'display_name' => 'Test '.ucfirst($prefix),
            'designation' => 'CEO',
            'company_name' => 'Unity Ventures',
            'membership_status' => $membershipStatus,
            'coins_balance' => 250,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }

    private function createTestCircle(string $name, ?string $founderUserId = null, array $calendarLeadership = []): Circle
    {
        $id = (string) Str::uuid();

        DB::table('circles')->insert([
            'id' => $id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'status' => 'active',
            'founder_user_id' => $founderUserId,
            'calendar' => json_encode(['leadership' => $calendarLeadership]),
            'industry_tags' => json_encode(['Technology']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Circle::query()->findOrFail($id);
    }

    public function test_access_system_bootstrap_and_role_crud_lifecycle(): void
    {
        $adminUser = $this->createTestUser('circle_peer', 'superadmin');

        $globalRole = Role::query()->firstOrCreate(
            ['key' => 'global_admin'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Global Admin',
                'role_type' => 'system',
                'scope_rule' => 'global',
                'status' => 'active',
                'is_assignable' => true,
            ]
        );

        DB::table('admin_users')->insert([
            'id' => $adminUser->id,
            'name' => $adminUser->display_name,
            'email' => $adminUser->email,
            'role' => 'global_admin',
            'password' => bcrypt('password123'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_user_roles')->insert([
            'user_id' => $adminUser->id,
            'role_id' => $globalRole->id,
            'permission_type' => 'edit',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($adminUser);

        // 1. Bootstrap endpoint
        $bootstrapRes = $this->getJson('/api/v1/admin/access-system/bootstrap');
        $bootstrapRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current_user_access.is_global', true);

        // 2. Create custom role without code changes
        $customRoleKey = 'regional_growth_mentor_'.Str::lower(Str::random(4));
        $createRes = $this->postJson('/api/v1/admin/access/roles', [
            'role_key' => $customRoleKey,
            'name' => 'Regional Growth Mentor',
            'description' => 'Mentors multiple circles in growth',
            'default_scope_type' => 'multi_circle',
            'hierarchy_level' => 42,
        ]);
        $createRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.key', $customRoleKey);

        $createdRoleId = (string) $createRes->json('data.id');

        // 3. Get Role Details
        $showRes = $this->getJson("/api/v1/admin/access/roles/{$createdRoleId}");
        $this->assertSame(200, $showRes->status(), 'Show Role failed: '.$showRes->getContent());
        $showRes->assertOk()
            ->assertJsonPath('data.id', $createdRoleId);

        // 4. Assign custom role to a user on a circle via organizational-assignments API
        $targetPeer = $this->createTestUser('circle_peer', 'mentor_candidate');
        $targetCircle = $this->createTestCircle('Mentored Circle');
        $assignRes = $this->postJson('/api/v1/admin/organizational-assignments', [
            'user_id' => $targetPeer->id,
            'role_id' => $createdRoleId,
            'scope_type' => 'multi_circle',
            'circle_id' => $targetCircle->id,
        ]);
        $assignRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user_id', $targetPeer->id)
            ->assertJsonPath('data.role_key', $customRoleKey);

        // 4b. Verify Effective Access and Organizational Scope endpoints for assigned user
        $effectiveRes = $this->getJson("/api/v1/admin/access/users/{$targetPeer->id}/effective-access");
        $effectiveRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user_id', $targetPeer->id);

        $scopeSummaryRes = $this->getJson("/api/v1/admin/users/{$targetPeer->id}/organizational-scope");
        $scopeSummaryRes->assertOk()
            ->assertJsonPath('success', true);
        $this->assertContains($targetCircle->id, $scopeSummaryRes->json('data.circle_ids'));

        // 4c. Verify Scope Policies and Field Access endpoints
        $policiesRes = $this->getJson("/api/v1/admin/access/roles/{$createdRoleId}/scope-policies");
        $policiesRes->assertOk()->assertJsonPath('success', true);

        $fieldAccessRes = $this->getJson("/api/v1/admin/access/roles/{$createdRoleId}/field-access");
        $fieldAccessRes->assertOk()->assertJsonPath('success', true);

        // 5. Clone Role
        $cloneKey = $customRoleKey.'_clone';
        $cloneRes = $this->postJson("/api/v1/admin/access/roles/{$createdRoleId}/clone", [
            'role_key' => $cloneKey,
            'name' => 'Cloned Regional Mentor',
        ]);
        $cloneRes->assertStatus(201)
            ->assertJsonPath('data.key', $cloneKey);

        $clonedRoleId = (string) $cloneRes->json('data.id');

        // 6. Delete Cloned Custom Role
        $delRes = $this->deleteJson("/api/v1/admin/access/roles/{$clonedRoleId}");
        $delRes->assertOk()
            ->assertJsonPath('data.deleted', true);

        // 7. System role deletion is blocked
        $sysDelRes = $this->deleteJson("/api/v1/admin/access/roles/{$globalRole->id}");
        $sysDelRes->assertStatus(422);
    }

    public function test_chair_sees_only_assigned_circle_and_is_blocked_from_other_circles(): void
    {
        $chairUser = $this->createTestUser('circle_peer', 'chair_alpha');
        $peerInCircleA = $this->createTestUser('free_peer', 'peer_in_a');
        $peerInCircleB = $this->createTestUser('Only Unity Peer', 'peer_in_b');

        $circleA = $this->createTestCircle('Alpha Circle', null, [
            'chair_user_id' => $chairUser->id,
        ]);
        $circleB = $this->createTestCircle('Beta Circle');

        DB::table('circle_members')->insert([
            [
                'id' => (string) Str::uuid(),
                'circle_id' => $circleA->id,
                'user_id' => $chairUser->id,
                'role' => 'chair',
                'status' => 'approved',
                'substitute_count' => 0,
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'circle_id' => $circleA->id,
                'user_id' => $peerInCircleA->id,
                'role' => 'member',
                'status' => 'approved',
                'substitute_count' => 0,
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'circle_id' => $circleB->id,
                'user_id' => $peerInCircleB->id,
                'role' => 'member',
                'status' => 'approved',
                'substitute_count' => 0,
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Sanctum::actingAs($chairUser);

        // 1. /api/v1/leadership/me resolves chair role & Circle A scope while preserving membership_status
        $meRes = $this->getJson('/api/v1/leadership/me');
        $meRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.membership_status', 'circle_peer')
            ->assertJsonPath('data.user.is_global', false);

        $scopedCircleIds = $meRes->json('data.organizational_scope.circle_ids');
        $this->assertContains($circleA->id, $scopedCircleIds);
        $this->assertNotContains($circleB->id, $scopedCircleIds);

        // 2. /api/v1/leadership/circles returns only Circle A
        $circlesRes = $this->getJson('/api/v1/leadership/circles');
        $circlesRes->assertOk();
        $returnedCircleIds = collect($circlesRes->json('data.items'))->pluck('id')->all();
        $this->assertContains($circleA->id, $returnedCircleIds);
        $this->assertNotContains($circleB->id, $returnedCircleIds);

        // 3. Direct access to Circle B returns 403 FORBIDDEN (IDOR protection)
        $forbiddenCircleRes = $this->getJson("/api/v1/leadership/circles/{$circleB->id}");
        $forbiddenCircleRes->assertStatus(403);

        // 4. Direct access to Peer in Circle A succeeds; Peer in Circle B returns 403 FORBIDDEN
        $allowedPeerRes = $this->getJson("/api/v1/leadership/peers/{$peerInCircleA->id}");
        $allowedPeerRes->assertOk()
            ->assertJsonPath('data.id', $peerInCircleA->id);

        $forbiddenPeerRes = $this->getJson("/api/v1/leadership/peers/{$peerInCircleB->id}");
        $forbiddenPeerRes->assertStatus(403);

        // 5. Scoped dashboard, activities, events, and reports list endpoints succeed
        $this->getJson('/api/v1/leadership/dashboard')->assertOk()->assertJsonPath('success', true);
        $this->getJson('/api/v1/leadership/activities')->assertOk()->assertJsonPath('success', true);
        $this->getJson('/api/v1/leadership/events')->assertOk()->assertJsonPath('success', true);
        $this->getJson('/api/v1/leadership/reports')->assertOk()->assertJsonPath('success', true);
    }

    public function test_circle_founder_with_multiple_circles_sees_union_of_assigned_circles(): void
    {
        $founderUser = $this->createTestUser('multi_circle_peer', 'founder_multi');
        $circle1 = $this->createTestCircle('Founder Circle 1', $founderUser->id);
        $circle2 = $this->createTestCircle('Founder Circle 2', $founderUser->id);
        $unassignedCircle = $this->createTestCircle('Unassigned Circle 3');

        Sanctum::actingAs($founderUser);

        $circlesRes = $this->getJson('/api/v1/leadership/circles');
        $circlesRes->assertOk();
        $ids = collect($circlesRes->json('data.items'))->pluck('id')->all();

        $this->assertContains($circle1->id, $ids);
        $this->assertContains($circle2->id, $ids);
        $this->assertNotContains($unassignedCircle->id, $ids);

        // Reports execution scoped to founder's circles
        $reportRes = $this->getJson('/api/v1/leadership/reports/circle_summary_report');
        $reportRes->assertOk()
            ->assertJsonPath('success', true);
        $reportCircleIds = collect($reportRes->json('data.rows'))->pluck('circle_id')->all();
        $this->assertContains($circle1->id, $reportCircleIds);
        $this->assertContains($circle2->id, $reportCircleIds);
        $this->assertNotContains($unassignedCircle->id, $reportCircleIds);
    }

    public function test_membership_status_is_preserved_and_non_leader_is_blocked_from_leadership_apis(): void
    {
        $freeTrialPeer = $this->createTestUser('free_trial_peer', 'trial_member');

        Sanctum::actingAs($freeTrialPeer);

        // Non-leader peer with free_trial_peer membership is denied access to leadership endpoints
        $res = $this->getJson('/api/v1/leadership/me');
        $res->assertStatus(403);
    }

    public function test_field_access_service_masks_phone_and_email_correctly(): void
    {
        $fieldService = app(FieldAccessService::class);

        $this->assertSame('+91******3210', $fieldService->applyMask('+919876543210', 'partial_phone'));
        $this->assertSame('ar***@peersunity.com', $fieldService->applyMask('arjun@peersunity.com', 'partial_email'));
        $this->assertSame('[REDACTED]', $fieldService->applyMask('Sensitive Value', 'redacted'));
    }
}
