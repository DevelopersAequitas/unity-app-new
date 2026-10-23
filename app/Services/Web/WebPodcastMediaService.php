<?php

declare(strict_types=1);

namespace App\Services\Web;

use App\Models\Web\WebMedia;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WebPodcastMediaService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<WebMedia>
     */
    public function listPaginated(array $filters = [], int $perPage = 18): LengthAwarePaginator
    {
        if (! Schema::hasTable('web_media')) {
            return new ConcretePaginator([], 0, $perPage);
        }

        $query = WebMedia::query();

        if (Schema::hasColumn('web_media', 'tag') && ! empty($filters['tag']) && $filters['tag'] !== 'all') {
            $query->where('tag', $filters['tag']);
        }

        if (Schema::hasColumn('web_media', 'media_format') && ! empty($filters['format']) && $filters['format'] !== 'all') {
            $query->where('media_format', $filters['format']);
        }

        if (Schema::hasColumn('web_media', 'category') && ! empty($filters['category']) && $filters['category'] !== 'all') {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search): void {
                if (Schema::hasColumn('web_media', 'title')) {
                    $q->where('title', 'like', "%{$search}%");
                }
                if (Schema::hasColumn('web_media', 'subtitle')) {
                    $q->orWhere('subtitle', 'like', "%{$search}%");
                }
                if (Schema::hasColumn('web_media', 'guest_name')) {
                    $q->orWhere('guest_name', 'like', "%{$search}%");
                }
                if (Schema::hasColumn('web_media', 'tag')) {
                    $q->orWhere('tag', 'like', "%{$search}%");
                }
            });
        }

        if (Schema::hasColumn('web_media', 'sort_order')) {
            $query->orderBy('sort_order', 'asc');
        }

        if (Schema::hasColumn('web_media', 'created_at')) {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, WebMedia>
     */
    public function allActive(array $filters = []): Collection
    {
        if (! Schema::hasTable('web_media')) {
            return new Collection;
        }

        $query = WebMedia::query();

        if (Schema::hasColumn('web_media', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('web_media', 'tag') && ! empty($filters['tag']) && $filters['tag'] !== 'all') {
            $query->where('tag', $filters['tag']);
        }

        if (Schema::hasColumn('web_media', 'media_format') && ! empty($filters['format']) && $filters['format'] !== 'all') {
            $query->where('media_format', $filters['format']);
        }

        if (Schema::hasColumn('web_media', 'is_featured') && isset($filters['featured']) && $filters['featured'] !== 'all') {
            $query->where('is_featured', filter_var($filters['featured'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search): void {
                if (Schema::hasColumn('web_media', 'title')) {
                    $q->where('title', 'like', "%{$search}%");
                }
                if (Schema::hasColumn('web_media', 'subtitle')) {
                    $q->orWhere('subtitle', 'like', "%{$search}%");
                }
                if (Schema::hasColumn('web_media', 'guest_name')) {
                    $q->orWhere('guest_name', 'like', "%{$search}%");
                }
            });
        }

        if (Schema::hasColumn('web_media', 'sort_order')) {
            $query->orderBy('sort_order', 'asc');
        }

        if (Schema::hasColumn('web_media', 'created_at')) {
            $query->orderBy('created_at', 'desc');
        }

        return $query->get();
    }

    /**
     * @return Collection<int, WebMedia>
     */
    public function featured(int $limit = 6): Collection
    {
        if (! Schema::hasTable('web_media')) {
            return new Collection;
        }

        $query = WebMedia::query();

        if (Schema::hasColumn('web_media', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('web_media', 'is_featured')) {
            $query->where('is_featured', true);
        }

        if (Schema::hasColumn('web_media', 'sort_order')) {
            $query->orderBy('sort_order', 'asc');
        }

        if (Schema::hasColumn('web_media', 'created_at')) {
            $query->orderBy('created_at', 'desc');
        }

        return $query->take($limit)->get();
    }

    public function findById(string $id): ?WebMedia
    {
        if (! Schema::hasTable('web_media')) {
            return null;
        }

        return WebMedia::find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $cover = null, ?UploadedFile $avatar = null): WebMedia
    {
        $coverPath = (string) ($data['cover_url'] ?? '');
        if ($cover instanceof UploadedFile && $cover->isValid()) {
            $coverPath = $this->storeUploadedFile($cover);
        }

        $avatarPath = (string) ($data['guest_avatar_url'] ?? '');
        if ($avatar instanceof UploadedFile && $avatar->isValid()) {
            $avatarPath = $this->storeUploadedFile($avatar);
        }

        $youtubeUrl = ! empty($data['youtube_url']) ? (string) $data['youtube_url'] : null;
        $youtubeVideoId = $youtubeUrl ? WebMedia::extractYouTubeId($youtubeUrl) : null;

        // Auto fallback for cover image if empty and youtube video id exists
        if (empty($coverPath) && $youtubeVideoId) {
            $coverPath = WebMedia::buildYouTubeThumbnailUrl($youtubeVideoId);
        }

        $showNotes = $this->normalizeShowNotes($data['show_notes'] ?? null);

        $media = new WebMedia;

        if (Schema::hasColumn('web_media', 'title')) {
            $media->title = (string) ($data['title'] ?? '');
        }

        if (Schema::hasColumn('web_media', 'name')) {
            $media->name = ! empty($data['title']) ? (string) $data['title'] : 'podcast-media';
        }

        if (Schema::hasColumn('web_media', 'url')) {
            $media->url = $coverPath ?: ($youtubeUrl ?: '');
        }

        if (Schema::hasColumn('web_media', 'subtitle')) {
            $media->subtitle = ! empty($data['subtitle']) ? (string) $data['subtitle'] : null;
        }

        if (Schema::hasColumn('web_media', 'tag')) {
            $media->tag = ! empty($data['tag']) ? (string) $data['tag'] : 'MANUFACTURING';
        }

        if (Schema::hasColumn('web_media', 'media_format')) {
            $media->media_format = ! empty($data['media_format']) ? (string) $data['media_format'] : 'podcast';
        }

        if (Schema::hasColumn('web_media', 'duration')) {
            $media->duration = ! empty($data['duration']) ? (string) $data['duration'] : '45 mins';
        }

        if (Schema::hasColumn('web_media', 'youtube_url')) {
            $media->youtube_url = $youtubeUrl;
        }

        if (Schema::hasColumn('web_media', 'youtube_video_id')) {
            $media->youtube_video_id = $youtubeVideoId;
        }

        if (Schema::hasColumn('web_media', 'spotify_url')) {
            $media->spotify_url = ! empty($data['spotify_url']) ? (string) $data['spotify_url'] : null;
        }

        if (Schema::hasColumn('web_media', 'apple_podcast_url')) {
            $media->apple_podcast_url = ! empty($data['apple_podcast_url']) ? (string) $data['apple_podcast_url'] : null;
        }

        if (Schema::hasColumn('web_media', 'external_url')) {
            $media->external_url = ! empty($data['external_url']) ? (string) $data['external_url'] : null;
        }

        if (Schema::hasColumn('web_media', 'cover_image')) {
            $media->cover_image = $coverPath;
        }

        if (Schema::hasColumn('web_media', 'guest_name')) {
            $media->guest_name = ! empty($data['guest_name']) ? (string) $data['guest_name'] : null;
        }

        if (Schema::hasColumn('web_media', 'guest_title')) {
            $media->guest_title = ! empty($data['guest_title']) ? (string) $data['guest_title'] : null;
        }

        if (Schema::hasColumn('web_media', 'guest_avatar')) {
            $media->guest_avatar = $avatarPath;
        }

        if (Schema::hasColumn('web_media', 'show_notes')) {
            $media->show_notes = $showNotes;
        }

        if (Schema::hasColumn('web_media', 'file_type')) {
            $media->file_type = ! empty($youtubeUrl) ? 'video' : 'audio';
        }

        if (Schema::hasColumn('web_media', 'file_path')) {
            $media->file_path = $coverPath;
        }

        if (Schema::hasColumn('web_media', 'file_name')) {
            $media->file_name = Str::slug((string) ($data['title'] ?? 'podcast-media'));
        }

        if (Schema::hasColumn('web_media', 'category')) {
            $media->category = 'podcast';
        }

        if (Schema::hasColumn('web_media', 'sort_order')) {
            $media->sort_order = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;
        }

        if (Schema::hasColumn('web_media', 'is_featured')) {
            $media->is_featured = isset($data['is_featured']) ? (bool) $data['is_featured'] : false;
        }

        if (Schema::hasColumn('web_media', 'is_active')) {
            $media->is_active = isset($data['is_active']) ? (bool) $data['is_active'] : true;
        }

        $media->save();

        return $media;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(WebMedia $media, array $data, ?UploadedFile $cover = null, ?UploadedFile $avatar = null): WebMedia
    {
        if ($cover instanceof UploadedFile && $cover->isValid()) {
            $this->deletePhysicalFile($media->cover_image);
            $newCover = $this->storeUploadedFile($cover);
            if (Schema::hasColumn('web_media', 'cover_image')) {
                $media->cover_image = $newCover;
            }
        } elseif (! empty($data['cover_url']) && Schema::hasColumn('web_media', 'cover_image')) {
            $media->cover_image = (string) $data['cover_url'];
        }

        if ($avatar instanceof UploadedFile && $avatar->isValid()) {
            $this->deletePhysicalFile($media->guest_avatar);
            $newAvatar = $this->storeUploadedFile($avatar);
            if (Schema::hasColumn('web_media', 'guest_avatar')) {
                $media->guest_avatar = $newAvatar;
            }
        } elseif (! empty($data['guest_avatar_url']) && Schema::hasColumn('web_media', 'guest_avatar')) {
            $media->guest_avatar = (string) $data['guest_avatar_url'];
        }

        if (array_key_exists('youtube_url', $data) && Schema::hasColumn('web_media', 'youtube_url')) {
            $youtubeUrl = ! empty($data['youtube_url']) ? (string) $data['youtube_url'] : null;
            $media->youtube_url = $youtubeUrl;
            if (Schema::hasColumn('web_media', 'youtube_video_id')) {
                $media->youtube_video_id = $youtubeUrl ? WebMedia::extractYouTubeId($youtubeUrl) : null;
            }

            if (empty($media->cover_image) && $media->youtube_video_id && Schema::hasColumn('web_media', 'cover_image')) {
                $media->cover_image = WebMedia::buildYouTubeThumbnailUrl($media->youtube_video_id);
            }
        }

        if (array_key_exists('title', $data) && Schema::hasColumn('web_media', 'title')) {
            $media->title = (string) $data['title'];
            if (Schema::hasColumn('web_media', 'name')) {
                $media->name = (string) $data['title'];
            }
        }

        if (array_key_exists('subtitle', $data) && Schema::hasColumn('web_media', 'subtitle')) {
            $media->subtitle = ! empty($data['subtitle']) ? (string) $data['subtitle'] : null;
        }

        if (! empty($data['tag']) && Schema::hasColumn('web_media', 'tag')) {
            $media->tag = (string) $data['tag'];
        }

        if (! empty($data['media_format']) && Schema::hasColumn('web_media', 'media_format')) {
            $media->media_format = (string) $data['media_format'];
        }

        if (! empty($data['duration']) && Schema::hasColumn('web_media', 'duration')) {
            $media->duration = (string) $data['duration'];
        }

        if (array_key_exists('spotify_url', $data) && Schema::hasColumn('web_media', 'spotify_url')) {
            $media->spotify_url = ! empty($data['spotify_url']) ? (string) $data['spotify_url'] : null;
        }

        if (array_key_exists('apple_podcast_url', $data) && Schema::hasColumn('web_media', 'apple_podcast_url')) {
            $media->apple_podcast_url = ! empty($data['apple_podcast_url']) ? (string) $data['apple_podcast_url'] : null;
        }

        if (array_key_exists('external_url', $data) && Schema::hasColumn('web_media', 'external_url')) {
            $media->external_url = ! empty($data['external_url']) ? (string) $data['external_url'] : null;
        }

        if (array_key_exists('guest_name', $data) && Schema::hasColumn('web_media', 'guest_name')) {
            $media->guest_name = ! empty($data['guest_name']) ? (string) $data['guest_name'] : null;
        }

        if (array_key_exists('guest_title', $data) && Schema::hasColumn('web_media', 'guest_title')) {
            $media->guest_title = ! empty($data['guest_title']) ? (string) $data['guest_title'] : null;
        }

        if (array_key_exists('show_notes', $data) && Schema::hasColumn('web_media', 'show_notes')) {
            $media->show_notes = $this->normalizeShowNotes($data['show_notes']);
        }

        if (isset($data['sort_order']) && Schema::hasColumn('web_media', 'sort_order')) {
            $media->sort_order = (int) $data['sort_order'];
        }

        if (isset($data['is_featured']) && Schema::hasColumn('web_media', 'is_featured')) {
            $media->is_featured = (bool) $data['is_featured'];
        }

        if (isset($data['is_active']) && Schema::hasColumn('web_media', 'is_active')) {
            $media->is_active = (bool) $data['is_active'];
        }

        $media->save();

        return $media;
    }

    public function delete(WebMedia $media): bool
    {
        $this->deletePhysicalFile($media->cover_image);
        $this->deletePhysicalFile($media->guest_avatar);

        return (bool) $media->delete();
    }

    public function toggleFeatured(WebMedia $media): bool
    {
        $media->is_featured = ! $media->is_featured;

        return $media->save();
    }

    public function toggleActive(WebMedia $media): bool
    {
        $media->is_active = ! $media->is_active;

        return $media->save();
    }

    /**
     * Convert multiline show notes into an array of string bullet items.
     *
     * @return array<int, string>
     */
    protected function normalizeShowNotes(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('strval', $raw)));
        }

        if (is_string($raw) && ! empty(trim($raw))) {
            $lines = preg_split("/\r\n|\n|\r/", $raw);
            if ($lines !== false) {
                return array_values(array_filter(array_map('trim', $lines)));
            }
        }

        return [];
    }

    protected function storeUploadedFile(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = Str::uuid().'.'.$extension;

        // Try public/uploads/web-media first
        try {
            $destinationDir = public_path('uploads/web-media');
            if (! File::isDirectory($destinationDir)) {
                File::makeDirectory($destinationDir, 0755, true, true);
            }

            $file->move($destinationDir, $filename);

            return '/uploads/web-media/'.$filename;
        } catch (\Throwable $e) {
            // Fallback to Laravel public storage disk
            $storedPath = Storage::disk('public')->putFileAs('web-media', $file, $filename);

            return '/storage/'.$storedPath;
        }
    }

    protected function deletePhysicalFile(?string $path): void
    {
        if (empty($path)) {
            return;
        }

        if (Str::startsWith($path, '/uploads/web-media/')) {
            $local = public_path(ltrim($path, '/'));
            if (File::exists($local)) {
                File::delete($local);
            }
        } elseif (Str::startsWith($path, '/storage/web-media/')) {
            $storageRelative = Str::after($path, '/storage/');
            if (Storage::disk('public')->exists($storageRelative)) {
                Storage::disk('public')->delete($storageRelative);
            }
        }
    }
}
