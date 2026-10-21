<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Web\BulkDeleteWebGalleryItemsRequest;
use App\Http\Requests\Admin\Web\BulkZipUploadWebGalleryRequest;
use App\Http\Requests\Admin\Web\StoreWebGalleryItemRequest;
use App\Http\Requests\Admin\Web\UpdateWebGalleryItemRequest;
use App\Models\Web\WebGalleryItem;
use App\Services\Web\WebGalleryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class WebGalleryController extends Controller
{
    public function __construct(
        protected WebGalleryService $galleryService
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'search' => $request->query('search', ''),
        ];

        $missingTable = ! Schema::hasTable('web_gallery_items');
        $dbError = null;

        try {
            $items = $this->galleryService->listPaginated($filters, 18);
            $totalCount = $items->total();
            $activeCount = Schema::hasTable('web_gallery_items') && Schema::hasColumn('web_gallery_items', 'is_active')
                ? WebGalleryItem::where('is_active', true)->count()
                : $totalCount;
        } catch (\Throwable $e) {
            Log::error('WebGalleryController index error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $items = new LengthAwarePaginator([], 0, 18);
            $totalCount = 0;
            $activeCount = 0;
            $dbError = $e->getMessage();
        }

        return view('admin.web.gallery.index', [
            'items' => $items,
            'filters' => $filters,
            'stats' => [
                'total' => $totalCount,
                'active' => $activeCount,
            ],
            'missingTable' => $missingTable,
            'dbError' => $dbError,
        ]);
    }

    public function store(StoreWebGalleryItemRequest $request): RedirectResponse
    {
        try {
            $validated = $request->validated();
            $file = $request->file('image');

            $this->galleryService->createItem($validated, $file);

            return redirect()->route('admin.web.gallery.index')
                ->with('success', 'Gallery moment added successfully! It is now live for website visitors.');
        } catch (\Throwable $e) {
            Log::error('WebGalleryController store error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return redirect()->route('admin.web.gallery.index')
                ->withInput()
                ->with('error', 'Failed to save photo: '.$e->getMessage());
        }
    }

    public function bulkZipUpload(BulkZipUploadWebGalleryRequest $request): RedirectResponse
    {
        try {
            $validated = $request->validated();
            $zipFile = $request->file('zip_file');

            $result = $this->galleryService->processZipUpload($zipFile, $validated);

            $message = "Bulk upload successful! Imported {$result['imported_count']} photos into the website gallery.";
            if ($result['skipped_count'] > 0) {
                $message .= " ({$result['skipped_count']} non-image files skipped).";
            }

            return redirect()->route('admin.web.gallery.index')
                ->with('success', $message);
        } catch (\Throwable $e) {
            Log::error('WebGalleryController bulkZipUpload error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return redirect()->route('admin.web.gallery.index')
                ->withInput()
                ->with('error', 'Bulk ZIP upload failed: '.$e->getMessage());
        }
    }

    public function update(UpdateWebGalleryItemRequest $request, string $id): RedirectResponse
    {
        try {
            $item = $this->galleryService->findItemById($id);

            if (! $item) {
                return redirect()->route('admin.web.gallery.index')
                    ->with('error', 'Gallery item not found.');
            }

            $validated = $request->validated();
            $file = $request->file('image');

            $this->galleryService->updateItem($item, $validated, $file);

            return redirect()->route('admin.web.gallery.index')
                ->with('success', 'Gallery moment updated successfully.');
        } catch (\Throwable $e) {
            Log::error('WebGalleryController update error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return redirect()->route('admin.web.gallery.index')
                ->withInput()
                ->with('error', 'Failed to update photo: '.$e->getMessage());
        }
    }

    public function destroy(string $id): RedirectResponse
    {
        try {
            $item = $this->galleryService->findItemById($id);

            if (! $item) {
                return redirect()->route('admin.web.gallery.index')
                    ->with('error', 'Gallery item not found.');
            }

            $this->galleryService->deleteItem($item);

            return redirect()->route('admin.web.gallery.index')
                ->with('success', 'Gallery moment deleted successfully.');
        } catch (\Throwable $e) {
            Log::error('WebGalleryController destroy error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return redirect()->route('admin.web.gallery.index')
                ->with('error', 'Failed to delete photo: '.$e->getMessage());
        }
    }

    public function bulkDestroy(BulkDeleteWebGalleryItemsRequest $request): RedirectResponse
    {
        try {
            $ids = (array) $request->validated('ids', []);
            $deletedCount = $this->galleryService->bulkDeleteItems($ids);

            return redirect()->route('admin.web.gallery.index')
                ->with('success', "Successfully deleted {$deletedCount} gallery photo(s).");
        } catch (\Throwable $e) {
            Log::error('WebGalleryController bulkDestroy error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return redirect()->route('admin.web.gallery.index')
                ->with('error', 'Failed to delete selected photos: '.$e->getMessage());
        }
    }

    public function toggleStatus(string $id): JsonResponse|RedirectResponse
    {
        try {
            $item = $this->galleryService->findItemById($id);

            if (! $item) {
                if (request()->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Item not found.'], 404);
                }

                return redirect()->route('admin.web.gallery.index')->with('error', 'Item not found.');
            }

            $this->galleryService->toggleItemActive($item);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'is_active' => $item->is_active,
                    'message' => 'Status updated.',
                ]);
            }

            return redirect()->route('admin.web.gallery.index')
                ->with('success', 'Status toggled successfully.');
        } catch (\Throwable $e) {
            Log::error('WebGalleryController toggleStatus error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            if (request()->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }

            return redirect()->route('admin.web.gallery.index')->with('error', 'Error: '.$e->getMessage());
        }
    }
}
