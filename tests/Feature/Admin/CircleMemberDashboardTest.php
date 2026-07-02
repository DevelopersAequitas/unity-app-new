<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class CircleMemberDashboardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_circle_scoped_dashboard_loads_successfully(): void
    {
        // Ensure the role exists in the database
        $chairRole = Role::query()->firstOrCreate(
            ['key' => 'chair'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Chair',
                'description' => 'Circle Chair',
            ]
        );

        // Create the app user matching the email
        $user = User::factory()->create([
            'email' => 'chair.user@example.com',
            'display_name' => 'John Chair',
            'coins_balance' => 500,
        ]);

        // Create the admin user
        $admin = AdminUser::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'John Chair Admin',
            'email' => 'chair.user@example.com',
        ]);

        // Attach role to admin user
        $admin->roles()->attach($chairRole->id);

        // Create a test circle
        $circle = Circle::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Circle Alpha',
            'slug' => 'test-circle-alpha-'.Str::lower(Str::random(5)),
            'status' => 'active',
        ]);

        // Create the approved membership for the chair
        CircleMember::create([
            'circle_id' => $circle->id,
            'user_id' => $user->id,
            'role' => 'chair',
            'role_id' => $chairRole->id,
            'status' => 'approved',
            'joined_at' => now(),
        ]);

        // Create a peer user in the same circle
        $peerRole = Role::query()->firstOrCreate(
            ['key' => 'member'],
            ['id' => (string) Str::uuid(), 'name' => 'Member']
        );
        $peerUser = User::factory()->create([
            'email' => 'peer.user@example.com',
            'display_name' => 'Jane Peer',
        ]);
        CircleMember::create([
            'circle_id' => $circle->id,
            'user_id' => $peerUser->id,
            'role' => 'member',
            'role_id' => $peerRole->id,
            'status' => 'approved',
            'joined_at' => now(),
        ]);

        // Access the dashboard
        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.circle-member.dashboard'));

        // Assert success and presence of dashboard content
        $response->assertStatus(200);
        $response->assertSee('John Chair');
        $response->assertSee('Circle Dashboard');
        $response->assertSee('Test Circle Alpha');
    }

    public function test_founder_defaults_to_first_circle_and_conditional_dropdown(): void
    {
        $founderRole = Role::query()->firstOrCreate(
            ['key' => 'founder'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Founder',
                'description' => 'Circle Founder',
            ]
        );

        $user = User::factory()->create([
            'email' => 'founder.user@example.com',
            'display_name' => 'John Founder',
        ]);

        $admin = AdminUser::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'John Founder Admin',
            'email' => 'founder.user@example.com',
        ]);

        $admin->roles()->attach($founderRole->id);

        // Circle 1
        $circle1 = Circle::create([
            'id' => (string) Str::uuid(),
            'name' => 'Founder Circle One',
            'slug' => 'founder-circle-one-'.Str::lower(Str::random(5)),
            'status' => 'active',
        ]);

        CircleMember::create([
            'circle_id' => $circle1->id,
            'user_id' => $user->id,
            'role' => 'founder',
            'role_id' => $founderRole->id,
            'status' => 'approved',
            'joined_at' => now(),
        ]);

        // 1. Access dashboard with only 1 circle: it should load circle1 stats directly and not show a dropdown
        $response1 = $this->actingAs($admin, 'admin')
            ->get(route('admin.circle-member.dashboard'));

        $response1->assertStatus(200);
        $response1->assertSee('Circle Dashboard');
        $response1->assertSee('Circle Activities Overview');
        $response1->assertDontSee('id="topbar_circle_id"', false);

        // Circle 2
        $circle2 = Circle::create([
            'id' => (string) Str::uuid(),
            'name' => 'Founder Circle Two',
            'slug' => 'founder-circle-two-'.Str::lower(Str::random(5)),
            'status' => 'active',
        ]);

        CircleMember::create([
            'circle_id' => $circle2->id,
            'user_id' => $user->id,
            'role' => 'founder',
            'role_id' => $founderRole->id,
            'status' => 'approved',
            'joined_at' => now(),
        ]);

        // Clear AdminAccess caching by flushing cache to make sure the new circle is picked up
        \Illuminate\Support\Facades\Cache::flush();

        // 2. Access dashboard with multiple circles: it should default to circle1 but render the dropdown in the topbar
        $response2 = $this->actingAs($admin, 'admin')
            ->get(route('admin.circle-member.dashboard'));

        $response2->assertStatus(200);
        $response2->assertSee('id="topbar_circle_id"', false);
        $response2->assertSee('Founder Circle One');
        $response2->assertSee('Founder Circle Two');

        // 3. Select circle2 explicitly: it should filter stats by circle2
        $response3 = $this->actingAs($admin, 'admin')
            ->get(route('admin.circle-member.dashboard', ['circle_id' => $circle2->id]));

        $response3->assertStatus(200);
        $response3->assertSee('Founder Circle One'); // Present in dropdown option
        $response3->assertSee('Founder Circle Two'); // Present in dropdown option
    }
}
