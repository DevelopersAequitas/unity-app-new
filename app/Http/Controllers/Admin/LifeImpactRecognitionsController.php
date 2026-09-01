<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\Post;
use App\Models\User;
use App\Services\Admin\IndustryScopeService;
use App\Services\Creative\LifeImpactCreativeGenerator;
use App\Support\AdminAccess;
use App\Support\AdminCircleScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LifeImpactRecognitionsController extends Controller
{
    /**
     * Display the listing of Life Impact Recognitions and Creative Studio.
     */
    public function index(Request $request): View
    {
        $adminUser = Auth::guard('admin')->user();
        if (! $adminUser) {
            abort(401);
        }

        $canEditUsers = AdminAccess::canEditUsers($adminUser);
        $generator = app(LifeImpactCreativeGenerator::class);
        $recognitionLevels = $generator->getAllRecognitionLevels();

        // Filters
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $search = trim((string) $request->input('q', ''));
        $circleId = $request->input('circle_id', 'all');
        $membershipStatus = $request->input('membership_status');
        $perPage = $request->integer('per_page') ?: 20;
        $perPage = in_array($perPage, [10, 20, 25, 50, 100], true) ? $perPage : 20;
        $sort = $request->input('sort', 'total_life_impacted');
        $direction = $request->input('dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $hasDateFilter = ! empty($startDate) || ! empty($endDate);

        // Section A: Top 10 Query (ordered by total life impacted desc, then display_name asc)
        $topPeersQuery = User::query()
            ->select('users.*')
            ->with(['city', 'circleMembers.circle'])
            ->where(function (Builder $q): void {
                $q->where('life_impacted_count', '>', 0)
                    ->orWhereExists(function ($sub): void {
                        $sub->select(DB::raw(1))
                            ->from('life_impact_histories')
                            ->whereColumn('life_impact_histories.user_id', 'users.id');
                    });
            });

        $this->applyScopes($topPeersQuery, $adminUser);

        if ($hasDateFilter) {
            $topPeersQuery->addSelect([
                'total_life_impacted_calc' => DB::table('life_impact_histories')
                    ->selectRaw('COALESCE(SUM(COALESCE(impact_value, life_impacted, 0)), 0)')
                    ->whereColumn('life_impact_histories.user_id', 'users.id')
                    ->when($startDate, fn ($q) => $q->whereDate('created_at', '>=', $startDate))
                    ->when($endDate, fn ($q) => $q->whereDate('created_at', '<=', $endDate)),
            ])
                ->orderByDesc('total_life_impacted_calc')
                ->orderBy('display_name', 'asc')
                ->limit(10);
        } else {
            $topPeersQuery->orderByDesc('life_impacted_count')
                ->orderBy('display_name', 'asc')
                ->limit(10);
        }

        $topPeers = $topPeersQuery->get();

        // Section B: All Life Impact Peers Query
        $query = User::query()
            ->select('users.*')
            ->with(['city', 'circleMembers.circle']);

        $this->applyScopes($query, $adminUser);

        if ($hasDateFilter) {
            $query->addSelect([
                'total_life_impacted_calc' => DB::table('life_impact_histories')
                    ->selectRaw('COALESCE(SUM(COALESCE(impact_value, life_impacted, 0)), 0)')
                    ->whereColumn('life_impact_histories.user_id', 'users.id')
                    ->when($startDate, fn ($q) => $q->whereDate('created_at', '>=', $startDate))
                    ->when($endDate, fn ($q) => $q->whereDate('created_at', '<=', $endDate)),
            ]);
        } else {
            $query->addSelect(DB::raw('COALESCE(users.life_impacted_count, 0) as total_life_impacted_calc'));
        }

        // Apply Search
        if ($search !== '') {
            if (Str::isUuid($search)) {
                $query->where('users.id', $search);
            } else {
                $words = array_filter(explode(' ', $search));
                $query->where(function (Builder $q) use ($words): void {
                    foreach ($words as $word) {
                        $like = "%{$word}%";
                        $q->where(function (Builder $sub) use ($like): void {
                            $sub->where('name', 'ILIKE', $like)
                                ->orWhere('display_name', 'ILIKE', $like)
                                ->orWhere('first_name', 'ILIKE', $like)
                                ->orWhere('last_name', 'ILIKE', $like)
                                ->orWhere('email', 'ILIKE', $like)
                                ->orWhere('company', 'ILIKE', $like)
                                ->orWhere('company_name', 'ILIKE', $like)
                                ->orWhere('business_name', 'ILIKE', $like)
                                ->orWhere('city', 'ILIKE', $like)
                                ->orWhere('phone', 'ILIKE', $like)
                                ->orWhere('designation', 'ILIKE', $like)
                                ->orWhereRaw("TRIM(CONCAT_WS(' ', COALESCE(first_name, ''), COALESCE(last_name, ''))) ILIKE ?", [$like])
                                ->orWhereHas('city', fn (Builder $cq) => $cq->where('name', 'ILIKE', $like));
                        });
                    }
                });
            }
        }

        // Apply Circle Filter
        if ($circleId !== '' && $circleId !== 'all' && Str::isUuid($circleId)) {
            $query->whereHas('circleMembers', function (Builder $cq) use ($circleId): void {
                $cq->where('circle_id', $circleId)
                    ->where('status', 'approved')
                    ->whereNull('deleted_at');
            });
        }

        // Apply Membership Status Filter
        if ($membershipStatus) {
            $dbValue = match ($membershipStatus) {
                'only_unity_peer' => 'Only Unity Peer',
                'circle_peer' => 'Circle Peer',
                'multi_circle_peer' => 'Multi Circle Peer',
                'free_peer' => 'free_peer',
                'free_trial_peer' => 'free_trial_peer',
                default => $membershipStatus,
            };
            $query->where('membership_status', $dbValue);
        }

        // Sorting
        if ($sort === 'display_name') {
            $query->orderBy('display_name', $direction);
        } elseif ($sort === 'total_life_impacted') {
            $query->orderBy('total_life_impacted_calc', $direction)
                ->orderBy('display_name', 'asc');
        } else {
            $query->orderBy('total_life_impacted_calc', 'desc')
                ->orderBy('display_name', 'asc');
        }

        $peers = $query->paginate($perPage)->withQueryString();

        $membershipStatuses = ['circle_peer', 'multi_circle_peer', 'only_unity_peer', 'free_peer', 'free_trial_peer'];
        $membershipStatusLabels = $this->membershipFilterOptions();

        $filters = [
            'search' => $search,
            'membership_status' => $membershipStatus,
            'circle_id' => $circleId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'per_page' => $perPage,
            'sort' => $sort,
            'dir' => $direction,
        ];

        $allPeersQuery = User::query()
            ->with(['city'])
            ->orderByDesc('life_impacted_count')
            ->orderBy('display_name', 'asc');
        $this->applyScopes($allPeersQuery, $adminUser);
        $allPeers = $allPeersQuery->get();

        $activeTab = $request->input('tab', 'list') === 'creative' ? 'creative' : 'list';
        $selectedPeerId = $request->input('peer_id');
        if (! $selectedPeerId && $activeTab === 'creative' && $allPeers->isNotEmpty()) {
            $selectedPeerId = $allPeers->first()->id;
        }

        $circles = $this->circleOptions($adminUser);

        return view('admin.life-impact-recognitions.index', [
            'topPeers' => $topPeers,
            'peers' => $peers,
            'allPeers' => $allPeers,
            'canEditUsers' => $canEditUsers,
            'membershipStatuses' => $membershipStatuses,
            'membershipStatusLabels' => $membershipStatusLabels,
            'filters' => $filters,
            'recognitionLevels' => $recognitionLevels,
            'activeTab' => $activeTab,
            'selectedPeerId' => $selectedPeerId,
            'circles' => $circles,
        ]);
    }

    /**
     * Preview Life Impact Recognition creative image response.
     */
    public function creativePreview(Request $request, string $id, LifeImpactCreativeGenerator $generator)
    {
        $peer = User::with(['city', 'circleMembers.circle'])->findOrFail($id);

        $realCount = (int) ($peer->life_impacted_count ?? 0);
        if ($realCount <= 0) {
            $realCount = (int) DB::table('life_impact_histories')
                ->where('user_id', $peer->id)
                ->sum(DB::raw('COALESCE(impact_value, life_impacted, 0)'));
        }

        $selectedThreshold = $request->has('threshold') && (int) $request->input('threshold') > 0
            ? (int) $request->input('threshold')
            : 0;

        $effectiveThreshold = $selectedThreshold > 0
            ? $selectedThreshold
            : ($realCount >= 25 ? $realCount : 25);

        $meta = $generator->getRecognitionMeta($effectiveThreshold);
        $caption = $generator->formatCaption($peer, $effectiveThreshold, $meta);

        $peerName = $peer->display_name ?: trim(($peer->first_name ?? '').' '.($peer->last_name ?? ''));
        if (empty($peerName)) {
            $peerName = $peer->name ?? 'Peer Member';
        }

        $cityModel = $peer->getRelation('city') ?? $peer->cityRelation ?? null;
        $cityName = $cityModel->name ?? $peer->city ?? '';
        if (is_array($cityName)) {
            $cityName = $cityName['name'] ?? $cityName['label'] ?? '';
        }

        $company = $peer->company_name ?? $peer->company ?? $peer->business_name ?? '';
        if (in_array(strtolower(trim((string) $company)), ['', 'none', 'null', 'no company'], true)) {
            $company = '';
        }

        $peerDetails = [
            'id' => $peer->id,
            'name' => $peerName,
            'company' => $company,
            'city' => $cityName,
            'designation' => $peer->designation ?? 'Peers Global Member',
            'membership_status' => $peer->membership_status ?? 'Peer',
            'life_impacted_count' => $realCount,
        ];

        $timelinePosts = Post::query()
            ->where('source_type', 'life_impact')
            ->where('source_id', $peer->id)
            ->where('post_type', 'life_impact_recognition')
            ->latest('created_at')
            ->get();

        $allLevels = $generator->getAllRecognitionLevels();
        $progressionList = [];

        foreach ($allLevels as $threshold => $hMeta) {
            $isUnlocked = $realCount >= $threshold;
            $isCurrent = ($meta['title'] === $hMeta['title']);

            $matchingPost = $timelinePosts->first(function ($p) use ($hMeta): bool {
                return str_contains(strtolower((string) $p->title), strtolower($hMeta['title']))
                    || str_contains(strtolower((string) $p->content_text), strtolower($hMeta['title']));
            });

            $progressionList[] = [
                'threshold' => $threshold,
                'title' => $hMeta['title'],
                'compliment' => $hMeta['compliment'],
                'quote' => $hMeta['quote'],
                'is_unlocked' => $isUnlocked,
                'is_current' => $isCurrent,
                'badge_image' => asset($hMeta['badge_image']),
                'posted_to_timeline' => ! empty($matchingPost),
                'post_id' => $matchingPost?->id,
                'post_view_url' => $matchingPost ? route('admin.posts.show', $matchingPost->id) : null,
                'posted_at' => $matchingPost?->created_at ? $matchingPost->created_at->format('d M Y, h:i A') : null,
            ];
        }

        $currentMatchingPost = $timelinePosts->first(function ($p) use ($meta): bool {
            return str_contains(strtolower((string) $p->title), strtolower($meta['title']))
                || str_contains(strtolower((string) $p->content_text), strtolower($meta['title']));
        });

        $timelineStatus = [
            'is_posted' => ! empty($currentMatchingPost),
            'post_id' => $currentMatchingPost?->id,
            'post_view_url' => $currentMatchingPost ? route('admin.posts.show', $currentMatchingPost->id) : null,
            'posted_at' => $currentMatchingPost?->created_at ? $currentMatchingPost->created_at->format('d M Y, h:i A') : null,
            'total_timeline_posts' => $timelinePosts->count(),
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'meta' => $meta,
                'caption' => $caption,
                'preview_url' => route('admin.life-impact-recognitions.creative-preview', ['id' => $peer->id, 'image' => 1, 'threshold' => $effectiveThreshold]),
                'peer' => $peerDetails,
                'peer_progression' => $progressionList,
                'timeline_status' => $timelineStatus,
            ]);
        }

        // Render actual image binary when image=1
        if ($request->boolean('image')) {
            $fileModel = $generator->generate($peer, $realCount, $effectiveThreshold);
            $disk = 'public';
            if ($fileModel->s3_key && Storage::disk($disk)->exists($fileModel->s3_key)) {
                $path = Storage::disk($disk)->path($fileModel->s3_key);

                return response()->file($path, [
                    'Content-Type' => 'image/webp',
                    'Cache-Control' => 'no-cache, must-revalidate',
                ])->deleteFileAfterSend(true);
            }
        }

        return response()->json([
            'success' => true,
            'meta' => $meta,
            'caption' => $caption,
            'preview_url' => route('admin.life-impact-recognitions.creative-preview', ['id' => $peer->id, 'image' => 1, 'threshold' => $effectiveThreshold]),
            'peer' => $peerDetails,
            'peer_progression' => $progressionList,
            'timeline_status' => $timelineStatus,
        ]);
    }

    /**
     * Post Life Impact Recognition Creative to Timeline.
     */
    public function postCreativeToTimeline(Request $request, string $id, LifeImpactCreativeGenerator $generator): JsonResponse
    {
        $adminUser = Auth::guard('admin')->user();
        if (! $adminUser) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $peer = User::findOrFail($id);

        $realCount = (int) ($peer->life_impacted_count ?? 0);
        if ($realCount <= 0) {
            $realCount = (int) DB::table('life_impact_histories')
                ->where('user_id', $peer->id)
                ->sum(DB::raw('COALESCE(impact_value, life_impacted, 0)'));
        }

        $selectedThreshold = $request->has('threshold') && (int) $request->input('threshold') > 0
            ? (int) $request->input('threshold')
            : 0;

        $effectiveThreshold = $selectedThreshold > 0
            ? $selectedThreshold
            : ($realCount >= 25 ? $realCount : 25);

        try {
            $fileRecord = $generator->generate($peer, $realCount, $effectiveThreshold);
            $imageUrl = url('/api/v1/files/'.$fileRecord->id);

            $meta = $generator->getRecognitionMeta($effectiveThreshold);
            $caption = $generator->formatCaption($peer, $effectiveThreshold, $meta);

            // Find system user to post automated announcement
            $systemUser = User::where('email', 'info@peersglobal.com')->first();
            if (! $systemUser) {
                $userData = [
                    'id' => (string) Str::uuid(),
                    'first_name' => 'PeersGlobal',
                    'last_name' => 'Unity',
                    'display_name' => 'PeersGlobal Unity',
                    'email' => 'info@peersglobal.com',
                    'status' => 'active',
                ];
                if (Schema::hasColumn('users', 'password_hash')) {
                    $userData['password_hash'] = bcrypt(Str::random(16));
                } elseif (Schema::hasColumn('users', 'password')) {
                    $userData['password'] = bcrypt(Str::random(16));
                }
                $systemUser = User::create($userData);
            }
            $authorUserId = $systemUser ? $systemUser->id : $peer->id;

            $post = Post::create([
                'user_id' => $authorUserId,
                'circle_id' => null,
                'content_text' => $caption,
                'media' => [
                    [
                        'id' => $fileRecord->id,
                        'type' => 'image',
                        'url' => $imageUrl,
                    ],
                ],
                'tags' => ['life_impact', 'recognition', '1million_mission', $meta['hashtag'], (string) $peer->id],
                'visibility' => 'public',
                'moderation_status' => 'approved',
                'sponsored' => false,
                'is_deleted' => false,
                'source_type' => 'life_impact',
                'source_id' => $peer->id,
                'source_event' => 'recognition',
                'post_type' => 'life_impact_recognition',
                'title' => "BIG CONGRATULATIONS: {$meta['title']} — ".($peer->display_name ?: $peer->name),
                'description' => $caption,
                'image' => $imageUrl,
                'status' => 'active',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Life Impact recognition posted to Timeline successfully! 🎉',
                'post_id' => $post->id,
                'view_url' => route('admin.posts.show', $post->id),
                'timeline_url' => route('admin.posts.index'),
                'image_url' => $imageUrl,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed posting life impact recognition to timeline: '.$e->getMessage());

            return response()->json([
                'error' => 'Failed to post creative to timeline: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Apply active scoping to restrict query by role.
     */
    private function applyScopes(Builder $query, $adminUser): void
    {
        AdminCircleScope::applyToUsersQuery($query, $adminUser);

        $industryScope = app(IndustryScopeService::class);
        if ($industryScope->isIndustryDirector($adminUser)) {
            $industryScope->applyPeersScope($query, $adminUser->id);
        }
    }

    /**
     * Get membership options for filter.
     */
    private function membershipFilterOptions(): array
    {
        return [
            'circle_peer' => 'Circle Peer',
            'multi_circle_peer' => 'Multi Circle Peer',
            'only_unity_peer' => 'Global Peer',
            'free_peer' => 'Free Peer',
            'free_trial_peer' => 'Free Trial Peer',
        ];
    }

    /**
     * Get Circle options for filter.
     */
    private function circleOptions($adminUser)
    {
        $query = Circle::query()->orderBy('name');

        if (AdminAccess::isDed($adminUser)) {
            AdminCircleScope::applyToCirclesQuery($query, $adminUser);
        } elseif (app(IndustryScopeService::class)->isIndustryDirector($adminUser)) {
            $circleIds = app(IndustryScopeService::class)->circleIdsForAdmin($adminUser);
            $query->when($circleIds !== [], fn ($q) => $q->whereIn('id', $circleIds), fn ($q) => $q->whereRaw('1 = 0'));
        }

        return $query->get(['id', 'name']);
    }
}
