<?php

declare(strict_types=1);

namespace Tests\Feature\Leadership;

use App\Models\AdminUser;
use App\Models\Role;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeadershipAdminWebTest extends TestCase
{
    protected function getAdminUser(): AdminUser
    {
        $role = Role::firstOrCreate(['key' => 'global_admin'], [
            'id' => (string) Str::uuid(),
            'name' => 'Global Admin',
            'key' => 'global_admin',
        ]);

        $admin = AdminUser::first();
        if (! $admin) {
            $admin = AdminUser::create([
                'id' => (string) Str::uuid(),
                'name' => 'Leadership Global Admin',
                'email' => 'admin.leadership@example.com',
            ]);
        }

        if (! $admin->roles()->where('key', 'global_admin')->exists()) {
            $admin->roles()->syncWithoutDetaching([$role->id]);
        }

        return $admin;
    }

    public function test_unauthenticated_user_cannot_access_leadership_admin(): void
    {
        $response = $this->get('/admin/web/leadership');

        $response->assertStatus(302);
    }

    public function test_admin_user_can_access_leadership_admin_spa(): void
    {
        $admin = $this->getAdminUser();

        $response = $this->actingAs($admin, 'admin')->get('/admin/web/leadership');

        $response->assertStatus(200);
        $response->assertSee('leadership-admin-root');
        $response->assertSee('Leadership Selection');
    }

    public function test_admin_user_can_access_spa_subroutes(): void
    {
        $admin = $this->getAdminUser();

        $response = $this->actingAs($admin, 'admin')->get('/admin/web/leadership/campaigns');

        $response->assertStatus(200);
        $response->assertSee('leadership-admin-root');
    }

    public function test_admin_can_fetch_roles_excluding_global_admin(): void
    {
        $admin = $this->getAdminUser();

        Role::firstOrCreate(['key' => 'chair'], [
            'id' => (string) Str::uuid(),
            'name' => 'Circle Chair',
            'key' => 'chair',
        ]);

        $response = $this->actingAs($admin, 'admin')->getJson('/api/v1/leadership/admin/roles');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $roles = $response->json('data');
        $this->assertIsArray($roles);
        $this->assertTrue(collect($roles)->contains('key', 'chair'));
        $this->assertFalse(collect($roles)->contains('key', 'global_admin'));
    }

    public function test_admin_can_create_campaign_with_valid_role_and_no_voting_or_jury_dates(): void
    {
        $admin = $this->getAdminUser();

        $chairRole = Role::firstOrCreate(['key' => 'chair'], [
            'id' => (string) Str::uuid(),
            'name' => 'Circle Chair',
            'key' => 'chair',
        ]);

        $response = $this->actingAs($admin, 'admin')->postJson('/api/v1/leadership/admin/campaigns', [
            'name' => 'Circle Chair Selection 2026',
            'code' => 'TEST-CHAIR-'.Str::random(4),
            'role_id' => $chairRole->id,
            'year' => 2026,
            'description' => 'Test campaign description',
            'nomination_start_at' => now()->toDateTimeString(),
            'nomination_end_at' => now()->addDays(14)->toDateTimeString(),
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $this->assertEquals('Circle Chair', $response->json('data.role_name'));
        $this->assertNull($response->json('data.voting_starts_at'));
    }

    public function test_admin_can_filter_nominations_by_role_and_form_type(): void
    {
        $admin = $this->getAdminUser();

        $response = $this->actingAs($admin, 'admin')->getJson('/api/v1/leadership/admin/nominations?role=DED&form_type=main_form_1');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }
}
