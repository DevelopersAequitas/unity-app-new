<?php

declare(strict_types=1);

namespace Tests\Feature\Leader;

use App\Models\AdminAuditLog;
use App\Models\Circle;
use App\Models\Impact;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeaderLifeImpactAndPeerProfileTest extends TestCase
{
    use RefreshDatabase;

    private Circle $circleA;

    private Circle $circleB;

    private User $superAdmin;

    private User $dedA;

    private User $dedB;

    private User $chairA;

    private User $peerA;

    private User $peerB;

    private Impact $impactCircleA;

    private Impact $impactCircleB;

    protected function setUp(): void
    {
        parent::setUp();

        $districtAId = (string) Str::uuid();
        $districtBId = (string) Str::uuid();

        if (DB::getSchemaBuilder()->hasTable('districts')) {
            DB::table('districts')->insert([
                [
                    'id' => $districtAId,
                    'name' => 'Ahmedabad District',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => $districtBId,
                    'name' => 'Surat District',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        $this->circleA = Circle::factory()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Ahmedabad Titans',
            'district_id' => DB::getSchemaBuilder()->hasColumn('circles', 'district_id') ? $districtAId : null,
        ]);

        $this->circleB = Circle::factory()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Surat Icons',
            'district_id' => DB::getSchemaBuilder()->hasColumn('circles', 'district_id') ? $districtBId : null,
        ]);

        $this->superAdmin = User::factory()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Global',
            'last_name' => 'Founder',
            'email' => 'founder-li@example.com',
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->dedA = User::factory()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'DED',
            'last_name' => 'Ahmedabad',
            'email' => 'ded-a@example.com',
            'status' => 'active',
        ]);

        $this->dedB = User::factory()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'DED',
            'last_name' => 'Surat',
            'email' => 'ded-b@example.com',
            'status' => 'active',
        ]);

        $this->chairA = User::factory()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Chair',
            'last_name' => 'Ahmedabad',
            'email' => 'chair-a@example.com',
            'status' => 'active',
        ]);

        $this->peerA = User::factory()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Rohan',
            'last_name' => 'Patel',
            'email' => 'rohan-a@example.com',
            'phone' => '+919876543210',
            'status' => 'active',
        ]);

        $this->peerB = User::factory()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Meera',
            'last_name' => 'Desai',
            'email' => 'meera-b@example.com',
            'status' => 'active',
        ]);

        $dedRole = Role::query()->firstOrCreate(
            ['key' => 'ded'],
            ['id' => (string) Str::uuid(), 'name' => 'DED', 'description' => 'District Executive Director']
        );
        $chairRole = Role::query()->firstOrCreate(
            ['key' => 'circle_chair'],
            ['id' => (string) Str::uuid(), 'name' => 'Circle Chair', 'description' => 'Circle Chair']
        );

        DB::table('circles')->where('id', $this->circleA->id)->update([
            'ded_user_id' => $this->dedA->id,
            'chair_user_id' => $this->chairA->id,
        ]);
        DB::table('circles')->where('id', $this->circleB->id)->update([
            'ded_user_id' => $this->dedB->id,
        ]);

        foreach ([
            [$this->circleA->id, $this->dedA->id, 'ded', $dedRole->id],
            [$this->circleA->id, $this->chairA->id, 'circle_chair', $chairRole->id],
            [$this->circleA->id, $this->peerA->id, 'member', null],
            [$this->circleB->id, $this->dedB->id, 'ded', $dedRole->id],
            [$this->circleB->id, $this->peerB->id, 'member', null],
        ] as [$cId, $uId, $roleKey, $roleId]) {
            DB::table('circle_members')->insert([
                'id' => (string) Str::uuid(),
                'circle_id' => $cId,
                'user_id' => $uId,
                'role' => $roleKey,
                'role_id' => $roleId,
                'status' => 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->impactCircleA = Impact::query()->create([
            'user_id' => $this->peerA->id,
            'impacted_peer_id' => $this->chairA->id,
            'impact_date' => now()->toDateString(),
            'action' => 'Business Mentorship',
            'story_to_share' => 'Helped streamline B2B onboarding for 3 founders.',
            'life_impacted' => 3,
            'requires_leadership_approval' => true,
            'status' => 'pending',
        ]);

        $this->impactCircleB = Impact::query()->create([
            'user_id' => $this->peerB->id,
            'impacted_peer_id' => $this->dedB->id,
            'impact_date' => now()->toDateString(),
            'action' => 'Community Initiative',
            'story_to_share' => 'Organized vocational training drive.',
            'life_impacted' => 10,
            'requires_leadership_approval' => true,
            'status' => 'pending',
        ]);
    }

    public function test_ded_can_approve_in_scope_pending_impact_and_audit_log_is_written(): void
    {
        $token = $this->dedA->createToken('ded-a')->plainTextToken;

        $detail = $this->withToken($token)
            ->getJson("/api/v1/leader/impacts/{$this->impactCircleA->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.permissions.can_approve', true);

        $this->assertNotEmpty($detail->json('data.verification_history'));

        $this->withToken($token)
            ->postJson("/api/v1/leader/impacts/{$this->impactCircleA->id}/approve", [
                'remarks' => 'Verified with circle chair',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved');

        $this->impactCircleA->refresh();
        $this->assertSame('approved', $this->impactCircleA->status);
        $this->assertSame($this->dedA->id, $this->impactCircleA->approved_by);

        $this->assertTrue(
            AdminAuditLog::query()
                ->where('target_table', 'impacts')
                ->where('target_id', $this->impactCircleA->id)
                ->exists()
        );

        // Second approval attempt must return 409 conflict
        $this->withToken($token)
            ->postJson("/api/v1/leader/impacts/{$this->impactCircleA->id}/approve", [])
            ->assertStatus(409);
    }

    public function test_ded_cannot_review_out_of_scope_impact_and_chair_cannot_review_any(): void
    {
        $dedAToken = $this->dedA->createToken('ded-a')->plainTextToken;
        $chairToken = $this->chairA->createToken('chair-a')->plainTextToken;

        // DED A out-of-scope for Circle B -> 403
        $this->withToken($dedAToken)
            ->postJson("/api/v1/leader/impacts/{$this->impactCircleB->id}/approve")
            ->assertStatus(403);

        // Circle Chair cannot approve or reject Life Impacts -> 403
        $this->withToken($chairToken)
            ->postJson("/api/v1/leader/impacts/{$this->impactCircleA->id}/approve")
            ->assertStatus(403);

        $this->withToken($chairToken)
            ->postJson("/api/v1/leader/impacts/{$this->impactCircleA->id}/reject", [
                'reason' => 'Not authorized anyway',
            ])
            ->assertStatus(403);
    }

    public function test_super_admin_rejection_requires_reason_and_blocks_self_approval(): void
    {
        $saToken = $this->superAdmin->createToken('sa')->plainTextToken;

        // Missing or short reason returns 422
        $this->withToken($saToken)
            ->postJson("/api/v1/leader/impacts/{$this->impactCircleB->id}/reject", [
                'reason' => 'no',
            ])
            ->assertStatus(422);

        $this->withToken($saToken)
            ->postJson("/api/v1/leader/impacts/{$this->impactCircleB->id}/reject", [
                'reason' => 'Insufficient beneficiary proof uploaded',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.verification.review_remarks', 'Insufficient beneficiary proof uploaded');

        // Self-approval by DED A on own impact must be denied
        $ownImpact = Impact::query()->create([
            'user_id' => $this->dedA->id,
            'impact_date' => now()->toDateString(),
            'action' => 'Self Submitted',
            'story_to_share' => 'Self submitted story',
            'life_impacted' => 1,
            'requires_leadership_approval' => true,
            'status' => 'pending',
        ]);

        $dedAToken = $this->dedA->createToken('ded-a')->plainTextToken;
        $this->withToken($dedAToken)
            ->postJson("/api/v1/leader/impacts/{$ownImpact->id}/approve")
            ->assertStatus(403);
    }

    public function test_founder_peer_profile_returns_real_sections_without_fabricated_fallbacks_and_excludes_member_role_from_leadership(): void
    {
        $saToken = $this->superAdmin->createToken('sa')->plainTextToken;

        $res = $this->withToken($saToken)
            ->getJson("/api/v1/leader/peers/{$this->peerA->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $data = $res->json('data');

        // Zero fake fallback strings
        $this->assertNotSame('Apex Dynamics', $data['company'] ?? '');
        $this->assertNotSame('94%', $data['attendance_percentage'] ?? '');

        // Ordinary circle_members role ('member') must NOT populate leadership positions
        $this->assertFalse((bool) ($data['leadership']['has_leadership_history'] ?? false));
        $this->assertEmpty($data['leadership']['current_positions'] ?? []);

        // Chair A MUST have Circle Chair in current_positions
        $chairRes = $this->withToken($saToken)
            ->getJson("/api/v1/leader/peers/{$this->chairA->id}")
            ->assertStatus(200);

        $this->assertTrue((bool) $chairRes->json('data.leadership.has_leadership_history'));
        $this->assertNotEmpty($chairRes->json('data.leadership.current_positions'));
    }
}
