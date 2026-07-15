<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Role;
use App\Models\Tutorial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminTutorialWebTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(): AdminUser
    {
        $role = Role::firstOrCreate(
            ['key' => 'global_admin'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Global Admin',
            ]
        );

        $admin = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Super Administrator',
            'email' => 'admin@example.com',
        ]);

        $admin->roles()->attach($role->id);

        return $admin;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/tutorials');
        $response->assertRedirect('/admin/login');
    }

    public function test_admin_can_view_tutorials_page(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin, 'admin');

        Tutorial::create([
            'video_id' => 'ZazxlEXKXKw',
            'youtube_url' => 'https://www.youtube.com/shorts/ZazxlEXKXKw',
        ]);

        $response = $this->get('/admin/tutorials');
        $response->assertStatus(200);
        $response->assertSee('Tutorials Management');
        $response->assertSee('ZazxlEXKXKw');
    }

    public function test_admin_can_add_tutorial(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin, 'admin');

        $payload = [
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ];

        $response = $this->post('/admin/tutorials', $payload);
        $response->assertRedirect('/admin/tutorials');
        $response->assertSessionHas('success', 'Tutorial video added successfully.');

        $this->assertDatabaseHas('tutorials', [
            'video_id' => 'dQw4w9WgXcQ',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
    }

    public function test_validation_prevents_duplicate_video(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin, 'admin');

        Tutorial::create([
            'video_id' => 'dQw4w9WgXcQ',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $response = $this->post('/admin/tutorials', [
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $response->assertSessionHasErrors('youtube_url');
    }

    public function test_admin_can_remove_tutorial(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin, 'admin');

        $tutorial = Tutorial::create([
            'video_id' => 'ZazxlEXKXKw',
            'youtube_url' => 'https://www.youtube.com/shorts/ZazxlEXKXKw',
        ]);

        $response = $this->delete("/admin/tutorials/{$tutorial->id}");
        $response->assertRedirect('/admin/tutorials');
        $response->assertSessionHas('success', 'Tutorial video removed successfully.');

        $this->assertDatabaseMissing('tutorials', [
            'id' => $tutorial->id,
        ]);
    }
}
