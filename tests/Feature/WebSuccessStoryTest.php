<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Role;
use App\Models\Web\WebSuccessStory;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebSuccessStoryTest extends TestCase
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

    public function test_success_stories_index_is_accessible(): void
    {
        $admin = $this->getAdminUser();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.web.success-stories.index'));

        $response->assertStatus(200);
        $response->assertSee('Homepage Success Stories');
        $response->assertSee('Add Media for Homepage');
    }

    public function test_store_and_update_success_story(): void
    {
        $admin = $this->getAdminUser();

        $storeResponse = $this->actingAs($admin, 'admin')->post(route('admin.web.success-stories.store'), [
            'person_name' => 'John Doe Test',
            'designation' => 'Founder & CEO',
            'company' => 'Acme Corp',
            'story_title' => 'Scaling Logistics across APAC',
            'quote' => 'Great experience with Peers Global network.',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'sort_order' => 10,
            'is_active' => '1',
        ]);

        $storeResponse->assertRedirect(route('admin.web.success-stories.index'));

        $story = WebSuccessStory::where('person_name', 'John Doe Test')->first();
        $this->assertNotNull($story);
        $this->assertSame('dQw4w9WgXcQ', $story->youtube_video_id);

        // Test update
        $updateResponse = $this->actingAs($admin, 'admin')->put(route('admin.web.success-stories.update', $story->id), [
            'person_name' => 'John Doe Updated',
            'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ',
            'sort_order' => 5,
            'is_active' => '1',
        ]);

        $updateResponse->assertRedirect(route('admin.web.success-stories.index'));
        $story->refresh();
        $this->assertSame('John Doe Updated', $story->person_name);

        // Cleanup
        $story->delete();
    }

    public function test_public_success_stories_api(): void
    {
        $response = $this->getJson('/api/v1/web/success-stories');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'count',
            'data' => [
                '*' => [
                    'id',
                    'personName',
                    'youtubeUrl',
                    'youtubeVideoId',
                    'youtubeEmbedUrl',
                    'coverImageUrl',
                ],
            ],
        ]);
    }
}
