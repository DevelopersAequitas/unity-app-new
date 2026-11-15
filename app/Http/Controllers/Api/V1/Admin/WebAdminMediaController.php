<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Web\WebGalleryItem;
use App\Models\Web\WebMedia;
use App\Models\Web\WebSuccessStory;
use App\Services\Web\WebGalleryService;
use App\Services\Web\WebPodcastMediaService;
use App\Services\Web\WebSuccessStoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class WebAdminMediaController extends Controller
{
    public function __construct(
        protected WebGalleryService $galleryService,
        protected WebPodcastMediaService $podcastService,
        protected WebSuccessStoryService $storyService
    ) {}

    /**
     * Get paginated/full gallery items with statistics for admin dashboard.
     */
    public function gallery(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->query('search', ''),
        ];

        $items = $this->galleryService->listPaginated($filters, (int) $request->query('per_page', 50));
        $totalCount = $items->total();
        $activeCount = Schema::hasTable('web_gallery_items') && Schema::hasColumn('web_gallery_items', 'is_active')
            ? WebGalleryItem::where('is_active', true)->count()
            : $totalCount;

        $mapped = collect($items->items())->map(function (WebGalleryItem $item): array {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'category' => $item->category,
                'city' => $item->city,
                'circle' => $item->circle,
                'year' => $item->year,
                'image' => $item->image_url ?: ($item->image_path ?: $item->media_url),
                'imageUrl' => $item->image_url ?: ($item->image_path ?: $item->media_url),
                'mediaUrl' => $item->image_url ?: ($item->image_path ?: $item->media_url),
                'caption' => $item->caption ?? '',
                'sortOrder' => $item->sort_order,
                'isActive' => (bool) $item->is_active,
                'createdAt' => $item->created_at ? $item->created_at->toIso8601String() : null,
            ];
        });

        return response()->json([
            'success' => true,
            'count' => $mapped->count(),
            'total' => $totalCount,
            'active_count' => $activeCount,
            'data' => $mapped,
        ]);
    }

    /**
     * Toggle active/live status of a gallery item.
     */
    public function toggleGalleryStatus(string $id): JsonResponse
    {
        $item = $this->galleryService->findItemById($id);

        if (! $item) {
            return response()->json([
                'success' => false,
                'message' => 'Gallery item not found',
            ], 404);
        }

        $this->galleryService->toggleItemActive($item);

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully',
            'data' => [
                'id' => $item->id,
                'isActive' => (bool) $item->is_active,
            ],
        ]);
    }

    /**
     * Delete a single gallery item.
     */
    public function destroyGallery(string $id): JsonResponse
    {
        $item = $this->galleryService->findItemById($id);

        if (! $item) {
            return response()->json([
                'success' => false,
                'message' => 'Gallery item not found',
            ], 404);
        }

        $this->galleryService->deleteItem($item);

        return response()->json([
            'success' => true,
            'message' => 'Gallery item deleted successfully',
        ]);
    }

    /**
     * Bulk delete gallery items.
     */
    public function bulkDeleteGallery(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);

        if (empty($ids) || ! is_array($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'No items specified for deletion',
            ], 422);
        }

        $count = $this->galleryService->bulkDelete($ids);

        return response()->json([
            'success' => true,
            'message' => "Successfully deleted {$count} gallery items",
            'deleted_count' => $count,
        ]);
    }

    /**
     * Store a newly created gallery item.
     */
    public function storeGallery(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'caption' => 'required|string|max:1000',
            'image_url' => 'nullable|string|max:1000',
            'category' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'circle' => 'nullable|string|max:100',
            'year' => 'nullable|string|max:10',
            'is_active' => 'nullable|boolean',
        ]);

        $file = $request->file('image');
        $item = $this->galleryService->createItem($validated, $file);

        return response()->json([
            'success' => true,
            'message' => 'Gallery item created successfully',
            'data' => [
                'id' => $item->id,
                'title' => $item->title,
                'caption' => $item->caption,
                'category' => $item->category,
                'city' => $item->city,
                'circle' => $item->circle,
                'year' => $item->year,
                'image' => $item->image_url ?: ($item->image_path ?: $item->media_url),
                'imageUrl' => $item->image_url ?: ($item->image_path ?: $item->media_url),
                'mediaUrl' => $item->image_url ?: ($item->image_path ?: $item->media_url),
                'isActive' => (bool) $item->is_active,
                'createdAt' => $item->created_at ? $item->created_at->toIso8601String() : null,
            ],
        ], 201);
    }

    /**
     * Update gallery item details.
     */
    public function updateGallery(Request $request, string $id): JsonResponse
    {
        $item = $this->galleryService->findItemById($id);

        if (! $item) {
            return response()->json([
                'success' => false,
                'message' => 'Gallery item not found',
            ], 404);
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:1000',
            'category' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'circle' => 'nullable|string|max:100',
            'year' => 'nullable|string|max:10',
            'is_active' => 'nullable|boolean',
        ]);

        $file = $request->file('image');
        $updated = $this->galleryService->updateItem($item, $validated, $file);

        return response()->json([
            'success' => true,
            'message' => 'Gallery item updated successfully',
            'data' => [
                'id' => $updated->id,
                'title' => $updated->title,
                'caption' => $updated->caption,
                'isActive' => (bool) $updated->is_active,
            ],
        ]);
    }

    /**
     * Get podcasts / media channels for admin panel.
     */
    public function podcasts(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->query('search', ''),
            'tag' => $request->query('tag', 'all'),
            'format' => $request->query('format', 'all'),
        ];

        $items = $this->podcastService->listPaginated($filters, (int) $request->query('per_page', 50));
        $totalCount = $items->total();

        $featuredCount = Schema::hasTable('web_media') && Schema::hasColumn('web_media', 'is_featured')
            ? WebMedia::where('is_featured', true)->count()
            : 0;

        $youtubeCount = Schema::hasTable('web_media') && Schema::hasColumn('web_media', 'youtube_url')
            ? WebMedia::whereNotNull('youtube_url')->where('youtube_url', '!=', '')->count()
            : 0;

        $spotifyCount = Schema::hasTable('web_media') && Schema::hasColumn('web_media', 'spotify_url')
            ? WebMedia::whereNotNull('spotify_url')->where('spotify_url', '!=', '')->count()
            : 0;

        $mapped = collect($items->items())->map(function (WebMedia $item): array {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'subtitle' => $item->subtitle,
                'tag' => $item->tag,
                'format' => $item->media_format,
                'duration' => $item->duration,
                'image' => $item->cover_image_url ?: $item->cover_image,
                'guest' => [
                    'name' => $item->guest_name ?: 'Community Leader',
                    'title' => $item->guest_title ?: 'Peers Global Member',
                    'avatar' => $item->guest_avatar_url ?: $item->guest_avatar,
                ],
                'spotifyUrl' => $item->spotify_url,
                'youtubeUrl' => $item->youtube_url,
                'youtubeVideoId' => $item->youtube_video_id,
                'isFeatured' => (bool) $item->is_featured,
                'isActive' => (bool) $item->is_active,
                'sortOrder' => $item->sort_order,
                'createdAt' => $item->created_at ? $item->created_at->toIso8601String() : null,
            ];
        });

        return response()->json([
            'success' => true,
            'count' => $mapped->count(),
            'total' => $totalCount,
            'stats' => [
                'total' => $totalCount,
                'featured' => $featuredCount,
                'youtube' => $youtubeCount,
                'spotify' => $spotifyCount,
            ],
            'data' => $mapped,
        ]);
    }

    /**
     * Store a new podcast episode.
     */
    public function storePodcast(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'tag' => 'nullable|string|max:100',
            'media_format' => 'nullable|string|max:100',
            'duration' => 'nullable|string|max:50',
            'youtube_url' => 'nullable|string|max:1000',
            'spotify_url' => 'nullable|string|max:1000',
            'cover_url' => 'nullable|string|max:1000',
            'guest_name' => 'nullable|string|max:255',
            'guest_title' => 'nullable|string|max:500',
            'is_featured' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $coverFile = $request->file('cover');
        $avatarFile = $request->file('avatar');
        $media = $this->podcastService->create($validated, $coverFile, $avatarFile);

        return response()->json([
            'success' => true,
            'message' => 'Podcast episode created successfully',
            'data' => [
                'id' => $media->id,
                'title' => $media->title,
                'isActive' => (bool) $media->is_active,
                'isFeatured' => (bool) $media->is_featured,
            ],
        ], 201);
    }

    /**
     * Update podcast episode.
     */
    public function updatePodcast(Request $request, string $id): JsonResponse
    {
        $media = $this->podcastService->findById($id);

        if (! $media) {
            return response()->json(['success' => false, 'message' => 'Podcast episode not found'], 404);
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'tag' => 'nullable|string|max:100',
            'duration' => 'nullable|string|max:50',
            'youtube_url' => 'nullable|string|max:1000',
            'spotify_url' => 'nullable|string|max:1000',
            'guest_name' => 'nullable|string|max:255',
            'guest_title' => 'nullable|string|max:500',
            'is_featured' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $coverFile = $request->file('cover');
        $avatarFile = $request->file('avatar');
        $updated = $this->podcastService->update($media, $validated, $coverFile, $avatarFile);

        return response()->json([
            'success' => true,
            'message' => 'Podcast episode updated successfully',
            'data' => [
                'id' => $updated->id,
                'title' => $updated->title,
                'isActive' => (bool) $updated->is_active,
                'isFeatured' => (bool) $updated->is_featured,
            ],
        ]);
    }

    /**
     * Delete podcast episode.
     */
    public function destroyPodcast(string $id): JsonResponse
    {
        $media = $this->podcastService->findById($id);

        if (! $media) {
            return response()->json(['success' => false, 'message' => 'Podcast episode not found'], 404);
        }

        $this->podcastService->delete($media);

        return response()->json([
            'success' => true,
            'message' => 'Podcast episode deleted successfully',
        ]);
    }

    /**
     * Toggle podcast featured status.
     */
    public function togglePodcastFeatured(string $id): JsonResponse
    {
        $media = $this->podcastService->findById($id);

        if (! $media) {
            return response()->json(['success' => false, 'message' => 'Podcast episode not found'], 404);
        }

        $this->podcastService->toggleFeatured($media);

        return response()->json([
            'success' => true,
            'message' => 'Featured status updated',
            'data' => [
                'id' => $media->id,
                'isFeatured' => (bool) $media->is_featured,
            ],
        ]);
    }

    /**
     * Toggle podcast live status.
     */
    public function togglePodcastStatus(string $id): JsonResponse
    {
        $media = $this->podcastService->findById($id);

        if (! $media) {
            return response()->json(['success' => false, 'message' => 'Podcast episode not found'], 404);
        }

        $this->podcastService->toggleActive($media);

        return response()->json([
            'success' => true,
            'message' => 'Live status updated',
            'data' => [
                'id' => $media->id,
                'isActive' => (bool) $media->is_active,
            ],
        ]);
    }

    /**
     * Get success stories for admin panel.
     */
    public function successStories(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->query('search', ''),
            'status' => $request->query('status', 'all'),
        ];

        $stories = $this->storyService->getAll($filters);
        $totalCount = $stories->count();
        $activeCount = $stories->where('is_active', true)->count();
        $customCount = $stories->where('has_custom_cover', true)->count();

        $mapped = $stories->map(function (WebSuccessStory $item): array {
            return [
                'id' => $item->id,
                'personName' => $item->person_name,
                'designation' => $item->designation,
                'company' => $item->company,
                'storyTitle' => $item->story_title,
                'quote' => $item->quote,
                'youtubeUrl' => $item->youtube_url,
                'youtubeVideoId' => $item->youtube_video_id,
                'youtubeEmbedUrl' => $item->youtube_embed_url,
                'coverImageUrl' => $item->cover_image_url ?: $item->youtube_thumbnail_url,
                'hasCustomCover' => (bool) $item->has_custom_cover,
                'isActive' => (bool) $item->is_active,
                'sortOrder' => $item->sort_order,
                'createdAt' => $item->created_at ? $item->created_at->toIso8601String() : null,
            ];
        });

        return response()->json([
            'success' => true,
            'count' => $mapped->count(),
            'total' => $totalCount,
            'stats' => [
                'total' => $totalCount,
                'active' => $activeCount,
                'customCovers' => $customCount,
            ],
            'data' => $mapped,
        ]);
    }

    /**
     * Store new success story.
     */
    public function storeSuccessStory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'person_name' => 'required|string|max:255',
            'designation' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'story_title' => 'required|string|max:255',
            'quote' => 'required|string|max:1000',
            'youtube_url' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
        ]);

        $coverFile = $request->file('cover_image');
        $story = $this->storyService->create($validated, $coverFile);

        return response()->json([
            'success' => true,
            'message' => 'Success story created successfully',
            'data' => [
                'id' => $story->id,
                'personName' => $story->person_name,
                'storyTitle' => $story->story_title,
                'isActive' => (bool) $story->is_active,
            ],
        ], 201);
    }

    /**
     * Update success story.
     */
    public function updateSuccessStory(Request $request, string $id): JsonResponse
    {
        $story = $this->storyService->findById($id);

        if (! $story) {
            return response()->json(['success' => false, 'message' => 'Success story not found'], 404);
        }

        $validated = $request->validate([
            'person_name' => 'nullable|string|max:255',
            'designation' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'story_title' => 'nullable|string|max:255',
            'quote' => 'nullable|string|max:1000',
            'youtube_url' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
        ]);

        $coverFile = $request->file('cover_image');
        $updated = $this->storyService->update($story, $validated, $coverFile);

        return response()->json([
            'success' => true,
            'message' => 'Success story updated successfully',
            'data' => [
                'id' => $updated->id,
                'personName' => $updated->person_name,
                'storyTitle' => $updated->story_title,
                'isActive' => (bool) $updated->is_active,
            ],
        ]);
    }

    /**
     * Delete success story.
     */
    public function destroySuccessStory(string $id): JsonResponse
    {
        $story = $this->storyService->findById($id);

        if (! $story) {
            return response()->json(['success' => false, 'message' => 'Success story not found'], 404);
        }

        $this->storyService->delete($story);

        return response()->json([
            'success' => true,
            'message' => 'Success story deleted successfully',
        ]);
    }

    /**
     * Toggle success story active status.
     */
    public function toggleSuccessStoryStatus(string $id): JsonResponse
    {
        $story = $this->storyService->findById($id);

        if (! $story) {
            return response()->json(['success' => false, 'message' => 'Success story not found'], 404);
        }

        $this->storyService->toggleStatus($story);

        return response()->json([
            'success' => true,
            'message' => 'Live status updated',
            'data' => [
                'id' => $story->id,
                'isActive' => (bool) $story->is_active,
            ],
        ]);
    }
}
