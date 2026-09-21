<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Role;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebManagementTest extends TestCase
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
                'name' => 'Test Global Admin',
                'email' => 'testglobaladmin@example.com',
            ]);
        }

        if (! $admin->roles()->where('key', 'global_admin')->exists()) {
            $admin->roles()->syncWithoutDetaching([$role->id]);
        }

        return $admin;
    }

    public function test_web_dashboard_is_accessible(): void
    {
        $admin = $this->getAdminUser();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.web.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Dashboard Overview');
        $response->assertSee('Peers Global Website');
    }

    public function test_web_partnerships_index_and_create(): void
    {
        $admin = $this->getAdminUser();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.web.partnerships.index'));
        $response->assertStatus(200);
        $response->assertSee('Partnerships Management');

        $storeResponse = $this->actingAs($admin, 'admin')->post(route('admin.web.partnerships.store'), [
            'title' => 'Test Energy Alliance',
            'company_a' => 'Solar Alpha',
            'company_b' => 'Grid Beta',
            'sector' => 'CleanTech',
            'value' => '₹ 2.5 Cr',
            'status' => 'Active',
            'stage' => 'Active Execution',
            'description' => 'Test description for partnership',
        ]);

        $storeResponse->assertRedirect(route('admin.web.partnerships.index'));
        $this->assertDatabaseHas('web_partnerships', [
            'title' => 'Test Energy Alliance',
            'company_a' => 'Solar Alpha',
        ]);
    }

    public function test_web_opportunities_index(): void
    {
        $admin = $this->getAdminUser();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.web.opportunities.index'));
        $response->assertStatus(200);
        $response->assertSee('Opportunities Pipeline');
    }

    public function test_web_companies_index(): void
    {
        $admin = $this->getAdminUser();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.web.companies.index'));
        $response->assertStatus(200);
        $response->assertSee('Enterprise Companies Directory');
    }

    public function test_web_blogs_index(): void
    {
        $admin = $this->getAdminUser();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.web.blogs.index'));
        $response->assertStatus(200);
        $response->assertSee('Publications');
    }

    public function test_web_media_index(): void
    {
        $admin = $this->getAdminUser();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.web.media.index'));
        $response->assertStatus(200);
        $response->assertSee('Website Media Library');
    }

    public function test_web_page_media_index(): void
    {
        $admin = $this->getAdminUser();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.web.page-media.index'));
        $response->assertStatus(200);
        $response->assertSee('Website Page Media');
    }

    public function test_public_web_media_api(): void
    {
        $response = $this->getJson('/api/v1/web-media');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'count',
            'data',
        ]);
    }

    public function test_public_web_blogs_api(): void
    {
        $response = $this->getJson('/api/v1/web-blogs');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data',
        ]);
    }

    public function test_public_web_settings_api(): void
    {
        $response = $this->getJson('/api/v1/web-settings');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data',
        ]);
    }
}
