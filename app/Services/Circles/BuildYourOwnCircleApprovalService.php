<?php

declare(strict_types=1);

namespace App\Services\Circles;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCircleRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Handles automatic Circle creation when admin approves a "Build Your Own Circle" request.
 */
final class BuildYourOwnCircleApprovalService
{
    public function __construct(
        private readonly ?UserCircleNomineeStatusResolver $nomineeResolver = null,
    ) {}

    /**
     * Approve the request and auto-create a Circle from it.
     *
     * @param  UserCircleRequest  $circleRequest
     * @param  string|null        $adminUserId      Admin user ID performing the action
     * @param  string|null        $adminNotes
     * @param  array<string,mixed> $overrides        Optional overrides for the circle (e.g. city_id, status)
     * @return array{circle: Circle, circle_request: UserCircleRequest}
     */
    public function approve(
        UserCircleRequest $circleRequest,
        ?string $adminUserId,
        ?string $adminNotes = null,
        array $overrides = [],
    ): array {
        if ($circleRequest->status === UserCircleRequest::STATUS_APPROVED && $circleRequest->created_circle_id) {
            $circle = Circle::find($circleRequest->created_circle_id);
            if ($circle) {
                return ['circle' => $circle, 'circle_request' => $circleRequest];
            }
        }

        return DB::transaction(function () use ($circleRequest, $adminUserId, $adminNotes, $overrides): array {
            // ── 1. Build Circle payload from request ──────────────────────────────
            $circleName = (string) $circleRequest->circle_name;
            $baseSlug   = Str::slug($circleName) ?: 'circle';
            $slug       = $baseSlug;
            $counter    = 1;
            while (Circle::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            // Resolve city_id from city name if not overridden
            $cityId = $overrides['city_id'] ?? null;
            if (! $cityId && $circleRequest->city && Schema::hasTable('cities')) {
                $cityId = DB::table('cities')
                    ->where('name', 'ilike', trim($circleRequest->city))
                    ->value('id');
            }

            // Founder = the peer who submitted the circle request
            $founderId = (string) $circleRequest->user_id;

            $circleStatus = $overrides['status'] ?? 'active';

            // Auto-resolve DED for city/district (e.g., Ahmedabad DED)
            $dedUserId = $overrides['ded_user_id'] ?? $this->resolveDedForLocation($circleRequest->city, $cityId);

            // Auto-resolve Industry Director for category
            $categoryId = $overrides['category_id'] ?? $circleRequest->category_id;
            $industryDirectorUserId = $overrides['industry_director_user_id'] ?? $this->resolveIndustryDirector(
                $categoryId ? (int) $categoryId : null,
                $circleName
            );

            $calendar = [
                'settings' => array_filter([
                    'meeting_mode'      => $circleRequest->meeting_mode,
                    'meeting_frequency' => $circleRequest->meeting_frequency,
                ]),
                'leadership' => array_filter([
                    'circle_founder_user_id'    => $founderId,
                    'founder_user_id'           => $founderId,
                    'ded_user_id'               => $dedUserId,
                    'industry_director_user_id' => $industryDirectorUserId,
                ]),
            ];

            $circleData = array_merge([
                'name'                       => $circleName,
                'slug'                       => $slug,
                'description'                => $circleRequest->description,
                'city_id'                    => $cityId,
                'country'                    => $circleRequest->country ?? 'India',
                'meeting_mode'               => $circleRequest->meeting_mode,
                'meeting_frequency'          => $circleRequest->meeting_frequency,
                'circle_founder_user_id'     => $founderId,
                'founder_user_id'            => $founderId,
                'ded_user_id'                => $dedUserId,
                'industry_director_user_id'  => $industryDirectorUserId,
                'status'                     => $circleStatus,
                'type'                       => 'public',
                'calendar'                   => $calendar,
            ], $overrides);

            // Remove keys that aren't fillable on the Circle model
            $circle = new Circle();
            foreach ($circleData as $key => $value) {
                if ($key === 'template_id' && ! empty($value) && ! Str::isUuid((string) $value)) {
                    continue;
                }
                if (Schema::hasColumn('circles', $key) || in_array($key, ['calendar'], true)) {
                    $circle->{$key} = $value;
                }
            }
            $circle->slug = $slug;
            $circle->save();

            // ── Attach Level 1 Category if provided ──────────────────────────────
            if ($categoryId) {
                $catId = (int) $categoryId;
                if (method_exists($circle, 'categories')) {
                    $circle->categories()->syncWithoutDetaching([$catId]);
                }

                if (empty($circle->industry_tags) && Schema::hasTable('circle_categories')) {
                    $catName = DB::table('circle_categories')->where('id', $catId)->value('name');
                    if ($catName) {
                        $circle->industry_tags = [$catName];
                        $circle->save();
                    }
                }
            }

            // ── 2. Add submitter as Circle Founder member ─────────────────────────
            $founderCatId = $request->founder_category_id ?? ($request->metadata['founder_category_id'] ?? null);
            $founderSubId = $request->founder_subcategory_id ?? ($request->metadata['founder_subcategory_id'] ?? null);
            $this->addFounderMember($circle->id, $founderId, $founderCatId ? (int) $founderCatId : null, $founderSubId ? (int) $founderSubId : null);

            // ── 3. Add DED member if resolved ─────────────────────────────────────
            if ($dedUserId) {
                $this->addLeaderMember($circle->id, $dedUserId, 'ded');
            }

            // ── 4. Add Industry Director member if resolved ───────────────────────
            if ($industryDirectorUserId) {
                $this->addLeaderMember($circle->id, $industryDirectorUserId, 'industry_director');
            }

            // ── Sync leadership tenure across circle ──────────────────────────────
            Circle::syncLeadershipFromMembers($circle->id);

            // ── 5. Mark request as approved and store the created circle ID ───────
            // Recommended peers remain strictly as recommended peers (nominees)
            $circleRequest->status            = UserCircleRequest::STATUS_APPROVED;
            $circleRequest->admin_notes       = $adminNotes ?? $circleRequest->admin_notes;
            $circleRequest->reviewed_by       = $adminUserId;
            $circleRequest->reviewed_at       = now();
            $circleRequest->created_circle_id = $circle->id;
            $circleRequest->circle_created_at = now();
            $circleRequest->save();

            // ── 6. Sync founder user's active circle ──────────────────────────────
            $this->syncFounderActiveCircle($founderId, $circle);

            $circle->load(['cityRef', 'founder', 'circleFounder', 'ded', 'industryDirector', 'members']);

            return [
                'circle'         => $circle,
                'circle_request' => $circleRequest,
            ];
        });
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Resolve DED User ID for a given city or district.
     */
    public function resolveDedForLocation(?string $cityName, ?string $cityId): ?string
    {
        $cityName = trim((string) $cityName);

        // 1. Check admin_ded_districts
        if (Schema::hasTable('admin_ded_districts')) {
            $dedRow = DB::table('admin_ded_districts')
                ->where(function ($q) use ($cityName, $cityId) {
                    if ($cityId && Schema::hasColumn('admin_ded_districts', 'district_id')) {
                        $q->where('district_id', $cityId);
                    }
                    if ($cityName !== '' && Schema::hasColumn('admin_ded_districts', 'district_name')) {
                        $q->orWhere('district_name', 'ilike', "%{$cityName}%");
                    }
                })
                ->first();

            if ($dedRow) {
                $uid = $dedRow->user_id ?? null;
                if (! $uid && ! empty($dedRow->admin_user_id)) {
                    $uid = DB::table('admin_users')->where('id', $dedRow->admin_user_id)->value('user_id')
                        ?: $dedRow->admin_user_id;
                }
                if ($uid && DB::table('users')->where('id', $uid)->exists()) {
                    return (string) $uid;
                }
            }
        }

        // 2. Check existing circles in the same city / city_id with ded_user_id assigned
        $existingCircleDed = Circle::query()
            ->whereNotNull('ded_user_id')
            ->where(function ($q) use ($cityName, $cityId) {
                if ($cityId) {
                    $q->where('city_id', $cityId);
                }
                if ($cityName !== '') {
                    $q->orWhere('name', 'ilike', "%{$cityName}%");
                }
            })
            ->latest('created_at')
            ->value('ded_user_id');

        if ($existingCircleDed && DB::table('users')->where('id', $existingCircleDed)->exists()) {
            return (string) $existingCircleDed;
        }

        // 3. Fallback: check circle_members with role 'ded' in circles matching this city
        $circleMemberDed = CircleMember::query()
            ->where('role', 'ded')
            ->whereHas('circle', function ($q) use ($cityName, $cityId) {
                if ($cityId) {
                    $q->where('city_id', $cityId);
                }
                if ($cityName !== '') {
                    $q->orWhere('name', 'ilike', "%{$cityName}%");
                }
            })
            ->latest('joined_at')
            ->value('user_id');

        if ($circleMemberDed && DB::table('users')->where('id', $circleMemberDed)->exists()) {
            return (string) $circleMemberDed;
        }

        // 4. Any global DED fallback if available
        $globalDed = Circle::query()->whereNotNull('ded_user_id')->latest()->value('ded_user_id');
        if ($globalDed && DB::table('users')->where('id', $globalDed)->exists()) {
            return (string) $globalDed;
        }

        return null;
    }

    /**
     * Resolve Industry Director User ID for a given category/vertical or circle name.
     */
    public function resolveIndustryDirector(?int $categoryId, ?string $circleName): ?string
    {
        // 1. Check industry_director_assignments
        if (Schema::hasTable('industry_director_assignments')) {
            $assignment = DB::table('industry_director_assignments')
                ->where('is_active', true)
                ->where(function ($q) use ($categoryId) {
                    if ($categoryId) {
                        $q->where('industry_category_id', $categoryId)
                            ->orWhere('main_business_category_id', $categoryId)
                            ->orWhere('industry_id', (string) $categoryId);
                    }
                })
                ->first();

            if ($assignment) {
                $uid = $assignment->user_id ?? null;
                if (! $uid && ! empty($assignment->admin_user_id)) {
                    $uid = DB::table('admin_users')->where('id', $assignment->admin_user_id)->value('user_id')
                        ?: $assignment->admin_user_id;
                }
                if ($uid && DB::table('users')->where('id', $uid)->exists()) {
                    return (string) $uid;
                }
            }
        }

        // 2. Check existing circles with same category and industry director assigned
        if ($categoryId) {
            $catCircleId = Circle::query()
                ->whereNotNull('industry_director_user_id')
                ->where(function ($q) use ($categoryId) {
                    $q->where('category_id', $categoryId)
                        ->orWhereHas('categories', fn ($sub) => $sub->where('circle_categories.id', $categoryId));
                })
                ->latest('created_at')
                ->value('industry_director_user_id');

            if ($catCircleId && DB::table('users')->where('id', $catCircleId)->exists()) {
                return (string) $catCircleId;
            }
        }

        // 3. Fallback: Check any existing circle with an industry director assigned
        $fallbackCircleId = Circle::query()
            ->whereNotNull('industry_director_user_id')
            ->latest('created_at')
            ->value('industry_director_user_id');

        if ($fallbackCircleId && DB::table('users')->where('id', $fallbackCircleId)->exists()) {
            return (string) $fallbackCircleId;
        }

        return null;
    }

    public function addFounderMember(string $circleId, string $userId, ?int $categoryId = null, ?int $subcategoryId = null): void
    {
        $roleId = Role::where('key', 'circle_founder')->value('id');

        $fillData = [
            'role'      => 'circle_founder',
            'role_id'   => $roleId,
            'status'    => 'approved',
            'joined_at' => now(),
            'left_at'   => null,
        ];
        if ($categoryId !== null) {
            $fillData['category_id'] = $categoryId;
        }
        if ($subcategoryId !== null) {
            $fillData['level_4_category_id'] = $subcategoryId;
        }

        $existing = CircleMember::withTrashed()
            ->where('circle_id', $circleId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }
            $existing->forceFill($fillData)->save();
        } else {
            CircleMember::create(array_merge([
                'circle_id' => $circleId,
                'user_id'   => $userId,
            ], $fillData));
        }
    }

    public function addLeaderMember(string $circleId, string $userId, string $roleKey): void
    {
        $roleId = Role::where('key', $roleKey)->value('id');

        $existing = CircleMember::withTrashed()
            ->where('circle_id', $circleId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }
            $existing->forceFill([
                'role'      => $roleKey,
                'role_id'   => $roleId,
                'status'    => 'approved',
                'joined_at' => now(),
                'left_at'   => null,
            ])->save();
        } else {
            CircleMember::create([
                'circle_id' => $circleId,
                'user_id'   => $userId,
                'role'      => $roleKey,
                'role_id'   => $roleId,
                'status'    => 'approved',
                'joined_at' => now(),
            ]);
        }
    }

    private function syncFounderActiveCircle(string $founderId, Circle $circle): void
    {
        $user = User::find($founderId);
        if (! $user) {
            return;
        }

        if (Schema::hasColumn('users', 'active_circle_id') && empty($user->active_circle_id)) {
            $user->active_circle_id = $circle->id;
            if (Schema::hasColumn('users', 'circle_joined_at')) {
                $user->circle_joined_at = Carbon::now();
            }
            $user->save();
        }
    }
}
