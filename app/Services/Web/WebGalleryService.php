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

        $item = new WebGalleryItem;
        $item->title = (string) ($data['title'] ?? '');
        $item->category = (string) ($data['category'] ?? 'Annual Summit');
        $item->city = (string) ($data['city'] ?? 'Ahmedabad');
        $item->circle = (string) ($data['circle'] ?? 'All Circles');
        $item->year = (string) ($data['year'] ?? '2026');
        $item->image_path = $imagePath;
        $item->caption = ! empty($data['caption']) ? (string) $data['caption'] : null;
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

        $item->title = (string) ($data['title'] ?? $item->title);
        $item->category = (string) ($data['category'] ?? $item->category);
        $item->city = (string) ($data['city'] ?? $item->city);
        $item->circle = (string) ($data['circle'] ?? $item->circle);
        $item->year = (string) ($data['year'] ?? $item->year);
        $item->caption = array_key_exists('caption', $data) ? (! empty($data['caption']) ? (string) $data['caption'] : null) : $item->caption;
        $item->gallery_id = array_key_exists('gallery_id', $data) ? (! empty($data['gallery_id']) ? (string) $data['gallery_id'] : null) : $item->gallery_id;
        $item->sort_order = isset($data['sort_order']) ? (int) $data['sort_order'] : $item->sort_order;
        $item->is_active = isset($data['is_active']) ? (bool) $data['is_active'] : $item->is_active;
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
