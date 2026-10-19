<?php

declare(strict_types=1);

namespace App\Services\Web;

use App\Models\Web\WebGallery;
use App\Models\Web\WebGalleryItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class WebGalleryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<WebGalleryItem>
     */
    public function listPaginated(array $filters = [], int $perPage = 18): LengthAwarePaginator
    {
        $query = WebGalleryItem::query()->with('gallery');

        if (! empty($filters['category']) && $filters['category'] !== 'all') {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['city']) && $filters['city'] !== 'all') {
            $query->where('city', $filters['city']);
        }

        if (! empty($filters['circle']) && $filters['circle'] !== 'all') {
            $query->where('circle', $filters['circle']);
        }

        if (! empty($filters['year']) && $filters['year'] !== 'all') {
            $query->where('year', $filters['year']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('caption', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('circle', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, WebGalleryItem>
     */
    public function allActive(array $filters = []): Collection
    {
        $query = WebGalleryItem::query()->where('is_active', true);

        if (! empty($filters['category']) && $filters['category'] !== 'all') {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['city']) && $filters['city'] !== 'all') {
            $query->where('city', $filters['city']);
        }

        if (! empty($filters['circle']) && $filters['circle'] !== 'all') {
            $query->where('circle', $filters['circle']);
        }

        if (! empty($filters['year']) && $filters['year'] !== 'all') {
            $query->where('year', $filters['year']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('caption', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('circle', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * @return Collection<int, WebGallery>
     */
    public function listCollections(): Collection
    {
        return WebGallery::query()
            ->where('is_active', true)
            ->withCount('items')
            ->orderBy('sort_order', 'asc')
            ->get();
    }

    public function findItemById(string $id): ?WebGalleryItem
    {
        return WebGalleryItem::find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createItem(array $data, ?UploadedFile $file = null): WebGalleryItem
    {
        $imagePath = (string) ($data['image_url'] ?? '');

        if ($file instanceof UploadedFile && $file->isValid()) {
            $imagePath = $this->storeUploadedFile($file);
        }

        $caption = ! empty($data['caption']) ? (string) $data['caption'] : null;

        $title = (string) ($data['title'] ?? '');
        if (empty($title)) {
            $title = $caption ? Str::limit($caption, 60, '...') : 'Gallery Photo';
        }

        $item = new WebGalleryItem;
        $item->title = $title;
        $item->category = ! empty($data['category']) ? (string) $data['category'] : 'Gallery';
        $item->city = (string) ($data['city'] ?? '');
        $item->circle = (string) ($data['circle'] ?? '');
        $item->year = ! empty($data['year']) ? (string) $data['year'] : (string) date('Y');
        $item->image_path = $imagePath;
        $item->caption = $caption;
        $item->gallery_id = ! empty($data['gallery_id']) ? (string) $data['gallery_id'] : null;
        $item->sort_order = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;
        $item->is_active = isset($data['is_active']) ? (bool) $data['is_active'] : true;
        $item->save();

        return $item;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateItem(WebGalleryItem $item, array $data, ?UploadedFile $file = null): WebGalleryItem
    {
        if ($file instanceof UploadedFile && $file->isValid()) {
            $this->deletePhysicalFile($item->image_path);
            $item->image_path = $this->storeUploadedFile($file);
        } elseif (! empty($data['image_url'])) {
            $item->image_path = (string) $data['image_url'];
        }

        if (array_key_exists('title', $data) && ! empty($data['title'])) {
            $item->title = (string) $data['title'];
        } elseif (empty($item->title) && ! empty($data['caption'])) {
            $item->title = Str::limit((string) $data['caption'], 60, '...');
        }

        if (array_key_exists('category', $data) && ! empty($data['category'])) {
            $item->category = (string) $data['category'];
        }

        if (array_key_exists('city', $data)) {
            $item->city = (string) $data['city'];
        }

        if (array_key_exists('circle', $data)) {
            $item->circle = (string) $data['circle'];
        }

        if (array_key_exists('year', $data) && ! empty($data['year'])) {
            $item->year = (string) $data['year'];
        }

        if (array_key_exists('caption', $data)) {
            $item->caption = ! empty($data['caption']) ? (string) $data['caption'] : null;
        }

        if (array_key_exists('gallery_id', $data)) {
            $item->gallery_id = ! empty($data['gallery_id']) ? (string) $data['gallery_id'] : null;
        }

        if (isset($data['sort_order'])) {
            $item->sort_order = (int) $data['sort_order'];
        }

        if (isset($data['is_active'])) {
            $item->is_active = (bool) $data['is_active'];
        }

        $item->save();

        return $item;
    }

    public function deleteItem(WebGalleryItem $item): bool
    {
        $this->deletePhysicalFile($item->image_path);

        return (bool) $item->delete();
    }

    public function toggleItemActive(WebGalleryItem $item): bool
    {
        $item->is_active = ! $item->is_active;

        return $item->save();
    }

    protected function storeUploadedFile(UploadedFile $file): string
    {
        $destinationDir = public_path('uploads/web-gallery');
        if (! File::isDirectory($destinationDir)) {
            File::makeDirectory($destinationDir, 0755, true, true);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $filename = Str::uuid().'.'.$extension;
        $file->move($destinationDir, $filename);

        return '/uploads/web-gallery/'.$filename;
    }

    protected function deletePhysicalFile(?string $path): void
    {
        if (empty($path)) {
            return;
        }

        if (Str::startsWith($path, '/uploads/web-gallery/')) {
            $local = public_path(ltrim($path, '/'));
            if (File::exists($local)) {
                File::delete($local);
            }
        }
    }
}
