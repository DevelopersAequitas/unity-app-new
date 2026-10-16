<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Web\WebGalleryItem;
use Tests\TestCase;

class WebGalleryTest extends TestCase
{
    public function test_gallery_index_is_accessible(): void
    {
        $this->withoutMiddleware();

        $response = $this->get(route('admin.web.gallery.index'));

        $response->assertStatus(200);
        $response->assertSee('Gallery Collection');
        $response->assertSee('Add Gallery Photo');
    }

    public function test_store_and_update_gallery_item(): void
    {
        $this->withoutMiddleware();

        $storeResponse = $this->post(route('admin.web.gallery.store'), [
            'title' => 'Test Conclave Opening Session',
            'category' => 'Annual Summit',
            'city' => 'Ahmedabad',
            'circle' => 'All Circles',
            'year' => '2026',
            'image_url' => 'https://peersglobal.com/images/test.jpg',
            'caption' => 'Test caption describing plenary session.',
            'sort_order' => 1,
            'is_active' => 1,
        ]);

        $storeResponse->assertRedirect(route('admin.web.gallery.index'));

        $item = WebGalleryItem::where('title', 'Test Conclave Opening Session')->first();
        $this->assertNotNull($item);
        $this->assertSame('Ahmedabad', $item->city);
        $this->assertSame('Annual Summit', $item->category);

        // Update
        $updateResponse = $this->put(route('admin.web.gallery.update', $item->id), [
            'title' => 'Updated Conclave Opening Session',
            'category' => 'Annual Summit',
            'city' => 'Mumbai',
            'circle' => 'Logistics Circle',
            'year' => '2026',
            'image_url' => 'https://peersglobal.com/images/test-updated.jpg',
            'caption' => 'Updated caption.',
            'sort_order' => 2,
            'is_active' => 1,
        ]);

        $updateResponse->assertRedirect(route('admin.web.gallery.index'));

        $item->refresh();
        $this->assertSame('Updated Conclave Opening Session', $item->title);
        $this->assertSame('Mumbai', $item->city);

        // Toggle status
        $toggleResponse = $this->patch(route('admin.web.gallery.toggle-status', $item->id));
        $toggleResponse->assertRedirect(route('admin.web.gallery.index'));

        $item->refresh();
        $this->assertFalse($item->is_active);

        // Delete
        $deleteResponse = $this->delete(route('admin.web.gallery.destroy', $item->id));
        $deleteResponse->assertRedirect(route('admin.web.gallery.index'));
        $this->assertNull(WebGalleryItem::find($item->id));
    }

    public function test_public_gallery_api(): void
    {
        $item = WebGalleryItem::create([
            'title' => 'Public API Gallery Test Moment',
            'category' => 'Circle Meetings',
            'city' => 'Bengaluru',
            'circle' => 'Technology Circle',
            'year' => '2026',
            'image_path' => 'https://peersglobal.com/images/bengaluru.jpg',
            'caption' => 'Roundtable in Bengaluru.',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/web/gallery?city=Bengaluru');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertSee('Public API Gallery Test Moment');

        $item->delete();
    }
}
