<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Web\StoreWebSuccessStoryRequest;
use App\Http\Requests\Admin\Web\UpdateWebSuccessStoryRequest;
use App\Services\Web\WebSuccessStoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebSuccessStoryController extends Controller
{
    public function __construct(
        protected WebSuccessStoryService $storyService
    ) {}

    /**
     * Display listing of homepage success stories with KPI metrics.
     */
    public function index(Request $request): View
    {
        $filters = [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
        ];

        $stories = $this->storyService->getAll($filters);

        $totalCount = $stories->count();
        $activeCount = $stories->where('is_active', true)->count();
        $customCoverCount = $stories->whereNotNull('custom_cover_image')->count();
        $youtubeThumbCount = $totalCount - $customCoverCount;

        return view('admin.web.success-stories.index', [
            'stories' => $stories,
            'filters' => $filters,
            'stats' => [
                'total' => $totalCount,
                'active' => $activeCount,
                'customCovers' => $customCoverCount,
                'youtubeThumbs' => $youtubeThumbCount,
            ],
        ]);
    }

    /**
     * Store a newly created success story.
     */
    public function store(StoreWebSuccessStoryRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $coverFile = $request->file('custom_cover_image');

        $this->storyService->create($validated, $coverFile);

        return redirect()->route('admin.web.success-stories.index')
            ->with('success', 'Success story added successfully! YouTube video link and cover have been configured.');
    }

    /**
     * Update an existing success story.
     */
    public function update(UpdateWebSuccessStoryRequest $request, string $id): RedirectResponse
    {
        $story = $this->storyService->findById($id);

        if (! $story) {
            return redirect()->route('admin.web.success-stories.index')
                ->with('error', 'Success story not found.');
        }

        $validated = $request->validated();
        $coverFile = $request->file('custom_cover_image');
        $removeCover = $request->boolean('remove_custom_cover');

        $this->storyService->update($story, $validated, $coverFile, $removeCover);

        return redirect()->route('admin.web.success-stories.index')
            ->with('success', 'Success story updated successfully.');
    }

    /**
     * Delete a success story.
     */
    public function destroy(string $id): RedirectResponse
    {
        $story = $this->storyService->findById($id);

        if (! $story) {
            return redirect()->route('admin.web.success-stories.index')
                ->with('error', 'Success story not found.');
        }

        $this->storyService->delete($story);

        return redirect()->route('admin.web.success-stories.index')
            ->with('success', 'Success story deleted successfully.');
    }

    /**
     * Toggle active status.
     */
    public function toggleStatus(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $story = $this->storyService->findById($id);

        if (! $story) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Not found'], 404);
            }

            return redirect()->route('admin.web.success-stories.index')
                ->with('error', 'Success story not found.');
        }

        $newStatus = $this->storyService->toggleActive($story);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_active' => $newStatus,
                'message' => $newStatus ? 'Story is now active on homepage.' : 'Story deactivated.',
            ]);
        }

        return redirect()->route('admin.web.success-stories.index')
            ->with('success', $newStatus ? 'Story published to homepage.' : 'Story hidden from homepage.');
    }
}
