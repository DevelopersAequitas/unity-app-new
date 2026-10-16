<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Web\StoreWebGalleryItemRequest;
use App\Http\Requests\Admin\Web\UpdateWebGalleryItemRequest;
use App\Services\Web\WebGalleryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebGalleryController extends Controller
{
    public function __construct(
        protected WebGalleryService $galleryService
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'category' => $request->query('category', 'all'),
            'city' => $request->query('city', 'all'),
            'circle' => $request->query('circle', 'all'),
            'year' => $request->query('year', 'all'),
            'search' => $request->query('search', ''),
        ];

        $items = $this->galleryService->listPaginated($filters, 18);
        $collections = $this->galleryService->listCollections();

        // Calculate metrics
        $allItems = $this->galleryService->allActive();
        $totalMoments = $allItems->count();
        $distinctCities = $allItems->pluck('city')->unique()->count();
        $distinctCategories = $allItems->pluck('category')->unique()->count();

        // Standard filter lists based on the live website
        $categories = [
            'Annual Summit',
            'Circle Meetings',
            'Recognition & Awards',
            'Masterclasses',
            'Regional Conclaves',
            'City Gatherings',
        ];

        $cities = [
            'Ahmedabad',
            'Mumbai',
            'Bengaluru',
            'Surat',
            'Delhi NCR',
            'Pune',
            'Hyderabad',
            'Rajkot',
            'Vadodara',
            'Indore',
            'Jaipur',
            'All Cities',
        ];

        $circles = [
            'All Circles',
            'Logistics Circle',
            'Technology Circle',
            'Manufacturing Circle',
            'Export Circle',
            'Fempreneur Circle',
            'Leadership Board',
            'Healthcare Circle',
            'Real Estate Circle',
            'Textiles & Apparel Circle',
        ];

        $years = ['2026', '2025', '2024', '2023'];

        return view('admin.web.gallery.index', [
            'items' => $items,
            'collections' => $collections,
            'filters' => $filters,
            'categories' => $categories,
            'cities' => $cities,
            'circles' => $circles,
            'years' => $years,
            'stats' => [
                'total' => $totalMoments,
                'cities' => $distinctCities > 0 ? $distinctCities : 12,
                'categories' => $distinctCategories > 0 ? $distinctCategories : 6,
                'active' => $allItems->count(),
            ],
        ]);
    }

    public function store(StoreWebGalleryItemRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $file = $request->file('image');

        $this->galleryService->createItem($validated, $file);

        return redirect()->route('admin.web.gallery.index')
            ->with('success', 'Gallery moment added successfully! It is now live for website visitors.');
    }

    public function update(UpdateWebGalleryItemRequest $request, string $id): RedirectResponse
    {
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
    }

    public function destroy(string $id): RedirectResponse
    {
        $item = $this->galleryService->findItemById($id);

        if (! $item) {
            return redirect()->route('admin.web.gallery.index')
                ->with('error', 'Gallery item not found.');
        }

        $this->galleryService->deleteItem($item);

        return redirect()->route('admin.web.gallery.index')
            ->with('success', 'Gallery moment deleted successfully.');
    }

    public function toggleStatus(string $id): JsonResponse|RedirectResponse
    {
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
    }
}
