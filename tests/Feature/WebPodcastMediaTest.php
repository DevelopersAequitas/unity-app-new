<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Web\WebMedia;
use Tests\TestCase;

class WebPodcastMediaTest extends TestCase
{
    public function test_podcasts_index_is_accessible(): void
    {
        $this->withoutMiddleware();

        $response = $this->get(route('admin.web.podcasts.index'));

        $response->assertStatus(200);
        $response->assertSee('Podcast');
        $response->assertSee('Media Channels');
        $response->assertSee('Add Podcast Episode');
    }

    public function test_store_and_update_podcast_media(): void
    {
        $this->withoutMiddleware();

        $storeResponse = $this->post(route('admin.web.podcasts.store'), [
            'title' => 'Scaling MSME to 14 Export Markets',
            'subtitle' => 'Overcoming trade barriers through verified peer syndicates',
            'tag' => 'GLOBAL TRADE',
            'media_format' => 'podcast',
            'duration' => '44 mins',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'spotify_url' => 'https://open.spotify.com/episode/123456',
            'guest_name' => 'Harish Mehta',
            'guest_title' => 'Managing Director, Mehta Global Exim, Surat',
            'show_notes' => "Customs compliance.\nCross-Circle introductions bypass middlemen.",
            'is_featured' => 1,
            'is_active' => 1,
        ]);

        $storeResponse->assertRedirect(route('admin.web.podcasts.index'));

        $media = WebMedia::where('title', 'Scaling MSME to 14 Export Markets')->first();
        $this->assertNotNull($media);
        $this->assertSame('GLOBAL TRADE', $media->tag);
        $this->assertSame('dQw4w9WgXcQ', $media->youtube_video_id);
        $this->assertTrue($media->is_featured);
        $this->assertCount(2, $media->show_notes);

        // Update
        $updateResponse = $this->put(route('admin.web.podcasts.update', $media->id), [
            'title' => 'Scaling MSME to 14 Export Markets (Updated)',
            'tag' => 'GLOBAL TRADE',
            'media_format' => 'podcast',
            'duration' => '50 mins',
            'is_featured' => 0,
            'is_active' => 1,
        ]);

        $updateResponse->assertRedirect(route('admin.web.podcasts.index'));

        $media->refresh();
        $this->assertSame('Scaling MSME to 14 Export Markets (Updated)', $media->title);
        $this->assertSame('50 mins', $media->duration);
        $this->assertFalse($media->is_featured);

        // Toggle featured
        $toggleFeatured = $this->patch(route('admin.web.podcasts.toggle-featured', $media->id));
        $toggleFeatured->assertRedirect(route('admin.web.podcasts.index'));
        $media->refresh();
        $this->assertTrue($media->is_featured);

        // Delete
        $deleteResponse = $this->delete(route('admin.web.podcasts.destroy', $media->id));
        $deleteResponse->assertRedirect(route('admin.web.podcasts.index'));
        $this->assertNull(WebMedia::find($media->id));
    }

    public function test_public_podcasts_api(): void
    {
        $media = WebMedia::create([
            'title' => 'Building Communities That Last',
            'subtitle' => 'Why peer governance is the foundation',
            'tag' => 'COMMUNITY & VISION',
            'media_format' => 'podcast',
            'duration' => '58 mins',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'youtube_video_id' => 'dQw4w9WgXcQ',
            'guest_name' => 'Dr. Pravin Parmar',
            'guest_title' => 'Founder & Visionary, Peers Global',
            'is_featured' => true,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/web/podcasts?tag=COMMUNITY%20%26%20VISION');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertSee('Building Communities That Last');

        $featuredResponse = $this->getJson('/api/v1/web/podcasts/featured');
        $featuredResponse->assertStatus(200);
        $featuredResponse->assertSee('Building Communities That Last');

        $media->delete();
    }
}
