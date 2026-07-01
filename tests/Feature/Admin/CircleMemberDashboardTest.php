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
}
