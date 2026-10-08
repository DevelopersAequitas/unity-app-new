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
}
