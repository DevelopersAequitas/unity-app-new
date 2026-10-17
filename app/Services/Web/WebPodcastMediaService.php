<?php

declare(strict_types=1);

namespace App\Services\Web;

use App\Models\Web\WebMedia;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class WebPodcastMediaService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<WebMedia>
     */
    public function listPaginated(array $filters = [], int $perPage = 18): LengthAwarePaginator
    {
        $query = WebMedia::query();

        if (! empty($filters['tag']) && $filters['tag'] !== 'all') {
            $query->where('tag', $filters['tag']);
        }

        if (! empty($filters['format']) && $filters['format'] !== 'all') {
            $query->where('media_format', $filters['format']);
        }

        if (! empty($filters['category']) && $filters['category'] !== 'all') {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('subtitle', 'like', "%{$search}%")
                    ->orWhere('guest_name', 'like', "%{$search}%")
                    ->orWhere('tag', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, WebMedia>
     */
    public function allActive(array $filters = []): Collection
    {
        $query = WebMedia::query()->where('is_active', true);

        if (! empty($filters['tag']) && $filters['tag'] !== 'all') {
            $query->where('tag', $filters['tag']);
        }

        if (! empty($filters['format']) && $filters['format'] !== 'all') {
            $query->where('media_format', $filters['format']);
        }

        if (isset($filters['featured']) && $filters['featured'] !== 'all') {
            $query->where('is_featured', filter_var($filters['featured'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('subtitle', 'like', "%{$search}%")
                    ->orWhere('guest_name', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * @return Collection<int, WebMedia>
     */
    public function featured(int $limit = 6): Collection
    {
        return WebMedia::query()
            ->where('is_active', true)
            ->where('is_featured', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get();
    }

    public function findById(string $id): ?WebMedia
    {
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
        $media->title = (string) ($data['title'] ?? '');
        $media->subtitle = ! empty($data['subtitle']) ? (string) $data['subtitle'] : null;
        $media->tag = (string) ($data['tag'] ?? 'MANUFACTURING');
        $media->media_format = (string) ($data['media_format'] ?? 'podcast');
        $media->duration = (string) ($data['duration'] ?? '45 mins');
        $media->youtube_url = $youtubeUrl;
        $media->youtube_video_id = $youtubeVideoId;
        $media->spotify_url = ! empty($data['spotify_url']) ? (string) $data['spotify_url'] : null;
        $media->apple_podcast_url = ! empty($data['apple_podcast_url']) ? (string) $data['apple_podcast_url'] : null;
        $media->external_url = ! empty($data['external_url']) ? (string) $data['external_url'] : null;
        $media->cover_image = $coverPath;
        $media->guest_name = ! empty($data['guest_name']) ? (string) $data['guest_name'] : null;
        $media->guest_title = ! empty($data['guest_title']) ? (string) $data['guest_title'] : null;
        $media->guest_avatar = $avatarPath;
        $media->show_notes = $showNotes;
        $media->file_type = ! empty($youtubeUrl) ? 'video' : 'audio';
        $media->file_path = $coverPath;
        $media->file_name = Str::slug($media->title);
        $media->category = 'podcast';
        $media->sort_order = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;
        $media->is_featured = isset($data['is_featured']) ? (bool) $data['is_featured'] : false;
        $media->is_active = isset($data['is_active']) ? (bool) $data['is_active'] : true;
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
            $media->cover_image = $this->storeUploadedFile($cover);
        } elseif (! empty($data['cover_url'])) {
            $media->cover_image = (string) $data['cover_url'];
        }

        if ($avatar instanceof UploadedFile && $avatar->isValid()) {
            $this->deletePhysicalFile($media->guest_avatar);
            $media->guest_avatar = $this->storeUploadedFile($avatar);
        } elseif (! empty($data['guest_avatar_url'])) {
            $media->guest_avatar = (string) $data['guest_avatar_url'];
        }

        if (array_key_exists('youtube_url', $data)) {
            $youtubeUrl = ! empty($data['youtube_url']) ? (string) $data['youtube_url'] : null;
            $media->youtube_url = $youtubeUrl;
            $media->youtube_video_id = $youtubeUrl ? WebMedia::extractYouTubeId($youtubeUrl) : null;

            if (empty($media->cover_image) && $media->youtube_video_id) {
                $media->cover_image = WebMedia::buildYouTubeThumbnailUrl($media->youtube_video_id);
            }
        }

        $media->title = (string) ($data['title'] ?? $media->title);
        $media->subtitle = array_key_exists('subtitle', $data) ? (! empty($data['subtitle']) ? (string) $data['subtitle'] : null) : $media->subtitle;
        $media->tag = (string) ($data['tag'] ?? $media->tag);
        $media->media_format = (string) ($data['media_format'] ?? $media->media_format);
        $media->duration = (string) ($data['duration'] ?? $media->duration);
        $media->spotify_url = array_key_exists('spotify_url', $data) ? (! empty($data['spotify_url']) ? (string) $data['spotify_url'] : null) : $media->spotify_url;
        $media->apple_podcast_url = array_key_exists('apple_podcast_url', $data) ? (! empty($data['apple_podcast_url']) ? (string) $data['apple_podcast_url'] : null) : $media->apple_podcast_url;
        $media->external_url = array_key_exists('external_url', $data) ? (! empty($data['external_url']) ? (string) $data['external_url'] : null) : $media->external_url;
        $media->guest_name = array_key_exists('guest_name', $data) ? (! empty($data['guest_name']) ? (string) $data['guest_name'] : null) : $media->guest_name;
        $media->guest_title = array_key_exists('guest_title', $data) ? (! empty($data['guest_title']) ? (string) $data['guest_title'] : null) : $media->guest_title;

        if (array_key_exists('show_notes', $data)) {
            $media->show_notes = $this->normalizeShowNotes($data['show_notes']);
        }

        $media->sort_order = isset($data['sort_order']) ? (int) $data['sort_order'] : $media->sort_order;
        $media->is_featured = isset($data['is_featured']) ? (bool) $data['is_featured'] : $media->is_featured;
        $media->is_active = isset($data['is_active']) ? (bool) $data['is_active'] : $media->is_active;
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
     * @return list<string>
     */
    protected function normalizeShowNotes(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('trim', $raw)));
        }

        if (is_string($raw) && ! empty(trim($raw))) {
            // Split by newlines
            $lines = preg_split("/\r\n|\n|\r/", $raw);
            if ($lines !== false) {
                return array_values(array_filter(array_map('trim', $lines)));
            }
        }

        return [];
    }

    protected function storeUploadedFile(UploadedFile $file): string
    {
        $destinationDir = public_path('uploads/web-media');
        if (! File::isDirectory($destinationDir)) {
            File::makeDirectory($destinationDir, 0755, true, true);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $filename = Str::uuid().'.'.$extension;
        $file->move($destinationDir, $filename);

        return '/uploads/web-media/'.$filename;
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
        }
    }
}
