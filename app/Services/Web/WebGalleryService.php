<?php

declare(strict_types=1);

namespace App\Services\Web;

use App\Models\Web\WebGallery;
use App\Models\Web\WebGalleryItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WebGalleryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<WebGalleryItem>
     */
    public function listPaginated(array $filters = [], int $perPage = 18): LengthAwarePaginator
    {
        if (! Schema::hasTable('web_gallery_items')) {
            return new ConcretePaginator([], 0, $perPage);
        }

        $query = WebGalleryItem::query();

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search): void {
                if (Schema::hasColumn('web_gallery_items', 'caption')) {
                    $q->where('caption', 'like', "%{$search}%");
                }
                if (Schema::hasColumn('web_gallery_items', 'title')) {
                    $q->orWhere('title', 'like', "%{$search}%");
                }
            });
        }

        if (Schema::hasColumn('web_gallery_items', 'sort_order')) {
            $query->orderBy('sort_order', 'asc');
        }

        if (Schema::hasColumn('web_gallery_items', 'created_at')) {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, WebGalleryItem>
     */
    public function allActive(array $filters = []): Collection
    {
        if (! Schema::hasTable('web_gallery_items')) {
            return new Collection;
        }

        $query = WebGalleryItem::query();

        if (Schema::hasColumn('web_gallery_items', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('web_gallery_items', 'sort_order')) {
            $query->orderBy('sort_order', 'asc');
        }

        if (Schema::hasColumn('web_gallery_items', 'created_at')) {
            $query->orderBy('created_at', 'desc');
        }

        return $query->get();
    }

    /**
     * @return Collection<int, WebGallery>
     */
    public function listCollections(): Collection
    {
        if (! Schema::hasTable('web_galleries')) {
            return new Collection;
        }

        $query = WebGallery::query();

        if (Schema::hasColumn('web_galleries', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('web_galleries', 'sort_order')) {
            $query->orderBy('sort_order', 'asc');
        }

        return $query->get();
    }

    public function findItemById(string $id): ?WebGalleryItem
    {
        if (! Schema::hasTable('web_gallery_items')) {
            return null;
        }

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

        $item = new WebGalleryItem;

        if (Schema::hasColumn('web_gallery_items', 'title')) {
            $title = (string) ($data['title'] ?? '');
            if (empty($title)) {
                $title = $caption ? Str::limit($caption, 60, '...') : 'Gallery Photo';
            }
            $item->title = $title;
        }

        if (Schema::hasColumn('web_gallery_items', 'category')) {
            $item->category = ! empty($data['category']) ? (string) $data['category'] : 'Gallery';
        }

        if (Schema::hasColumn('web_gallery_items', 'city')) {
            $item->city = (string) ($data['city'] ?? '');
        }

        if (Schema::hasColumn('web_gallery_items', 'circle')) {
            $item->circle = (string) ($data['circle'] ?? '');
        }

        if (Schema::hasColumn('web_gallery_items', 'year')) {
            $item->year = ! empty($data['year']) ? (string) $data['year'] : (string) date('Y');
        }

        if (Schema::hasColumn('web_gallery_items', 'image_path')) {
            $item->image_path = $imagePath;
        }

        if (Schema::hasColumn('web_gallery_items', 'caption')) {
            $item->caption = $caption;
        }

        if (Schema::hasColumn('web_gallery_items', 'gallery_id')) {
            $item->gallery_id = ! empty($data['gallery_id']) ? (string) $data['gallery_id'] : null;
        }

        if (Schema::hasColumn('web_gallery_items', 'sort_order')) {
            $item->sort_order = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;
        }

        if (Schema::hasColumn('web_gallery_items', 'is_active')) {
            $item->is_active = isset($data['is_active']) ? (bool) $data['is_active'] : true;
        }

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
        } elseif (! empty($data['image_url']) && Schema::hasColumn('web_gallery_items', 'image_path')) {
            $item->image_path = (string) $data['image_url'];
        }

        if (Schema::hasColumn('web_gallery_items', 'title')) {
            if (array_key_exists('title', $data) && ! empty($data['title'])) {
                $item->title = (string) $data['title'];
            } elseif (empty($item->title) && ! empty($data['caption'])) {
                $item->title = Str::limit((string) $data['caption'], 60, '...');
            }
        }

        if (Schema::hasColumn('web_gallery_items', 'category') && array_key_exists('category', $data) && ! empty($data['category'])) {
            $item->category = (string) $data['category'];
        }

        if (Schema::hasColumn('web_gallery_items', 'city') && array_key_exists('city', $data)) {
            $item->city = (string) $data['city'];
        }

        if (Schema::hasColumn('web_gallery_items', 'circle') && array_key_exists('circle', $data)) {
            $item->circle = (string) $data['circle'];
        }

        if (Schema::hasColumn('web_gallery_items', 'year') && array_key_exists('year', $data) && ! empty($data['year'])) {
            $item->year = (string) $data['year'];
        }

        if (Schema::hasColumn('web_gallery_items', 'caption') && array_key_exists('caption', $data)) {
            $item->caption = ! empty($data['caption']) ? (string) $data['caption'] : null;
        }

        if (Schema::hasColumn('web_gallery_items', 'gallery_id') && array_key_exists('gallery_id', $data)) {
            $item->gallery_id = ! empty($data['gallery_id']) ? (string) $data['gallery_id'] : null;
        }

        if (Schema::hasColumn('web_gallery_items', 'sort_order') && isset($data['sort_order'])) {
            $item->sort_order = (int) $data['sort_order'];
        }

        if (Schema::hasColumn('web_gallery_items', 'is_active') && isset($data['is_active'])) {
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
