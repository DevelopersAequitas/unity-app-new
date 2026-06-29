<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileVisibilityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_own_hidden_profile(): void
    {
        $owner = User::factory()->create(['profile_visibility' => 'hidden']);
        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/members/{$owner->id}")->assertOk();
    }

    public function test_everyone_profile_is_visible_to_authenticated_users(): void
    {
        [$viewer, $owner] = $this->usersForVisibility('everyone');
        Sanctum::actingAs($viewer);

        $this->getJson("/api/v1/members/{$owner->id}")->assertOk();
    }

    public function test_connected_only_profile_requires_accepted_connection(): void
    {
        [$viewer, $owner, $unrelated] = $this->usersForVisibility('connected_only');
        Connection::create(['requester_id' => $viewer->id, 'addressee_id' => $owner->id, 'is_approved' => true, 'approved_at' => now()]);

        Sanctum::actingAs($viewer);
        $this->getJson("/api/v1/members/{$owner->id}")->assertOk();

        Sanctum::actingAs($unrelated);
        $this->getJson("/api/v1/members/{$owner->id}")->assertForbidden();
    }

    public function test_circle_only_profile_requires_shared_approved_circle(): void
    {
        [$viewer, $owner, $unrelated] = $this->usersForVisibility('circle_only');
        $circleId = (string) Str::uuid();
        DB::table('circles')->insert(['id' => $circleId, 'name' => 'Circle', 'slug' => 'circle', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([$viewer, $owner] as $user) {
            DB::table('circle_members')->insert(['id' => (string) Str::uuid(), 'circle_id' => $circleId, 'user_id' => $user->id, 'status' => 'approved', 'role' => 'member', 'created_at' => now(), 'updated_at' => now()]);
        }

        Sanctum::actingAs($viewer);
        $this->getJson("/api/v1/members/{$owner->id}")->assertOk();

        Sanctum::actingAs($unrelated);
        $this->getJson("/api/v1/members/{$owner->id}")->assertForbidden();
    }

    public function test_admin_can_view_hidden_profile(): void
    {
        [$admin, $owner] = $this->usersForVisibility('hidden');
        $role = Role::create(['id' => (string) Str::uuid(), 'key' => 'global_admin', 'name' => 'Global Admin']);
        $admin->roles()->attach($role->id);
        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/members/{$owner->id}")->assertOk();
    }

    /** @return array{0: User, 1: User, 2: User} */
    private function usersForVisibility(string $visibility): array
    {
        return [
            User::factory()->create(['status' => 'active']),
            User::factory()->create(['status' => 'active', 'profile_visibility' => $visibility]),
            User::factory()->create(['status' => 'active']),
        ];
    }
}
