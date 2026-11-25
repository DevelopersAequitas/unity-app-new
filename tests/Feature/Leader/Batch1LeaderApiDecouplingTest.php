<?php

declare(strict_types=1);

namespace Tests\Feature\Leader;

use App\Leader\Models\LeaderRoleCapability;
use App\Models\BecomeMentorSubmission;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\District;
use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Batch1LeaderApiDecouplingTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithRole(string $roleKey, string $email, array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'email' => $email,
            'active_circle_id' => $overrides['active_circle_id'] ?? null,
        ], $overrides));

        if (Schema::hasTable('roles') && Schema::hasTable('user_roles')) {
            $roleId = Role::query()->where('key', $roleKey)->value('id');
            if (! $roleId) {
                $roleId = (string) Str::uuid();
                DB::table('roles')->insert([
                    'id' => $roleId,
                    'key' => $roleKey,
                    'name' => ucfirst(str_replace('_', ' ', $roleKey)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('user_roles')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => (string) $user->id,
                'role_id' => (string) $roleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $user;
    }

    public function test_guest_user_is_denied_leader_leads_and_posts_endpoints(): void
    {
        $guest = User::factory()->create(['email' => 'member@example.com']);
        Sanctum::actingAs($guest);

        $this->getJson('/api/v1/leader/leads/counts')->assertStatus(403);
        $this->getJson('/api/v1/leader/leads/become_mentor')->assertStatus(403);
        $this->getJson('/api/v1/leader/posts')->assertStatus(403);
        $this->getJson('/api/v1/leader/pending-requests')->assertStatus(403);
        $this->getJson('/api/v1/leader/pending-requests/summary')->assertStatus(403);
    }

    public function test_leader_leads_and_posts_enforce_organizational_scope_across_roles(): void
    {
        $districtA = District::query()->create(['id' => (string) Str::uuid(), 'name' => 'District A', 'country' => 'India']);
        $districtB = District::query()->create(['id' => (string) Str::uuid(), 'name' => 'District B', 'country' => 'USA']);

        $circleA = Circle::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Circle Alpha',
            'district_id' => $districtA->id,
            'country' => 'India',
        ]);
        $circleB = Circle::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Circle Beta',
            'district_id' => $districtB->id,
            'country' => 'USA',
        ]);

        $peerA = User::factory()->create([
            'email' => 'peera@example.com',
            'active_circle_id' => $circleA->id,
        ]);
        $peerB = User::factory()->create([
            'email' => 'peerb@example.com',
            'active_circle_id' => $circleB->id,
        ]);

        CircleMember::query()->create([
            'id' => (string) Str::uuid(),
            'circle_id' => $circleA->id,
            'user_id' => $peerA->id,
            'status' => 'approved',
        ]);
        CircleMember::query()->create([
            'id' => (string) Str::uuid(),
            'circle_id' => $circleB->id,
            'user_id' => $peerB->id,
            'status' => 'approved',
        ]);

        Post::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $peerA->id,
            'circle_id' => $circleA->id,
            'post_type' => 'general',
            'content_text' => 'Alpha post',
            'status' => 'active',
            'is_deleted' => false,
        ]);
        Post::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $peerB->id,
            'circle_id' => $circleB->id,
            'post_type' => 'general',
            'content_text' => 'Beta post',
            'status' => 'active',
            'is_deleted' => false,
        ]);

        BecomeMentorSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alpha',
            'last_name' => 'Mentor',
            'email' => 'peera@example.com',
            'status' => 'pending',
        ]);
        BecomeMentorSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Beta',
            'last_name' => 'Mentor',
            'email' => 'peerb@example.com',
            'status' => 'pending',
        ]);

        LeaderRoleCapability::query()->updateOrCreate(
            ['role' => 'circleFounder', 'capability' => 'view_peers'],
            ['is_enabled' => true]
        );
        LeaderRoleCapability::query()->updateOrCreate(
            ['role' => 'circleDirector', 'capability' => 'view_peers'],
            ['is_enabled' => true]
        );
        LeaderRoleCapability::query()->updateOrCreate(
            ['role' => 'chair', 'capability' => 'view_peers'],
            ['is_enabled' => true]
        );
        LeaderRoleCapability::query()->updateOrCreate(
            ['role' => 'districtExecDirector', 'capability' => 'view_peers'],
            ['is_enabled' => true]
        );
        LeaderRoleCapability::query()->updateOrCreate(
            ['role' => 'industryDirector', 'capability' => 'view_peers'],
            ['is_enabled' => true]
        );
        LeaderRoleCapability::query()->updateOrCreate(
            ['role' => 'countryDirector', 'capability' => 'view_peers'],
            ['is_enabled' => true]
        );

        $superAdmin = $this->createUserWithRole('super_admin', 'superadmin@example.com');
        $countryDirector = $this->createUserWithRole('country_director', 'eed@example.com', ['country' => 'India']);
        $ded = $this->createUserWithRole('ded', 'ded@example.com', ['district_id' => $districtA->id]);
        $industryDirector = $this->createUserWithRole('industry_director', 'id@example.com');
        $circleFounder = $this->createUserWithRole('circle_founder', 'cf@example.com', ['active_circle_id' => $circleA->id]);
        $circleDirector = $this->createUserWithRole('circle_director', 'cd@example.com', ['active_circle_id' => $circleA->id]);
        $chair = $this->createUserWithRole('chair', 'chair@example.com', ['active_circle_id' => $circleA->id]);

        $circleA->update([
            'founder_user_id' => $circleFounder->id,
            'director_user_id' => $circleDirector->id,
            'ded_user_id' => $ded->id,
        ]);

        // 1. Super Admin sees global counts & posts (both circles)
        Sanctum::actingAs($superAdmin);
        $this->getJson('/api/v1/leader/leads/counts')
            ->assertOk()
            ->assertJsonPath('data.become_mentor', 2);
        $this->getJson('/api/v1/leader/posts')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 2);

        // 2. Circle Founder sees only Circle Alpha (1 mentor, 1 post), and is blocked from Circle Beta
        Sanctum::actingAs($circleFounder);
        $this->getJson('/api/v1/leader/leads/counts')
            ->assertOk()
            ->assertJsonPath('data.become_mentor', 1);
        $this->getJson('/api/v1/leader/posts')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
        $this->getJson("/api/v1/leader/posts?circle_id={$circleB->id}")
            ->assertStatus(403);

        // 3. Circle Director, Chair, DED, Country Director, Industry Director all stay within authorized scope
        foreach ([$countryDirector, $ded, $circleDirector, $chair] as $scopedLeader) {
            Sanctum::actingAs($scopedLeader);
            $this->getJson('/api/v1/leader/dashboard/metrics')->assertOk();
            $this->getJson('/api/v1/leader/leads/counts')->assertOk();
            $this->getJson('/api/v1/leader/posts')->assertOk();
            $this->getJson("/api/v1/leader/posts?circle_id={$circleB->id}")->assertStatus(403);
        }

        // Industry Director without assigned categories gets 0 out-of-scope records
        Sanctum::actingAs($industryDirector);
        $this->getJson('/api/v1/leader/leads/counts')
            ->assertOk()
            ->assertJsonPath('data.become_mentor', 0);
        $this->getJson('/api/v1/leader/posts')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);

        // Pending requests summary & list remain accessible to authorized leaders
        Sanctum::actingAs($circleFounder);
        $this->getJson('/api/v1/leader/pending-requests/summary')->assertOk();
        $this->getJson('/api/v1/leader/pending-requests')->assertOk();
    }
}
