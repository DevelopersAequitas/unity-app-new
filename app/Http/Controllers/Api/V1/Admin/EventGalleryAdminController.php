<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\EventGallery;
use App\Models\EventGalleryMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventGalleryAdminController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $galleries = EventGallery::query()
            ->withCount([
                'media as images_count' => fn ($q) => $q->where('media_type', 'image'),
                'media as videos_count' => fn ($q) => $q->where('media_type', 'video'),
            ])
            ->with(['media' => fn ($q) => $q->orderBy('sort_order')->orderBy('created_at')->limit(5)])
            ->when($request->search, function ($q, $search): void {
                $q->where('event_name', 'ilike', '%' . $search . '%');
            })
            ->latest('event_date')
            ->paginate((int) ($request->per_page ?? 20));

        return $this->success($galleries);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_name' => ['required', 'string', 'max:255'],
            'event_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
        ]);

        $gallery = EventGallery::query()->create([
            'event_name' => $validated['event_name'],
            'event_date' => $validated['event_date'] ?? now()->toDateString(),
            'description' => $validated['description'] ?? null,
            'created_by_admin_id' => auth()->id(),
        ]);

        return $this->success($gallery, 'Gallery created successfully.', 201);
    }

    public function show(string $id): JsonResponse
    {
        $gallery = EventGallery::query()
            ->with(['media' => fn ($q) => $q->orderBy('sort_order')->orderBy('created_at')])
            ->find($id);

        if (! $gallery) {
            return $this->error('Gallery not found', 404);
        }

        return $this->success($gallery);
    }

    public function media(string $id): JsonResponse
    {
        $media = EventGalleryMedia::query()
            ->where('event_gallery_id', $id)
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        return $this->success($media);
    }

    public function storeMedia(Request $request, string $id): JsonResponse
    {
        $gallery = EventGallery::query()->find($id);
        if (! $gallery) {
            return $this->error('Gallery not found', 404);
        }

        $validated = $request->validate([
            'media_type' => ['required', 'in:image,video'],
            'file_id' => ['nullable', 'string'],
            'caption' => ['nullable', 'string'],
        ]);

        $mediaItem = EventGalleryMedia::query()->create([
            'event_gallery_id' => $gallery->id,
            'media_type' => $validated['media_type'],
            'file_id' => $validated['file_id'] ?? null,
            'caption' => $validated['caption'] ?? null,
            'created_by_admin_id' => auth()->id(),
        ]);

        return $this->success($mediaItem, 'Media added successfully.', 201);
    }

    public function destroyMedia(string $id): JsonResponse
    {
        $media = EventGalleryMedia::query()->find($id);
        if (! $media) {
            return $this->error('Media not found', 404);
        }

        $media->delete();

        return $this->success(null, 'Media deleted successfully.');
    }
}