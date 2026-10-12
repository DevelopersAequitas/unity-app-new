<?php

declare(strict_types=1);

namespace App\Services\Web;

use App\Models\Web\WebSuccessStory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class WebSuccessStoryService
{
    /**
     * Get all success stories with optional filtering.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, WebSuccessStory>
     */
    public function getAll(array $filters = []): Collection
    {
        $query = WebSuccessStory::query();

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('person_name', 'like', "%{$search}%")
                    ->orWhere('designation', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%")
                    ->orWhere('story_title', 'like', "%{$search}%")
                    ->orWhere('quote', 'like', "%{$search}%");
            });
        }

        if (isset($filters['status']) && $filters['status'] !== 'all' && $filters['status'] !== '') {
            $query->where('is_active', $filters['status'] === 'active' || $filters['status'] === '1' || $filters['status'] === true);
        }

        return $query->orderBy('sort_order')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get active success stories for website display.
     *
     * @return Collection<int, WebSuccessStory>
     */
    public function getActive(): Collection
    {
        return WebSuccessStory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Find a story by UUID.
     */
    public function findById(string $id): ?WebSuccessStory
    {
        return WebSuccessStory::find($id);
    }

    /**
     * Create a new success story.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $file = null): WebSuccessStory
    {
        $youtubeUrl = (string) ($data['youtube_url'] ?? '');
        $videoId = WebSuccessStory::extractYouTubeId($youtubeUrl) ?? '';
        $thumbnailUrl = $videoId !== '' ? WebSuccessStory::buildYouTubeThumbnailUrl($videoId) : null;

        $customCoverPath = null;
        if ($file !== null && $file->isValid()) {
            $customCoverPath = $this->storeCoverImage($file);
        }

        $story = new WebSuccessStory;
        $story->person_name = (string) ($data['person_name'] ?? '');
        $story->designation = ! empty($data['designation']) ? (string) $data['designation'] : null;
        $story->company = ! empty($data['company']) ? (string) $data['company'] : null;
        $story->story_title = ! empty($data['story_title']) ? (string) $data['story_title'] : null;
        $story->quote = ! empty($data['quote']) ? (string) $data['quote'] : null;
        $story->youtube_url = $youtubeUrl;
        $story->youtube_video_id = $videoId;
        $story->youtube_thumbnail_url = $thumbnailUrl;
        $story->custom_cover_image = $customCoverPath;
        $story->sort_order = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;
        $story->is_active = isset($data['is_active']) ? (bool) $data['is_active'] : true;
        $story->save();

        return $story;
    }

    /**
     * Update an existing success story.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(WebSuccessStory $story, array $data, ?UploadedFile $file = null, bool $removeCustomCover = false): WebSuccessStory
    {
        $youtubeUrl = (string) ($data['youtube_url'] ?? $story->youtube_url);
        $videoId = WebSuccessStory::extractYouTubeId($youtubeUrl) ?? $story->youtube_video_id;
        $thumbnailUrl = $videoId !== '' ? WebSuccessStory::buildYouTubeThumbnailUrl($videoId) : $story->youtube_thumbnail_url;

        if ($removeCustomCover && ! empty($story->custom_cover_image)) {
            $this->deleteCoverFile($story->custom_cover_image);
            $story->custom_cover_image = null;
        }

        if ($file !== null && $file->isValid()) {
            if (! empty($story->custom_cover_image)) {
                $this->deleteCoverFile($story->custom_cover_image);
            }
            $story->custom_cover_image = $this->storeCoverImage($file);
        }

        $story->person_name = (string) ($data['person_name'] ?? $story->person_name);
        $story->designation = array_key_exists('designation', $data) ? ($data['designation'] ? (string) $data['designation'] : null) : $story->designation;
        $story->company = array_key_exists('company', $data) ? ($data['company'] ? (string) $data['company'] : null) : $story->company;
        $story->story_title = array_key_exists('story_title', $data) ? ($data['story_title'] ? (string) $data['story_title'] : null) : $story->story_title;
        $story->quote = array_key_exists('quote', $data) ? ($data['quote'] ? (string) $data['quote'] : null) : $story->quote;
        $story->youtube_url = $youtubeUrl;
        $story->youtube_video_id = $videoId;
        $story->youtube_thumbnail_url = $thumbnailUrl;
        $story->sort_order = isset($data['sort_order']) ? (int) $data['sort_order'] : $story->sort_order;
        if (array_key_exists('is_active', $data)) {
            $story->is_active = (bool) $data['is_active'];
        }

        $story->save();

        return $story;
    }

    /**
     * Delete a story and its custom uploaded cover file.
     */
    public function delete(WebSuccessStory $story): bool
    {
        if (! empty($story->custom_cover_image)) {
            $this->deleteCoverFile($story->custom_cover_image);
        }

        return (bool) $story->delete();
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleActive(WebSuccessStory $story): bool
    {
        $story->is_active = ! $story->is_active;
        $story->save();

        return $story->is_active;
    }

    /**
     * Save an uploaded cover image file to public/uploads/web-success-stories.
     */
    protected function storeCoverImage(UploadedFile $file): string
    {
        $destinationPath = public_path('uploads/web-success-stories');

        if (! File::isDirectory($destinationPath)) {
            File::makeDirectory($destinationPath, 0755, true, true);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $fileName = Str::uuid().'.'.$extension;
        $file->move($destinationPath, $fileName);

        return 'uploads/web-success-stories/'.$fileName;
    }

    /**
     * Remove existing cover file from disk.
     */
    protected function deleteCoverFile(string $relativePath): void
    {
        $fullPath = public_path($relativePath);
        if (File::exists($fullPath)) {
            File::delete($fullPath);
        }
    }
}
