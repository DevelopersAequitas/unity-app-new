<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\UserCircleRequest\SubmitUserCircleRequest;
use App\Http\Requests\Api\UserCircleRequest\UpdateUserCircleRequestNominees;
use App\Models\CircleCategory;
use App\Models\CircleMember;
use App\Models\User;
use App\Models\UserCircleRequest;
use App\Models\UserCircleRequestNominee;
use App\Services\Circles\UserCircleNomineeStatusResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UserCircleRequestController extends BaseApiController
{
    public function __construct(
        private readonly UserCircleNomineeStatusResolver $nomineeResolver,
    ) {}

    public static function getMinimumRequired(): int
    {
        return (int) Cache::get('circle.build_your_own.minimum_nominees', config('circle.build_your_own.minimum_nominees', 40));
    }

    /**
     * Get dynamic config, Level 1 categories, options, and declaration texts.
     */
    public function config(Request $request): JsonResponse
    {
        $minimum = self::getMinimumRequired();

        $categories = CircleCategory::query()
            ->where('level', 1)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'circle_key']);

        return $this->success([
            'minimum_recommended_peers_required' => $minimum,
            'minimum_nominees_required'          => $minimum, // alias
            'recommended_peers_required'         => $minimum,
            'recommended_nominees'               => $minimum, // alias
            'message'                            => "You need to recommend at least {$minimum} complete peers (name, mobile, business name & category) to build your circle.",
            'circle_types'                       => ['industry', 'interest'],
            'meeting_modes'                      => ['in_person', 'online', 'hybrid'],
            'target_first_meeting_day_options'   => [30, 60, 90],
            'interest_levels'                    => ['high', 'medium', 'low'],
            'declarations'                       => [
                'rulebook_accepted'      => 'I have read and accepted the Peers Global Circle Rulebook.',
                'one_member_per_category'=> 'I understand that only one member per specific category is permitted in this Circle.',
                'contact_permission'     => 'I confirm that I have explicit permission from these individuals to share their contact details.',
                'information_truthful'   => 'I declare that all information provided in this proposal is accurate and true.',
            ],
            'categories'                         => $categories,
        ], 'Circle build configuration fetched.');
    }

    /**
     * Get Level 4 subcategories for a given Level 1 category.
     * Optionally marks subcategories as locked if already assigned in an existing request or client state.
     */
    public function subcategories(Request $request, string $categoryId): JsonResponse
    {
        $category = CircleCategory::query()
            ->where('level', 1)
            ->where(function ($q) use ($categoryId): void {
                if (ctype_digit($categoryId)) {
                    $q->where('id', (int) $categoryId);
                } else {
                    $q->where('slug', $categoryId);
                }
            })
            ->first();

        if (! $category) {
            return $this->error('Circle category not found.', 404);
        }

        $requestId = $request->query('request_id') ?? $request->query('circle_request_id');
        $takenIdsInput = $request->query('taken_ids') ?? $request->query('taken_subcategory_ids');

        $lockedMap = [];

        // 1. If an existing request ID is provided, inspect taken subcategories in database
        if (! empty($requestId)) {
            $circleReq = UserCircleRequest::with('nominees')->find($requestId);
            if ($circleReq) {
                // Check founder subcategory
                $founderSubId = $circleReq->founder_subcategory_id ?? ($circleReq->metadata['founder_subcategory_id'] ?? null);
                if (! empty($founderSubId)) {
                    $founderName = $circleReq->user
                        ? (trim(($circleReq->user->first_name ?? '') . ' ' . ($circleReq->user->last_name ?? '')) ?: $circleReq->user->display_name ?: 'Founder')
                        : 'Founder';
                    $lockedMap[(int) $founderSubId] = [
                        'locked_by' => "{$founderName} (Founder)",
                        'is_founder' => true,
                    ];
                }

                // Check nominees
                foreach ($circleReq->nominees as $nom) {
                    if (! empty($nom->subcategory_id)) {
                        $lockedMap[(int) $nom->subcategory_id] = [
                            'locked_by' => $nom->name ?: 'Peer',
                            'is_founder' => false,
                        ];
                    }
                }
            }
        }

        // 2. If client passed temporary taken_ids (e.g. "101,102")
        if (! empty($takenIdsInput)) {
            $rawTaken = is_array($takenIdsInput) ? $takenIdsInput : explode(',', (string) $takenIdsInput);
            foreach ($rawTaken as $tId) {
                $intId = (int) trim((string) $tId);
                if ($intId > 0 && ! isset($lockedMap[$intId])) {
                    $lockedMap[$intId] = [
                        'locked_by' => 'Selected in another row',
                        'is_founder' => false,
                    ];
                }
            }
        }

        $subcategories = \App\Models\CircleCategoryLevel4::query()
            ->where('circle_category_id', $category->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'circle_category_id', 'level2_id', 'level3_id', 'name', 'slug', 'sort_order', 'is_active']);

        $items = $subcategories->map(function ($sub) use ($lockedMap) {
            $isLocked = isset($lockedMap[$sub->id]);
            return [
                'id'            => $sub->id,
                'category_id'   => $sub->circle_category_id,
                'level2_id'     => $sub->level2_id,
                'level3_id'     => $sub->level3_id,
                'name'          => $sub->name,
                'slug'          => $sub->slug,
                'sort_order'    => $sub->sort_order,
                'is_locked'     => $isLocked,
                'locked_by'     => $isLocked ? $lockedMap[$sub->id]['locked_by'] : null,
                'is_selectable' => ! $isLocked,
            ];
        });

        return $this->success([
            'category_id'   => $category->id,
            'category_name' => $category->name,
            'total'         => $items->count(),
            'locked_count'  => count($lockedMap),
            'items'         => $items,
        ], 'Subcategories fetched successfully.');
    }

    /**
     * Real-time peer check helper for Flutter contact selection.
     * Given a phone number, returns whether the contact is already an active Peer,
     * auto-fills business name & category, and checks if they are already in 3 circles.
     */
    public function checkPeer(Request $request): JsonResponse
    {
        $v = $request->validate([
            'phone' => ['required', 'string'],
        ]);

        $rawPhone = trim((string) $v['phone']);
        $digits = substr((string) preg_replace('/\D+/', '', $rawPhone), -10);

        if (strlen($digits) < 10) {
            return $this->success([
                'is_peer'                => false,
                'user_id'                => null,
                'name'                   => null,
                'business_name'          => null,
                'category_id'            => null,
                'category_name'          => null,
                'subcategory_id'         => null,
                'subcategory_name'       => null,
                'active_circles_count'   => 0,
                'is_max_circles_reached' => false,
                'warning'                => null,
                'avatar_url'             => null,
            ], 'Phone checked.');
        }

        $user = User::query()
            ->whereNull('deleted_at')
            ->where(function ($q) use ($digits): void {
                $q->where('phone', 'like', "%{$digits}");
                if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'secondary_mobile')) {
                    $q->orWhere('secondary_mobile', 'like', "%{$digits}");
                }
            })
            ->first();

        if (! $user) {
            return $this->success([
                'is_peer'                => false,
                'user_id'                => null,
                'name'                   => null,
                'business_name'          => null,
                'category_id'            => null,
                'category_name'          => null,
                'subcategory_id'         => null,
                'subcategory_name'       => null,
                'active_circles_count'   => 0,
                'is_max_circles_reached' => false,
                'warning'                => null,
                'avatar_url'             => null,
            ], 'Phone checked.');
        }

        // Count active circle memberships
        $activeCirclesCount = DB::table('circle_members')
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'approved'])
            ->count();

        $isMaxCircles = $activeCirclesCount >= 3;

        $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->display_name ?: $user->name ?? 'Peer';
        $businessName = $user->company_name ?? $user->business_name ?? null;

        // Category resolution (Level 1)
        $categoryId = $user->main_business_category_id ?? $user->category_id ?? null;
        $categoryName = null;
        if ($categoryId) {
            $cat = CircleCategory::find($categoryId);
            $categoryName = $cat?->name;
        }

        // Subcategory resolution (Level 4)
        $subcategoryId = $user->business_category_id ?? $user->level_4_category_id ?? null;
        $subcategoryName = null;
        if ($subcategoryId) {
            $subcat = \App\Models\CircleCategoryLevel4::find($subcategoryId);
            $subcategoryName = $subcat?->name;
            if (! $categoryId && $subcat && $subcat->circle_category_id) {
                $categoryId = $subcat->circle_category_id;
                $cat = CircleCategory::find($categoryId);
                $categoryName = $cat?->name;
            }
        }

        return $this->success([
            'is_peer'                => true,
            'user_id'                => (string) $user->id,
            'name'                   => $name,
            'business_name'          => $businessName,
            'category_id'            => $categoryId,
            'category_name'          => $categoryName,
            'subcategory_id'         => $subcategoryId,
            'subcategory_name'       => $subcategoryName,
            'active_circles_count'   => $activeCirclesCount,
            'is_max_circles_reached' => $isMaxCircles,
            'warning'                => $isMaxCircles ? "This peer is already a member of {$activeCirclesCount} circles." : null,
            'avatar_url'             => $user->profile_photo_url ?? null,
        ], 'Peer matched successfully.');
    }

    /**
     * Submit a new 6-Step "Build Your Own Circle" request.
     */
    public function store(SubmitUserCircleRequest $request): JsonResponse
    {
        $user = $request->user();
        $minimum = self::getMinimumRequired();

        // Check if user already has a pending/under_review request
        $existing = UserCircleRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [UserCircleRequest::STATUS_PENDING, UserCircleRequest::STATUS_UNDER_REVIEW])
            ->first();

        if ($existing) {
            return $this->error('You already have a pending circle request. Please wait for it to be reviewed.', 422);
        }

        $validated = $request->validated();
        $peers     = $validated['recommended_peers'] ?? $validated['nominees'] ?? [];

        // Build metadata bundle for the 6 steps
        $metadata = [
            'circle_type'                    => $validated['circle_type'] ?? 'industry',
            'why_circle'                     => $validated['why_circle'] ?? null,
            'ideal_member'                   => $validated['ideal_member'] ?? null,
            'problem_solved'                 => $validated['problem_solved'] ?? null,
            'founder_profile'                => [
                'business_name'         => $validated['founder_business_name'] ?? null,
                'years_in_business'     => $validated['founder_years_in_business'] ?? null,
                'website_linkedin'      => $validated['founder_website_linkedin'] ?? null,
                'category_id'           => $validated['founder_category_id'] ?? null,
                'leadership_experience' => $validated['founder_leadership_experience'] ?? null,
            ],
            'meeting_plan'                   => [
                'format'                     => $validated['meeting_mode'] ?? 'hybrid',
                'preferred_day'              => $validated['preferred_day'] ?? null,
                'preferred_time_slot'        => $validated['preferred_time_slot'] ?? null,
                'locality'                   => $validated['locality'] ?? null,
                'language'                   => $validated['meeting_language'] ?? 'English',
                'target_first_meeting_days'  => $validated['target_first_meeting_days'] ?? 30,
            ],
            'proposed_leadership'            => $validated['proposed_leadership'] ?? null,
            'declarations_accepted'          => $validated['declarations_accepted'] ?? true,
        ];

        $circleRequest = DB::transaction(function () use ($user, $validated, $peers, $minimum, $metadata): UserCircleRequest {
            $founderCategory = $validated['founder_category_id'] ?? null;
            $founderSubcategory = $validated['founder_subcategory_id'] ?? null;

            $req = UserCircleRequest::create([
                'user_id'                 => $user->id,
                'circle_name'             => $validated['circle_name'],
                'category_id'             => $validated['category_id'] ?? null,
                'founder_category_id'     => $founderCategory ? (int) $founderCategory : null,
                'founder_subcategory_id'  => $founderSubcategory ? (int) $founderSubcategory : null,
                'description'             => $validated['why_circle'] ?? $validated['description'] ?? null,
                'city'                    => $validated['city'] ?? null,
                'country'                 => $validated['country'] ?? 'India',
                'meeting_mode'            => $validated['meeting_mode'] ?? 'hybrid',
                'meeting_frequency'       => $validated['meeting_frequency'] ?? 'monthly',
                'status'                  => UserCircleRequest::STATUS_PENDING,
                'minimum_peers_required'  => $minimum,
                'metadata'                => $metadata,
            ]);

            foreach ($peers as $peer) {
                $peerId = $peer['peer_id'] ?? $peer['user_id'] ?? $peer['nominee_user_id'] ?? null;
                $peerName = $peer['name'] ?? null;
                $peerPhone = $peer['phone'] ?? null;
                $peerEmail = $peer['email'] ?? null;
                $peerBusiness = $peer['business_name'] ?? null;
                $peerCategory = $peer['category_id'] ?? null;
                $peerSubcategory = $peer['subcategory_id'] ?? $peer['sub_category_id'] ?? null;
                $source = $peer['source'] ?? (! empty($peerId) ? 'app' : 'manual');

                if (! empty($peerId)) {
                    $existingUser = User::find($peerId);
                    if ($existingUser) {
                        $peerName = $peerName ?: (trim(($existingUser->first_name ?? '') . ' ' . ($existingUser->last_name ?? '')) ?: $existingUser->display_name ?: $existingUser->name);
                        $peerPhone = $peerPhone ?: $existingUser->phone;
                        $peerEmail = $peerEmail ?: $existingUser->email;
                        $peerBusiness = $peerBusiness ?: ($existingUser->company_name ?? $existingUser->business_name ?? null);
                        $peerCategory = $peerCategory ?: ($existingUser->main_business_category_id ?? $existingUser->category_id ?? null);
                        $peerSubcategory = $peerSubcategory ?: ($existingUser->business_category_id ?? $existingUser->level_4_category_id ?? null);
                    }
                }

                UserCircleRequestNominee::create([
                    'user_circle_request_id' => $req->id,
                    'nominee_user_id'        => $peerId,
                    'name'                   => $peerName,
                    'phone'                  => $peerPhone,
                    'email'                  => $peerEmail,
                    'business_name'          => $peerBusiness,
                    'category_id'            => $peerCategory ? (int) $peerCategory : null,
                    'subcategory_id'         => $peerSubcategory ? (int) $peerSubcategory : null,
                    'interest_level'         => $peer['interest_level'] ?? 'medium',
                    'source'                 => $source,
                ]);
            }

            return $req;
        });

        $circleRequest->load('nominees');
        $allCount = $circleRequest->nominees->count();
        $completeCount = $circleRequest->nominees->filter(function ($n) {
            return ! empty($n->name) && ! empty($n->phone) && ! empty($n->business_name) && ! empty($n->category_id);
        })->count();

        return $this->success([
            'request'                        => $circleRequest,
            'recommended_peers_count'        => $allCount,
            'complete_peers_count'           => $completeCount,
            'incomplete_peers_count'         => max(0, $allCount - $completeCount),
            'minimum_required'               => $minimum,
            'has_minimum_recommended_peers' => $allCount >= $minimum,
            'remaining_recommended_peers'   => max(0, $minimum - $allCount),
        ], 'Circle request submitted successfully.', 201);
    }

    /**
     * Get my circle requests.
     */
    public function myRequests(Request $request): JsonResponse
    {
        $requests = UserCircleRequest::query()
            ->where('user_id', $request->user()->id)
            ->with(['category', 'nominees'])
            ->withCount('nominees')
            ->orderByDesc('created_at')
            ->get();

        $minimum = self::getMinimumRequired();

        return $this->success([
            'items'              => $requests->map(fn (UserCircleRequest $r) => $this->formatRequest($r, $minimum)),
            'minimum_required'   => $minimum,
        ], 'My circle requests fetched.');
    }

    /**
     * Show a single circle request.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $circleRequest = UserCircleRequest::query()
            ->where('user_id', $request->user()->id)
            ->with(['category', 'nominees'])
            ->withCount('nominees')
            ->findOrFail($id);

        $minimum = self::getMinimumRequired();

        return $this->success($this->formatRequest($circleRequest, $minimum));
    }

    /**
     * Add/replace recommended peers for an existing pending request.
     */
    public function updateNominees(UpdateUserCircleRequestNominees $request, string $id): JsonResponse
    {
        $circleRequest = UserCircleRequest::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', [UserCircleRequest::STATUS_PENDING, UserCircleRequest::STATUS_UNDER_REVIEW])
            ->findOrFail($id);

        $validated = $request->validated();
        $peers     = $validated['recommended_peers'] ?? $validated['nominees'] ?? [];
        $minimum   = self::getMinimumRequired();

        DB::transaction(function () use ($circleRequest, $peers): void {
            UserCircleRequestNominee::where('user_circle_request_id', $circleRequest->id)->delete();

            foreach ($peers as $peer) {
                $peerId = $peer['peer_id'] ?? $peer['user_id'] ?? $peer['nominee_user_id'] ?? null;
                $peerName = $peer['name'] ?? null;
                $peerPhone = $peer['phone'] ?? null;
                $peerEmail = $peer['email'] ?? null;
                $peerBusiness = $peer['business_name'] ?? null;
                $peerCategory = $peer['category_id'] ?? null;
                $peerSubcategory = $peer['subcategory_id'] ?? $peer['sub_category_id'] ?? null;
                $source = $peer['source'] ?? (! empty($peerId) ? 'app' : 'manual');

                if (! empty($peerId)) {
                    $existingUser = User::find($peerId);
                    if ($existingUser) {
                        $peerName = $peerName ?: (trim(($existingUser->first_name ?? '') . ' ' . ($existingUser->last_name ?? '')) ?: $existingUser->display_name ?: $existingUser->name);
                        $peerPhone = $peerPhone ?: $existingUser->phone;
                        $peerEmail = $peerEmail ?: $existingUser->email;
                        $peerBusiness = $peerBusiness ?: ($existingUser->company_name ?? $existingUser->business_name ?? null);
                        $peerCategory = $peerCategory ?: ($existingUser->main_business_category_id ?? $existingUser->category_id ?? null);
                        $peerSubcategory = $peerSubcategory ?: ($existingUser->business_category_id ?? $existingUser->level_4_category_id ?? null);
                    }
                }

                UserCircleRequestNominee::create([
                    'user_circle_request_id' => $circleRequest->id,
                    'nominee_user_id'        => $peerId,
                    'name'                   => $peerName,
                    'phone'                  => $peerPhone,
                    'email'                  => $peerEmail,
                    'business_name'          => $peerBusiness,
                    'category_id'            => $peerCategory ? (int) $peerCategory : null,
                    'subcategory_id'         => $peerSubcategory ? (int) $peerSubcategory : null,
                    'interest_level'         => $peer['interest_level'] ?? 'medium',
                    'source'                 => $source,
                ]);
            }
        });

        $circleRequest->load('nominees');
        $allCount = $circleRequest->nominees->count();
        $completeCount = $circleRequest->nominees->filter(function ($n) {
            return ! empty($n->name) && ! empty($n->phone) && ! empty($n->business_name) && ! empty($n->category_id);
        })->count();

        return $this->success([
            'request'                        => $circleRequest,
            'recommended_peers_count'        => $allCount,
            'complete_peers_count'           => $completeCount,
            'incomplete_peers_count'         => max(0, $allCount - $completeCount),
            'minimum_required'               => $minimum,
            'has_minimum_recommended_peers' => $allCount >= $minimum,
            'remaining_recommended_peers'   => max(0, $minimum - $allCount),
        ], 'Recommended peers updated successfully.');
    }

    /**
     * Cancel my own request.
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $circleRequest = UserCircleRequest::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', [UserCircleRequest::STATUS_PENDING, UserCircleRequest::STATUS_UNDER_REVIEW])
            ->findOrFail($id);

        $circleRequest->status = UserCircleRequest::STATUS_REJECTED;
        $circleRequest->save();

        return $this->success(null, 'Circle request cancelled.');
    }

    private function formatRequest(UserCircleRequest $r, int $minimum): array
    {
        $count = $r->nominees_count ?? $r->nominees->count();
        $resolvedNominees = $this->nomineeResolver->resolveNominees($r);
        $summary = $this->nomineeResolver->getSummary($r, $resolvedNominees);

        $completeCount = $resolvedNominees->filter(fn ($n) => ! empty($n['is_complete']))->count();

        return [
            'id'                   => $r->id,
            'circle_name'          => $r->circle_name,
            'category_id'          => $r->category_id,
            'category'             => $r->category ? [
                'id'         => $r->category->id,
                'name'       => $r->category->name,
                'slug'       => $r->category->slug,
                'circle_key' => $r->category->circle_key,
            ] : null,
            'founder_category_id'    => $r->founder_category_id ?? ($r->metadata['founder_category_id'] ?? null),
            'founder_category'       => $r->founderCategory ? [
                'id'   => $r->founderCategory->id,
                'name' => $r->founderCategory->name,
                'slug' => $r->founderCategory->slug,
            ] : null,
            'founder_subcategory_id' => $r->founder_subcategory_id ?? ($r->metadata['founder_subcategory_id'] ?? null),
            'founder_subcategory'    => $r->founderSubcategory ? [
                'id'   => $r->founderSubcategory->id,
                'name' => $r->founderSubcategory->name,
                'slug' => $r->founderSubcategory->slug,
            ] : null,
            'description'          => $r->description,
            'city'                 => $r->city,
            'country'              => $r->country,
            'meeting_mode'         => $r->meeting_mode,
            'meeting_frequency'    => $r->meeting_frequency,
            'status'               => $r->status,
            'metadata'             => $r->metadata,
            'recommended_peers_count'        => $count,
            'complete_peers_count'           => $completeCount,
            'incomplete_peers_count'         => max(0, $count - $completeCount),
            'minimum_required'               => $minimum,
            'has_minimum_recommended_peers' => $count >= $minimum,
            'remaining_recommended_peers'   => max(0, $minimum - $count),
            'summary'                        => $summary,
            'recommended_peers_joined_unity_count'  => $summary['joined_unity_count'],
            'recommended_peers_joined_circle_count' => $summary['joined_circle_count'],
            'created_circle_id'    => $r->created_circle_id,
            'circle_created_at'    => $r->circle_created_at?->toISOString(),
            'recommended_peers'    => $resolvedNominees,
            'created_at'           => $r->created_at?->toISOString(),
            'updated_at'           => $r->updated_at?->toISOString(),
        ];
    }
}
