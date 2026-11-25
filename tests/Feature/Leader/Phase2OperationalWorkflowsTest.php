<?php

declare(strict_types=1);

namespace Tests\Feature\Leader;

use App\Leader\Models\LeaderRoleCapability;
use App\Leader\Services\LeaderTeamsService;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Phase2OperationalWorkflowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_circle_role_assignment_enforces_scope_and_prevents_duplicates(): void
    {
        $circleA = Circle::query()->create(['id' => (string) Str::uuid(), 'name' => 'Alpha Circle']);
        $circleB = Circle::query()->create(['id' => (string) Str::uuid(), 'name' => 'Beta Circle']);

        $founderA = User::factory()->create(['role' => 'circle_founder', 'active_circle_id' => $circleA->id]);
        $circleA->update(['founder_user_id' => $founderA->id]);

        $memberA = User::factory()->create(['active_circle_id' => $circleA->id]);
        CircleMember::query()->create([
            'id' => (string) Str::uuid(),
            'circle_id' => $circleA->id,
            'user_id' => $memberA->id,
            'role' => 'member',
            'status' => 'approved',
        ]);

        LeaderRoleCapability::query()->updateOrCreate(
            ['role' => 'circleFounder', 'capability' => 'access_teams'],
            ['is_enabled' => true]
        );

        Sanctum::actingAs($founderA);

        // Authorized assignment within Circle Alpha succeeds
        $this->postJson("/api/v1/leader/teams/circles/{$circleA->id}/assign-role", [
            'user_id' => $memberA->id,
            'role' => 'vice_chair',
        ])->assertOk()->assertJsonPath('data.role', 'vice_chair');

        // Duplicate identical assignment returns 422
        $this->postJson("/api/v1/leader/teams/circles/{$circleA->id}/assign-role", [
            'user_id' => $memberA->id,
            'role' => 'vice_chair',
        ])->assertStatus(422);

        // Cross-circle assignment into Circle Beta is denied with 403
        $this->postJson("/api/v1/leader/teams/circles/{$circleB->id}/assign-role", [
            'user_id' => $memberA->id,
            'role' => 'secretary',
        ])->assertStatus(403);

        // Circle Chair without can_assign_circle_chair is denied with 403
        $chair = User::factory()->create(['role' => 'circle_chair', 'active_circle_id' => $circleA->id]);
        Sanctum::actingAs($chair);
        $this->postJson("/api/v1/leader/teams/circles/{$circleA->id}/assign-role", [
            'user_id' => $memberA->id,
            'role' => 'secretary',
        ])->assertStatus(403);
    }

    public function test_teams_circle_revenue_and_health_do_not_use_fabricated_defaults(): void
    {
        $circle = Circle::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Zero Circle',
            'health_score' => null,
        ]);
        $peer = User::factory()->create(['active_circle_id' => $circle->id]);
        CircleMember::query()->create([
            'id' => (string) Str::uuid(),
            'circle_id' => $circle->id,
            'user_id' => $peer->id,
            'status' => 'approved',
        ]);

        $service = app(LeaderTeamsService::class);
        $this->assertSame('₹0.0', $service->calculateCircleRevenue($circle, 1));
    }
}
