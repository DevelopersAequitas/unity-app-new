<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Web\StoreWebPodcastMediaRequest;
use App\Http\Requests\Admin\Web\UpdateWebPodcastMediaRequest;
use App\Services\Web\WebPodcastMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class WebPodcastMediaController extends Controller
{
    public function __construct(
        protected WebPodcastMediaService $podcastService
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'tag' => $request->query('tag', 'all'),
            'format' => $request->query('format', 'all'),
            'search' => $request->query('search', ''),
        ];

        $missingTable = ! Schema::hasTable('web_media');
        $dbError = null;

        try {
            $episodes = $this->podcastService->listPaginated($filters, 18);
            $allActive = $this->podcastService->allActive();

            $totalEpisodes = $allActive->count();
            $featuredCount = Schema::hasColumn('web_media', 'is_featured')
                ? $allActive->where('is_featured', true)->count()
                : 0;
            $youtubeVideos = Schema::hasColumn('web_media', 'youtube_url')
                ? $allActive->whereNotNull('youtube_url')->count()
                : 0;
            $spotifyLinks = Schema::hasColumn('web_media', 'spotify_url')
                ? $allActive->whereNotNull('spotify_url')->count()
                : 0;
        } catch (\Throwable $e) {
            Log::error('WebPodcastMediaController index error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $episodes = new LengthAwarePaginator([], 0, 18);
            $totalEpisodes = 0;
            $featuredCount = 0;
            $youtubeVideos = 0;
            $spotifyLinks = 0;
            $dbError = $e->getMessage();
        }

        $tags = [
            'MANUFACTURING',
            'LOGISTICS & AGRI',
            'GLOBAL TRADE',
            'COMMUNITY & VISION',
            'TECHNOLOGY & AI',
            'WOMEN ENTREPRENEURSHIP',
            'FINANCE & LIQUIDITY',
            'LEADERSHIP INSIGHTS',
        ];

        $formats = [
            'podcast' => 'The Peers Global Podcast (Audio & Video)',
            'broadcast_tv' => 'Vyapaar Jagat TV (Broadcast Media)',
            'digital_newsroom' => 'VyapaarJagat.com (Digital Newsroom)',
            'circle_magazines' => 'Circle Magazines (Print & Digital)',
            'press_coverage' => 'Press & Coverage (National Press)',
        ];

        return view('admin.web.podcasts.index', [
            'episodes' => $episodes,
            'filters' => $filters,
            'tags' => $tags,
            'formats' => $formats,
            'stats' => [
                'total' => $totalEpisodes,
                'featured' => $featuredCount,
                'youtube' => $youtubeVideos,
                'spotify' => $spotifyLinks,
            ],
            'missingTable' => $missingTable,
            'dbError' => $dbError,
        ]);
    }

    public function store(StoreWebPodcastMediaRequest $request): RedirectResponse
    {
        try {
            $validated = $request->validated();
            $cover = $request->file('cover_file');
            $avatar = $request->file('guest_avatar_file');

            $this->podcastService->create($validated, $cover, $avatar);

            return redirect()->route('admin.web.podcasts.index')
                ->with('success', 'Podcast episode / media feature published successfully! It is now accessible on the website media channels.');
        } catch (\Throwable $e) {
            Log::error('WebPodcastMediaController store error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return redirect()->route('admin.web.podcasts.index')
                ->withInput()
                ->with('error', 'Failed to save podcast episode: '.$e->getMessage());
        }
    }

    public function update(UpdateWebPodcastMediaRequest $request, string $id): RedirectResponse
    {
        try {
            $media = $this->podcastService->findById($id);

            if (! $media) {
                return redirect()->route('admin.web.podcasts.index')
                    ->with('error', 'Podcast episode not found.');
            }

            $validated = $request->validated();
            $cover = $request->file('cover_file');
            $avatar = $request->file('guest_avatar_file');

            $this->podcastService->update($media, $validated, $cover, $avatar);

            return redirect()->route('admin.web.podcasts.index')
                ->with('success', 'Podcast episode updated successfully.');
        } catch (\Throwable $e) {
            Log::error('WebPodcastMediaController update error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return redirect()->route('admin.web.podcasts.index')
                ->withInput()
                ->with('error', 'Failed to update podcast episode: '.$e->getMessage());
        }
    }

    public function destroy(string $id): RedirectResponse
    {
        try {
            $media = $this->podcastService->findById($id);

            if (! $media) {
                return redirect()->route('admin.web.podcasts.index')
                    ->with('error', 'Podcast episode not found.');
            }

            $this->podcastService->delete($media);

            return redirect()->route('admin.web.podcasts.index')
                ->with('success', 'Podcast episode removed successfully.');
        } catch (\Throwable $e) {
            Log::error('WebPodcastMediaController destroy error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return redirect()->route('admin.web.podcasts.index')
                ->with('error', 'Failed to delete podcast episode: '.$e->getMessage());
        }
    }

    public function toggleFeatured(string $id): JsonResponse|RedirectResponse
    {
        try {
            $media = $this->podcastService->findById($id);

            if (! $media) {
                if (request()->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Media not found.'], 404);
                }

                return redirect()->route('admin.web.podcasts.index')->with('error', 'Media not found.');
            }

            $this->podcastService->toggleFeatured($media);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'is_featured' => $media->is_featured,
                    'message' => 'Featured status updated.',
                ]);
            }

            return redirect()->route('admin.web.podcasts.index')
                ->with('success', 'Featured status toggled.');
        } catch (\Throwable $e) {
            Log::error('WebPodcastMediaController toggleFeatured error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            if (request()->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }

            return redirect()->route('admin.web.podcasts.index')->with('error', 'Error: '.$e->getMessage());
        }
    }

    public function toggleStatus(string $id): JsonResponse|RedirectResponse
    {
        try {
            $media = $this->podcastService->findById($id);

            if (! $media) {
                if (request()->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Media not found.'], 404);
                }

                return redirect()->route('admin.web.podcasts.index')->with('error', 'Media not found.');
            }

            $this->podcastService->toggleActive($media);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'is_active' => $media->is_active,
                    'message' => 'Status updated.',
                ]);
            }

            return redirect()->route('admin.web.podcasts.index')
                ->with('success', 'Status toggled successfully.');
        } catch (\Throwable $e) {
            Log::error('WebPodcastMediaController toggleStatus error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            if (request()->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }

            return redirect()->route('admin.web.podcasts.index')->with('error', 'Error: '.$e->getMessage());
        }
    }
}
