<?php

declare(strict_types=1);

namespace Tests\Feature\Leader;

use App\Models\Circle;
use App\Models\MilestoneBadge;
use App\Models\User;
use App\Models\UserMilestoneBadge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeaderMember360Test extends TestCase
{
    use RefreshDatabase;

    private User $leader;

    private User $member;

    private Circle $circle;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a circle
        $this->circle = Circle::factory()->create([
            'id' => Str::uuid()->toString(),
            'name' => 'Test Circle',
        ]);

        // Create a leader user with a leadership role stored in the DB
        $this->leader = User::factory()->create([
            'id' => Str::uuid()->toString(),
            'status' => 'active',
        ]);

        // Assign leader as chair of the circle
        DB::table('circles')
            ->where('id', $this->circle->id)
            ->update(['chair_user_id' => $this->leader->id]);

        // Join leader to circle via circle_members
        DB::table('circle_members')->insert([
            'id' => Str::uuid()->toString(),
            'circle_id' => $this->circle->id,
            'user_id' => $this->leader->id,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create a member user
        $this->member = User::factory()->create([
            'id' => Str::uuid()->toString(),
            'status' => 'active',
        ]);

        // Join member to the same circle
        DB::table('circle_members')->insert([
            'id' => Str::uuid()->toString(),
            'circle_id' => $this->circle->id,
            'user_id' => $this->member->id,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create a Sanctum token for the leader
        $this->token = $this->leader->createToken('leader-test')->plainTextToken;
    }

    // ────────────────────────────────────────────────────────────────────────
    // Authentication Tests
    // ────────────────────────────────────────────────────────────────────────

    /** @test */
    public function it_rejects_unauthenticated_requests(): void
    {
        $this->getJson("/api/v1/leader/members/{$this->member->id}")
            ->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Member 360° Profile
    // ────────────────────────────────────────────────────────────────────────

    /** @test */
    public function it_returns_member_360_profile(): void
    {
        $response = $this->withToken($this->token)
            ->getJson("/api/v1/leader/members/{$this->member->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Member profile retrieved successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'circle_name',
                    'summary' => [
                        'posts', 'creatives', 'badges',
                        'referrals_given', 'referrals_received',
                        'p2p_meetings', 'business_deals', 'life_impacts',
                        'event_registrations', 'coins_balance',
                    ],
                ],
            ]);
    }

    /** @test */
    public function it_returns_404_for_non_existent_member(): void
    {
        $fakeId = Str::uuid()->toString();

        $this->withToken($this->token)
            ->getJson("/api/v1/leader/members/{$fakeId}")
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error_code' => 'RESOURCE_NOT_FOUND',
            ]);
    }

    /** @test */
    public function it_returns_404_for_member_outside_leader_scope(): void
    {
        // Create a member that is NOT in the leader's circle
        $outsideMember = User::factory()->create([
            'id' => Str::uuid()->toString(),
            'status' => 'active',
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v1/leader/members/{$outsideMember->id}")
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error_code' => 'RESOURCE_NOT_FOUND',
            ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Activity Feed
    // ────────────────────────────────────────────────────────────────────────

    /** @test */
    public function it_returns_member_activities_feed(): void
    {
        $response = $this->withToken($this->token)
            ->getJson("/api/v1/leader/members/{$this->member->id}/activities");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
    }

    /** @test */
    public function it_returns_empty_activities_for_new_member(): void
    {
        $response = $this->withToken($this->token)
            ->getJson("/api/v1/leader/members/{$this->member->id}/activities");

        $response->assertStatus(200)
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    /** @test */
    public function it_filters_activities_by_type(): void
    {
        $this->withToken($this->token)
            ->getJson("/api/v1/leader/members/{$this->member->id}/activities?activity_type=referral")
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    /** @test */
    public function it_filters_activities_by_date_range(): void
    {
        $this->withToken($this->token)
            ->getJson("/api/v1/leader/members/{$this->member->id}/activities?from_date=2026-01-01&to_date=2026-12-31")
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    /** @test */
    public function it_paginates_activities(): void
    {
        $response = $this->withToken($this->token)
            ->getJson("/api/v1/leader/members/{$this->member->id}/activities?page=1&per_page=5");

        $response->assertStatus(200)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 5);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Member Posts
    // ────────────────────────────────────────────────────────────────────────

    /** @test */
    public function it_returns_member_posts(): void
    {
        // Create a test post
        DB::table('posts')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $this->member->id,
            'content_text' => 'Test post content',
            'visibility' => 'public',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v1/leader/members/{$this->member->id}/posts");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Member posts retrieved successfully.'])
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'content', 'created_at'],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);

        $this->assertEquals(1, $response->json('meta.total'));
    }

    /** @test */
    public function it_returns_empty_posts_for_new_member(): void
    {
        $response = $this->withToken($this->token)
            ->getJson("/api/v1/leader/members/{$this->member->id}/posts");

        $response->assertStatus(200)
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Member Badges
    // ────────────────────────────────────────────────────────────────────────

    /** @test */
    public function it_returns_member_badges(): void
    {
        // Create a milestone badge
        $badge = MilestoneBadge::create([
            'id' => Str::uuid()->toString(),
            'type' => MilestoneBadge::TYPE_LIFE_IMPACT,
            'title' => 'Impact Starter',
            'required_count' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Award it to the member
        UserMilestoneBadge::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $this->member->id,
            'badge_id' => $badge->id,
            'milestone_type' => 'LIFE_IMPACT',
            'achieved_count' => 1,
            'status' => UserMilestoneBadge::STATUS_EARNED,
            'earned_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v1/leader/members/{$this->member->id}/badges");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'badge_id', 'badge_name', 'earned_at'],
                ],
                'meta',
            ]);

        $this->assertEquals(1, $response->json('meta.total'));
    }

    // ────────────────────────────────────────────────────────────────────────
    // Member Creatives
    // ────────────────────────────────────────────────────────────────────────

    /** @test */
    public function it_returns_member_creatives(): void
    {
        $response = $this->withToken($this->token)
            ->getJson("/api/v1/leader/members/{$this->member->id}/creatives");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Member Events
    // ────────────────────────────────────────────────────────────────────────

    /** @test */
    public function it_returns_member_events(): void
    {
        $response = $this->withToken($this->token)
            ->getJson("/api/v1/leader/members/{$this->member->id}/events");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Member Event Registrations
    // ────────────────────────────────────────────────────────────────────────

    /** @test */
    public function it_returns_member_event_registrations(): void
    {
        $response = $this->withToken($this->token)
            ->getJson("/api/v1/leader/members/{$this->member->id}/event-registrations");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
    }

    /** @test */
    public function it_paginates_event_registrations(): void
    {
        $this->withToken($this->token)
            ->getJson("/api/v1/leader/members/{$this->member->id}/event-registrations?page=1&per_page=5")
            ->assertStatus(200)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 5);
    }
}
