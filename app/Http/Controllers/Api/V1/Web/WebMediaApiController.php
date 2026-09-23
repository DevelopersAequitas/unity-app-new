<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Web;

use App\Http\Controllers\Controller;
use App\Models\Web\WebMedia;
use App\Models\Web\WebPageMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WebMediaApiController extends Controller
{
    /**
     * Get Page Media items formatted for Next.js website consumption.
     */
    public function index(Request $request): JsonResponse
    {
        $pageId = $request->query('pageId');
        $slug = $request->query('slug');

        if (! Schema::hasTable('web_page_medias')) {
            return response()->json([
                'success' => true,
                'count' => 0,
                'data' => [],
            ]);
        }

        $query = WebPageMedia::query()->where('is_active', true);

        if (! empty($pageId) && $pageId !== 'all') {
            $query->where('page_id', $pageId);
        }

        if (! empty($slug)) {
            $query->where('page_slug', $slug);
        }

        $items = $query->orderBy('sort_order')->orderBy('created_at', 'desc')->get();

        // Map to exact Next.js PageMediaItem format
        $mapped = $items->map(function (WebPageMedia $item): array {
            return [
                'id' => $item->id,
                'pageId' => $item->page_id,
                'pageName' => $item->page_title ?? ucfirst($item->page_id),
                'pageSlug' => $item->page_slug ?? '/'.($item->page_id === 'home' ? '' : $item->page_id),
                'sectionName' => $item->section_key ?? 'Section Banner',
                'title' => $item->title,
                'description' => $item->description ?? '',
                'mediaType' => str_contains(strtolower($item->media_type ?? 'video'), 'photo') || str_contains(strtolower($item->media_type ?? 'video'), 'image') ? 'photo' : 'video',
                'sourceType' => $item->media_source === 'localhost' ? 'localhost' : 'url',
                'mediaUrl' => $item->media_url ?? '',
                'isActive' => (bool) $item->is_active,
                'createdAt' => $item->created_at?->toIso8601String() ?? now()->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'count' => $mapped->count(),
            'data' => $mapped,
        ]);
    }

    /**
     * Get raw media library assets.
     */
    public function assets(Request $request): JsonResponse
    {
        $category = $request->query('category');
        $type = $request->query('type');

        $query = WebMedia::query();

        if (! empty($category)) {
            $query->where('category', $category);
        }

        if (! empty($type)) {
            $query->where('file_type', $type);
        }

        $assets = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $assets,
        ]);
    }

    /**
     * Upload an image or video file.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:102400', // max 100MB
            'title' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
        ]);

        $file = $request->file('file');
        if (! $file || ! $file->isValid()) {
            return response()->json(['success' => false, 'message' => 'Invalid file uploaded'], 422);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType() ?? '';
        $isVideo = str_starts_with($mime, 'video/') || in_array($extension, ['mp4', 'webm', 'mov', 'avi', 'mkv'], true);
        $fileType = $isVideo ? 'video' : 'image';

        $fileName = Str::uuid().'.'.$extension;
        $destinationPath = public_path('uploads/web-media');

        if (! File::isDirectory($destinationPath)) {
            File::makeDirectory($destinationPath, 0755, true, true);
        }

        $file->move($destinationPath, $fileName);
        $publicUrl = asset('uploads/web-media/'.$fileName);

        $media = WebMedia::create([
            'title' => $request->input('title') ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'file_name' => $fileName,
            'file_path' => '/uploads/web-media/'.$fileName,
            'file_type' => $fileType,
            'file_size' => filesize($destinationPath.DIRECTORY_SEPARATOR.$fileName),
            'category' => $request->input('category', 'general'),
            'alt_text' => $request->input('title', ''),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File uploaded successfully',
            'data' => [
                'id' => $media->id,
                'fileName' => $fileName,
                'filePath' => '/uploads/web-media/'.$fileName,
                'url' => $publicUrl,
                'type' => $fileType,
            ],
        ]);
    }

    /**
     * Store or update a page media assignment.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|string',
            'page_id' => 'required|string',
            'page_title' => 'nullable|string',
            'page_slug' => 'nullable|string',
            'section_key' => 'nullable|string',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'media_type' => 'required|string',
            'media_source' => 'required|string',
            'media_url' => 'required|string',
            'local_file_name' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $item = null;
        if (! empty($validated['id'])) {
            $item = WebPageMedia::find($validated['id']);
        }

        if ($item) {
            $item->update($validated);
        } else {
            $item = WebPageMedia::create($validated);
        }

        return response()->json([
            'success' => true,
            'message' => 'Page media saved successfully',
            'data' => $item,
        ]);
    }

    /**
     * Delete a page media assignment.
     */
    public function destroy(string $id): JsonResponse
    {
        $item = WebPageMedia::find($id);
        if ($item) {
            $item->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Page media deleted successfully',
        ]);
    }
}
