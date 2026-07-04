<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CircleManagementTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;

    private Circle $circle;

    private User $founder;

    protected function setUp(): void
    {
        parent::setUp();

        $globalAdminRole = Role::where('key', 'global_admin')->firstOrFail();

        Role::forceCreate([
            'id' => (string) Str::uuid(),
            'key' => 'founder',
            'name' => 'Founder',
            'description' => 'Circle Founder',
        ]);

        Role::forceCreate([
            'id' => (string) Str::uuid(),
            'key' => 'member',
            'name' => 'Member',
            'description' => 'Circle Member',
        ]);

        $this->admin = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Global Admin',
            'email' => 'admin.test@example.com',
        ]);
        $this->admin->roles()->attach($globalAdminRole->id);

        $this->founder = User::factory()->create([
            'email' => 'founder@example.com',
            'status' => 'active',
        ]);

        $this->circle = Circle::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Circle X',
            'slug' => 'test-circle-x-'.Str::lower(Str::random(5)),
            'status' => 'active',
            'founder_user_id' => $this->founder->id,
        ]);

        CircleMember::create([
            'circle_id' => $this->circle->id,
            'user_id' => $this->founder->id,
            'role' => 'founder',
            'status' => 'approved',
            'joined_at' => now(),
        ]);
    }

    public function test_peer_options_only_shows_active_non_members(): void
    {
        // 1. Create an active non-member
        $activeUser = User::factory()->create([
            'email' => 'active.nonmember@example.com',
            'status' => 'active',
            'display_name' => 'Active NonMember',
            'company_name' => 'Aequitas Infortech',
            'city' => 'Ahmedabad',
        ]);

        // Create a user with duplicate name
        $duplicateUser = User::factory()->create([
            'email' => 'duplicate.nonmember@example.com',
            'status' => 'active',
            'display_name' => 'Active NonMember',
            'company_name' => 'Aequitas Tech',
            'city' => 'Ahmedabad',
        ]);

        // 2. Create an inactive user
        $inactiveUser = User::factory()->create([
            'email' => 'inactive.user@example.com',
            'status' => 'inactive',
            'display_name' => 'Inactive User',
        ]);

        // 3. Create a soft deleted user
        $softDeletedUser = User::factory()->create([
            'email' => 'deleted.user@example.com',
            'status' => 'active',
            'display_name' => 'Deleted User',
        ]);
        $softDeletedUser->delete();

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.circles.peer-options', $this->circle));

        $response->assertStatus(200);
        $results = $response->json('results');

        $this->assertArrayHasKey('pagination', $response->json());
        $this->assertFalse($response->json('pagination.more'));

        $ids = collect($results)->pluck('id')->toArray();

        // Active non-members should be in results
        $this->assertContains($activeUser->id, $ids);
        $this->assertContains($duplicateUser->id, $ids);

        // Inactive user should NOT be in results
        $this->assertNotContains($inactiveUser->id, $ids);

        // Soft deleted user should NOT be in results
        $this->assertNotContains($softDeletedUser->id, $ids);

        // Circle founder (current member) MUST NOT be in results
        $this->assertNotContains($this->founder->id, $ids);

        // Verify active options are disambiguated with company in parentheses since name is duplicate
        $activeOption = collect($results)->firstWhere('id', $activeUser->id);
        $this->assertNotNull($activeOption);
        $this->assertEquals('Active NonMember (Aequitas Infortech)', $activeOption['text']);

        $dupOption = collect($results)->firstWhere('id', $duplicateUser->id);
        $this->assertNotNull($dupOption);
        $this->assertEquals('Active NonMember (Aequitas Tech)', $dupOption['text']);
    }

    public function test_cannot_remove_founder_from_circle(): void
    {
        $founderMember = CircleMember::where('circle_id', $this->circle->id)
            ->where('user_id', $this->founder->id)
            ->firstOrFail();

        $response = $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.circles.members.destroy', [$this->circle, $founderMember]));

        // Should redirect back with error session key
        $response->assertRedirect();
        $response->assertSessionHas('error', 'Cannot remove founder. Please transfer founder role first.');

        // Verify founder member is still in circle
        $this->assertDatabaseHas('circle_members', [
            'id' => $founderMember->id,
            'deleted_at' => null,
        ]);
    }

    public function test_remove_peer_success_clears_active_circle(): void
    {
        $peer = User::factory()->create([
            'email' => 'peer@example.com',
            'status' => 'active',
            'active_circle_id' => $this->circle->id,
        ]);

        $peerMember = CircleMember::create([
            'circle_id' => $this->circle->id,
            'user_id' => $peer->id,
            'role' => 'member',
            'status' => 'approved',
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.circles.members.destroy', [$this->circle, $peerMember]));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Member removed from the circle.');

        // Verify membership is soft deleted
        $this->assertSoftDeleted('circle_members', [
            'id' => $peerMember->id,
        ]);

        // Verify user's active_circle_id is cleared
        $this->assertDatabaseHas('users', [
            'id' => $peer->id,
            'active_circle_id' => null,
        ]);
    }

    public function test_delete_stats_returns_correct_counts(): void
    {
        // Add a meeting
        DB::table('circle_meetings')->insert([
            'id' => (string) Str::uuid(),
            'circle_id' => $this->circle->id,
            'meeting_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.circles.delete-stats', $this->circle));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'name' => $this->circle->name,
            'members_count' => 1, // Founder is member
            'meetings_count' => 1,
            'related_count' => 0,
        ]);
    }

    public function test_circle_deletion_performs_cascade_cleanup(): void
    {
        // Associate peer user
        $peer = User::factory()->create([
            'email' => 'peer.cascade@example.com',
            'status' => 'active',
            'active_circle_id' => $this->circle->id,
        ]);

        $peerMember = CircleMember::create([
            'circle_id' => $this->circle->id,
            'user_id' => $peer->id,
            'role' => 'member',
            'status' => 'approved',
            'joined_at' => now(),
        ]);

        // Add a meeting
        $meetingId = (string) Str::uuid();
        DB::table('circle_meetings')->insert([
            'id' => $meetingId,
            'circle_id' => $this->circle->id,
            'meeting_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Add a join request
        $requestId = (string) Str::uuid();
        DB::table('circle_join_requests')->insert([
            'id' => $requestId,
            'circle_id' => $this->circle->id,
            'user_id' => $peer->id,
            'status' => 'pending_cd_approval',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.circles.destroy', $this->circle));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Circle deleted successfully.');

        // Verify circle is soft deleted
        $this->assertSoftDeleted('circles', [
            'id' => $this->circle->id,
        ]);

        // Verify circle members are soft deleted
        $this->assertSoftDeleted('circle_members', [
            'circle_id' => $this->circle->id,
        ]);

        // Verify meetings are deleted
        $this->assertDatabaseMissing('circle_meetings', [
            'id' => $meetingId,
        ]);

        // Verify join requests are deleted
        $this->assertDatabaseMissing('circle_join_requests', [
            'id' => $requestId,
        ]);

        // Verify user active_circle_id is cleared
        $this->assertDatabaseHas('users', [
            'id' => $peer->id,
            'active_circle_id' => null,
        ]);
    }

    public function test_cannot_add_duplicate_member_to_circle(): void
    {
        // Founder is already in the circle
        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.circles.members.store', $this->circle), [
                'user_id' => $this->founder->id,
                'role' => 'member',
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'This peer is already a member of this circle.',
        ]);
    }
}
