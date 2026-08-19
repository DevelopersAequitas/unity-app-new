<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\User;
use App\Services\Creative\IntroducedPeerCreativeGenerator;
use App\Services\IndustryDirector\IndustryScopeService;
use App\Support\AdminAccess;
use App\Support\AdminCircleScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MemberIntroducersController extends Controller
{
    /**
     * Display the listing of member introducers.
     */
    public function index(Request $request): View
    {
        $adminUser = Auth::guard('admin')->user();
        if (! $adminUser) {
            abort(401);
        }

        $canEditUsers = AdminAccess::canEditUsers($adminUser);

        // Section A: Top 10 Query (ordered by count desc, then alphabetically by name asc)
        $topIntroducersQuery = User::query()
            ->withCount(['introducedMembers'])
            ->with(['city', 'introducedBy'])
            ->has('introducedMembers')
            ->orderByDesc('introduced_members_count')
            ->orderBy('display_name', 'asc')
            ->limit(10);

        $this->applyScopes($topIntroducersQuery, $adminUser);
        $topIntroducers = $topIntroducersQuery->get();

        // Section B: All Introducers Query
        $query = User::query()
            ->with(['city', 'introducedBy']);

        $this->applyScopes($query, $adminUser);

        // Filters
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $search = trim((string) $request->input('q', ''));
        $membershipStatus = $request->input('membership_status');
        $perPage = $request->integer('per_page') ?: 20;
        $perPage = in_array($perPage, [10, 20, 25, 50, 100], true) ? $perPage : 20;

        // Apply Date Range Filter on introduced date (created_at of the introduced member)
        if ($startDate || $endDate) {
            $query->whereHas('introducedMembers', function (Builder $q) use ($startDate, $endDate) {
                if ($startDate) {
                    $q->whereDate('created_at', '>=', $startDate);
                }
                if ($endDate) {
                    $q->whereDate('created_at', '<=', $endDate);
                }
            });

            $query->withCount(['introducedMembers' => function (Builder $q) use ($startDate, $endDate) {
                if ($startDate) {
                    $q->whereDate('created_at', '>=', $startDate);
                }
                if ($endDate) {
                    $q->whereDate('created_at', '<=', $endDate);
                }
            }]);
        } else {
            $query->whereHas('introducedMembers');
            $query->withCount(['introducedMembers']);
        }

        // Apply Search
        if ($search !== '') {
            if (Str::isUuid($search)) {
                $query->where('users.id', $search);
            } else {
                $words = array_filter(explode(' ', $search));
                $query->where(function (Builder $q) use ($words) {
                    foreach ($words as $word) {
                        $like = "%{$word}%";
                        $q->where(function (Builder $sub) use ($like) {
                            $searchableColumns = [
                                'name',
                                'display_name',
                                'first_name',
                                'last_name',
                                'email',
                                'company',
                                'company_name',
                                'business_name',
                                'city',
                                'phone',
                                'designation',
                            ];

                            $hasSearchColumn = false;
                            foreach ($searchableColumns as $column) {
                                if (! Schema::hasColumn('users', $column)) {
                                    continue;
                                }
                                if (! $hasSearchColumn) {
                                    $sub->where($column, 'ILIKE', $like);
                                    $hasSearchColumn = true;

                                    continue;
                                }
                                $sub->orWhere($column, 'ILIKE', $like);
                            }

                            $sub->orWhereRaw("TRIM(CONCAT_WS(' ', COALESCE(first_name, ''), COALESCE(last_name, ''))) ILIKE ?", [$like]);

                            $sub->orWhereHas('city', function (Builder $cityQuery) use ($like) {
                                $cityQuery->where('name', 'ILIKE', $like);
                            });
                        });
                    }
                });
            }
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

        // Sorting (default to count desc, then alphabetically by name asc)
        $sort = $request->input('sort', 'introduced_members_count');
        $direction = $request->input('dir', 'desc') === 'asc' ? 'asc' : 'desc';

        if ($sort === 'display_name') {
            $query->orderBy('display_name', $direction);
        } else {
            $query->orderBy('introduced_members_count', $direction)
                ->orderBy('display_name', 'asc');
        }

        $introducers = $query->paginate($perPage)->withQueryString();

        $membershipStatuses = ['circle_peer', 'multi_circle_peer', 'only_unity_peer', 'free_peer', 'free_trial_peer'];
        $membershipStatusLabels = $this->membershipFilterOptions();

        $filters = [
            'search' => $search,
            'membership_status' => $membershipStatus,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'per_page' => $perPage,
            'sort' => $sort,
            'dir' => $direction,
        ];

        $creativeGenerator = app(IntroducedPeerCreativeGenerator::class);
        $growthHonours = $creativeGenerator->getAllHonours();

        $allIntroducersQuery = User::query()
            ->has('introducedMembers')
            ->withCount('introducedMembers')
            ->with('city')
            ->orderBy('display_name', 'asc');
        $this->applyScopes($allIntroducersQuery, $adminUser);
        $allIntroducers = $allIntroducersQuery->get();

        $activeTab = $request->input('tab', 'list') === 'creative' ? 'creative' : 'list';
        $selectedPeerId = $request->input('peer_id');

        return view('admin.member-introducers.index', [
            'topIntroducers' => $topIntroducers,
            'introducers' => $introducers,
            'allIntroducers' => $allIntroducers,
            'canEditUsers' => $canEditUsers,
            'membershipStatuses' => $membershipStatuses,
            'membershipStatusLabels' => $membershipStatusLabels,
            'filters' => $filters,
            'growthHonours' => $growthHonours,
            'activeTab' => $activeTab,
            'selectedPeerId' => $selectedPeerId,
        ]);
    }

    /**
     * Get introduced peers list for a specific introducer (JSON modal endpoint).
     */
    public function introducedPeers(Request $request, string $id): JsonResponse
    {
        $adminUser = Auth::guard('admin')->user();
        if (! $adminUser) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $introducer = User::with(['city', 'introducedBy'])->find($id);
        if (! $introducer) {
            return response()->json(['error' => 'Introducer not found'], 404);
        }

        $query = User::query()
            ->where('introduced_by', $id)
            ->with(['city']);

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $words = array_filter(explode(' ', $search));
            $query->where(function (Builder $q) use ($words) {
                foreach ($words as $word) {
                    $like = "%{$word}%";
                    $q->where(function (Builder $sub) use ($like) {
                        $sub->where('name', 'ILIKE', $like)
                            ->orWhere('display_name', 'ILIKE', $like)
                            ->orWhere('first_name', 'ILIKE', $like)
                            ->orWhere('last_name', 'ILIKE', $like)
                            ->orWhere('email', 'ILIKE', $like)
                            ->orWhere('company', 'ILIKE', $like)
                            ->orWhere('company_name', 'ILIKE', $like)
                            ->orWhere('phone', 'ILIKE', $like)
                            ->orWhere('designation', 'ILIKE', $like);
                    });
                }
            });
        }

        $introducedPeers = $query->latest('created_at')->get();

        $peersData = $introducedPeers->map(function ($peer) {
            $peerName = $peer->display_name ?: trim(($peer->first_name ?? '').' '.($peer->last_name ?? ''));
            if (empty($peerName)) {
                $peerName = $peer->name ?? 'Peer Member';
            }

            $avatar = $peer->profile_photo_url ?? ($peer->profile_photo_file_id ? url('/api/v1/files/'.$peer->profile_photo_file_id) : null);

            $cityModel = $peer->getRelation('city') ?? $peer->cityRelation ?? null;
            $cityName = $cityModel->name ?? $peer->city ?? '';
            if (is_array($cityName)) {
                $cityName = $cityName['name'] ?? $cityName['label'] ?? '';
            }

            $company = $peer->company_name ?? $peer->company ?? $peer->business_name ?? '';
            if (in_array(strtolower(trim((string) $company)), ['', 'none', 'null', 'no company'], true)) {
                $company = null;
            }

            return [
                'id' => $peer->id,
                'name' => $peerName,
                'avatar' => $avatar,
                'email' => $peer->email,
                'phone' => $peer->phone,
                'company' => $company,
                'designation' => $peer->designation,
                'city' => $cityName,
                'membership_status' => $peer->membership_status ?? 'Peer',
                'joined_at' => $peer->created_at ? $peer->created_at->format('d M Y') : '-',
            ];
        });

        $introducerName = $introducer->display_name ?: trim(($introducer->first_name ?? '').' '.($introducer->last_name ?? ''));

        return response()->json([
            'success' => true,
            'introducer' => [
                'id' => $introducer->id,
                'name' => $introducerName,
                'count' => $introducedPeers->count(),
            ],
            'introduced_peers' => $peersData,
        ]);
    }

    /**
     * Preview Growth Honour creative image response.
     */
    public function creativePreview(Request $request, string $id, IntroducedPeerCreativeGenerator $generator)
    {
        $introducer = User::with('city')->findOrFail($id);

        $count = $request->has('count') && (int) $request->input('count') > 0 ? (int) $request->input('count') : (int) ($introducer->members_introduced_count ?? 0);
        if ($count === 0) {
            $count = User::query()->where('introduced_by', $introducer->id)->count();
        }
        if ($count === 0) {
            $count = 1;
        }

        $meta = $generator->getHonourMeta($count);
        $caption = $generator->formatCaption($introducer, $count);

        $peerName = $introducer->display_name ?: trim(($introducer->first_name ?? '').' '.($introducer->last_name ?? ''));
        if (empty($peerName)) {
            $peerName = $introducer->name ?? 'Peer Member';
        }

        $cityModel = $introducer->getRelation('city') ?? $introducer->cityRelation ?? null;
        $cityName = $cityModel->name ?? $introducer->city ?? '';
        if (is_array($cityName)) {
            $cityName = $cityName['name'] ?? $cityName['label'] ?? '';
        }

        $company = $introducer->company_name ?? $introducer->company ?? $introducer->business_name ?? '';
        if (in_array(strtolower(trim((string) $company)), ['', 'none', 'null', 'no company'], true)) {
            $company = '';
        }

        $peerDetails = [
            'id' => $introducer->id,
            'name' => $peerName,
            'company' => $company,
            'city' => $cityName,
            'designation' => $introducer->designation ?? 'Peers Global Member',
            'membership_status' => $introducer->membership_status ?? 'Peer',
            'introduced_count' => $count,
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'meta' => $meta,
                'caption' => $caption,
                'preview_url' => route('admin.member-introducers.creative-preview', ['id' => $introducer->id, 'image' => 1, 'count' => $count]),
                'peer' => $peerDetails,
            ]);
        }

        // Render actual image binary when image=1
        if ($request->boolean('image')) {
            $fileModel = $generator->generate($introducer, $count);
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
            'preview_url' => route('admin.member-introducers.creative-preview', ['id' => $introducer->id, 'image' => 1, 'count' => $count]),
            'peer' => $peerDetails,
        ]);
    }

    /**
     * Post Creative to Timeline.
     */
    public function postCreativeToTimeline(Request $request, string $id, IntroducedPeerCreativeGenerator $generator): JsonResponse
    {
        $adminUser = Auth::guard('admin')->user();
        if (! $adminUser) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $introducer = User::findOrFail($id);

        $count = $request->has('count') && (int) $request->input('count') > 0 ? (int) $request->input('count') : (int) ($introducer->members_introduced_count ?? 0);
        if ($count === 0) {
            $count = User::query()->where('introduced_by', $introducer->id)->count();
        }
        if ($count === 0) {
            $count = 1;
        }

        try {
            // Generate creative file
            $fileRecord = $generator->generate($introducer, $count);
            $imageUrl = url('/api/v1/files/'.$fileRecord->id);

            $meta = $generator->getHonourMeta($count);
            $caption = $generator->formatCaption($introducer, $count);

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
            $authorUserId = $systemUser ? $systemUser->id : $introducer->id;

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
                'tags' => ['introduction', 'growth_honour', 'member_introducer', (string) $introducer->id],
                'visibility' => 'public',
                'moderation_status' => 'approved',
                'sponsored' => false,
                'is_deleted' => false,
                'source_type' => 'member_introduction',
                'source_id' => $introducer->id,
                'source_event' => 'growth_honour',
                'post_type' => 'growth_honour',
                'title' => "BIG CONGRATULATIONS: {$meta['title']} — ".($introducer->display_name ?: $introducer->name),
                'description' => $caption,
                'image' => $imageUrl,
                'status' => 'active',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Creative posted to Timeline successfully! 🎉',
                'post_id' => $post->id,
                'view_url' => route('admin.posts.show', $post->id),
                'timeline_url' => route('admin.posts.index'),
                'image_url' => $imageUrl,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed posting creative to timeline: '.$e->getMessage());

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
}
