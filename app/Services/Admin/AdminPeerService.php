<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\AdminUser;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\City;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdminPeerService
{
    public const PAID_TIER_STATUSES = [
        'only unity peer',
        'global peer',
        'circle peer',
        'multi circle peer',
        'pro',
        'active',
    ];

    public const INACTIVE_STATUSES = [
        '',
        'visitor',
        'free_peer',
        'free_trial_peer',
        'free',
        'free_trial',
        'inactive',
        'rejected',
        'cancelled',
        'canceled',
        'suspended',
    ];

    public function __construct(
        private readonly AdminScopeService $scope,
    ) {}

    /**
     * Fetch paginated list of canonical peers with comprehensive filtering and scoping.
     */
    public function listPeers(array $filters = [], User|AdminUser|null $actor = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = User::query()
            ->with([
                'roles:id,key,name',
                'circleMemberships' => fn ($q) => $q->where('status', 'approved')->whereNull('deleted_at')->with('circle:id,name'),
                'mainBusinessCategory:id,name',
                'businessCategory:id,name',
                'cityRelation:id,name,state,country',
            ])
            ->withCount([
                'circleMemberships' => fn ($q) => $q->where('status', 'approved')->whereNull('deleted_at'),
            ]);

        if ($actor !== null) {
            $this->scope->applyUserScope($query, $actor);
        }

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $filters);

        $perPage = max(1, min(100, $perPage));

        return $query->paginate($perPage);
    }

    /**
     * Fetch canonical peers roster for a specific circle.
     */
    public function listCirclePeers(string $circleId, array $filters = [], User|AdminUser|null $actor = null, int $perPage = 20): LengthAwarePaginator
    {
        if ($actor !== null) {
            $this->scope->assertCircleVisible($actor, $circleId);
        }

        $filters['circle_id'] = $circleId;

        return $this->listPeers($filters, $actor, $perPage);
    }

    /**
     * Apply all search and criteria filters to the User query.
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        // 1. Text Search
        if (! empty($filters['search'])) {
            $rawSearch = trim((string) $filters['search']);
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $rawSearch).'%';

            $query->where(function (Builder $q) use ($term, $rawSearch): void {
                $q->where('first_name', 'ILIKE', $term)
                    ->orWhere('last_name', 'ILIKE', $term)
                    ->orWhere('display_name', 'ILIKE', $term)
                    ->orWhere('email', 'ILIKE', $term)
                    ->orWhere('phone', 'ILIKE', $term)
                    ->orWhere('company_name', 'ILIKE', $term);

                if (Str::isUuid($rawSearch)) {
                    $q->orWhere('id', $rawSearch);
                }

                $q->orWhere('peer_id', 'ILIKE', $term);
            });
        }

        // 2. Geographic Filters
        if (! empty($filters['country'])) {
            $country = trim((string) $filters['country']);
            $query->where(function (Builder $q) use ($country): void {
                $q->where('country', 'ILIKE', $country)
                    ->orWhere('business_country', 'ILIKE', $country);
            });
        }

        if (! empty($filters['state'])) {
            $state = trim((string) $filters['state']);
            $query->where(function (Builder $q) use ($state): void {
                $q->where('state', 'ILIKE', $state)
                    ->orWhere('business_state', 'ILIKE', $state);
            });
        }

        if (! empty($filters['city'])) {
            $city = trim((string) $filters['city']);
            $cityIds = [];
            if (Schema::hasTable('cities')) {
                try {
                    $cityIds = City::query()->where('name', 'ILIKE', $city)->pluck('id')->all();
                } catch (\Throwable) {
                    $cityIds = [];
                }
            }

            $query->where(function (Builder $q) use ($city, $cityIds): void {
                $q->where('city', 'ILIKE', $city)
                    ->orWhere('business_city', 'ILIKE', $city);
                if (! empty($cityIds)) {
                    $q->orWhereIn('city_id', $cityIds);
                }
            });
        }

        if (! empty($filters['city_id'])) {
            $query->where('city_id', (string) $filters['city_id']);
        }

        // 3. Industry & Subcategory Filters
        if (! empty($filters['industry_id']) || ! empty($filters['industry'])) {
            $ind = (string) ($filters['industry_id'] ?? $filters['industry']);
            if (Str::isUuid($ind) || is_numeric($ind)) {
                $query->where(function (Builder $q) use ($ind): void {
                    $q->where('business_category_id', $ind)
                        ->orWhere('main_business_category_id', $ind);
                });
            } else {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $ind).'%';
                $query->where(function (Builder $q) use ($term): void {
                    $q->whereHas('mainBusinessCategory', fn ($c) => $c->where('name', 'ILIKE', $term))
                        ->orWhereHas('businessCategory', fn ($c) => $c->where('name', 'ILIKE', $term));
                });
            }
        }

        if (! empty($filters['sub_category']) || ! empty($filters['subcategory'])) {
            $sub = trim((string) ($filters['sub_category'] ?? $filters['subcategory']));
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $sub).'%';
            $query->where('business_sub_category', 'ILIKE', $term);
        }

        // 4. Circle Filter
        if (! empty($filters['circle_id'])) {
            $circleId = (string) $filters['circle_id'];
            $query->whereHas('circleMemberships', function (Builder $m) use ($circleId): void {
                $m->where('circle_id', $circleId)
                    ->where('status', 'approved')
                    ->whereNull('deleted_at');
            });
        }

        // 5. Peer Type / Membership Tier
        if (! empty($filters['peer_type'])) {
            $peerType = strtolower(trim((string) $filters['peer_type']));
            match ($peerType) {
                'pro', 'paid' => $query->where(function (Builder $q): void {
                    $q->whereNotIn(
                        DB::raw('LOWER(COALESCE(membership_status, \'\'))'),
                        self::INACTIVE_STATUSES
                    );
                }),
                'free_trial', 'trial' => $query->where(function (Builder $q): void {
                    $q->whereIn(
                        DB::raw('LOWER(COALESCE(membership_status, \'\'))'),
                        ['free_trial_peer', 'free_trial']
                    );
                }),
                'free' => $query->where(function (Builder $q): void {
                    $q->whereIn(
                        DB::raw('LOWER(COALESCE(membership_status, \'\'))'),
                        ['free_peer', 'free', 'visitor', '']
                    );
                }),
                'sponsored' => $query->where('is_sponsored_member', true),
                default => null,
            };
        }

        // 6. Explicit Membership Status Filter
        if (! empty($filters['membership_status'])) {
            $status = (string) $filters['membership_status'];
            $query->where('membership_status', $status);
        }

        // 7. Active Status Filter
        $hasIsActive = \Illuminate\Support\Facades\Schema::hasColumn('users', 'is_active');
        if (isset($filters['is_active'])) {
            $isActive = filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN);
            if ($hasIsActive) {
                $query->where('is_active', $isActive);
            } else {
                $query->where('status', $isActive ? 'active' : 'inactive');
            }
        }

        if (! empty($filters['status']) && strtolower(trim((string) $filters['status'])) !== 'all') {
            $status = strtolower(trim((string) $filters['status']));
            if ($status === 'active') {
                $query->where(function (Builder $q) use ($hasIsActive): void {
                    $q->where('status', 'active');
                    if ($hasIsActive) {
                        $q->orWhere('is_active', true);
                    }
                });
            } elseif ($status === 'inactive') {
                $query->where(function (Builder $q) use ($hasIsActive): void {
                    $q->where('status', 'inactive');
                    if ($hasIsActive) {
                        $q->orWhere('is_active', false);
                    }
                });
            } elseif ($status === 'suspended') {
                $query->where('status', 'suspended');
            } elseif ($status === 'pending') {
                $query->where('status', 'pending');
            } elseif ($status === 'expired') {
                $query->where(function (Builder $q): void {
                    $q->where('status', 'expired')
                        ->orWhere(function (Builder $sub): void {
                            $sub->whereNotNull('membership_ends_at')
                                ->where('membership_ends_at', '<', now());
                        });
                });
            }
        }

        // 8. Role Filter
        if (! empty($filters['role'])) {
            $roleKey = (string) $filters['role'];
            $query->whereHas('roles', fn ($r) => $r->where('key', $roleKey));
        }
    }

    /**
     * Apply sorting to query.
     */
    private function applySorting(Builder $query, array $filters): void
    {
        $sortBy = strtolower((string) ($filters['sort_by'] ?? $filters['sort'] ?? 'created_at'));
        $sortDir = strtolower((string) ($filters['sort_dir'] ?? $filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        match ($sortBy) {
            'name', 'first_name' => $query->orderBy('first_name', $sortDir)->orderBy('last_name', $sortDir),
            'display_name' => $query->orderBy('display_name', $sortDir),
            'email' => $query->orderBy('email', $sortDir),
            'coins', 'coins_balance' => $query->orderBy('coins_balance', $sortDir),
            'life_impacted', 'life_impacted_count' => $query->orderBy('life_impacted_count', $sortDir),
            'last_login_at', 'last_login' => $query->orderBy('last_login_at', $sortDir),
            'membership_expiry', 'expiry' => $query->orderBy('membership_ends_at', $sortDir),
            default => $query->orderBy('created_at', $sortDir),
        };
    }

    /**
     * Transform a User model into the canonical PeerAdmin schema.
     */
    public function transformPeer(User $user): array
    {
        $firstName = (string) ($user->first_name ?? '');
        $lastName = (string) ($user->last_name ?? '');
        $name = $user->adminDisplayName();

        $cityName = $user->cityRelation?->name ?? (is_string($user->city) ? $user->city : '');
        if (is_string($cityName) && str_starts_with(trim($cityName), '{')) {
            $decoded = json_decode($cityName, true);
            $cityName = $decoded['name'] ?? $decoded['label'] ?? $cityName;
        }

        $industryName = $user->mainBusinessCategory?->name
            ?? $user->businessCategory?->name
            ?? $user->business_sub_category
            ?? 'General';

        $avatarUrl = $user->profile_photo_url;
        if (empty($avatarUrl) && ! empty($user->profile_photo_file_id)) {
            $avatarUrl = url('/api/v1/files/'.$user->profile_photo_file_id);
        }

        $membershipStatus = (string) ($user->membership_status ?? 'free_peer');
        $lowerStatus = strtolower(trim(str_replace(' ', '_', $membershipStatus)));

        $isPaidTier = ! in_array($lowerStatus, self::INACTIVE_STATUSES, true);
        $isFreeTrial = in_array($lowerStatus, ['free_trial_peer', 'free_trial'], true);
        $isFree = in_array($lowerStatus, ['free_peer', 'free', 'visitor', ''], true);
        $isSponsored = (bool) $user->is_sponsored_member;

        $membershipLabel = match ($lowerStatus) {
            'only_unity_peer', 'global_peer' => 'Global Peer',
            'circle_peer' => 'Circle Peer',
            'multi_circle_peer' => 'Multi Circle Peer',
            'free_trial_peer', 'free_trial' => 'Free Trial Peer',
            'free_peer', 'free' => 'Free Peer',
            'visitor' => 'Visitor',
            default => Str::headline(str_replace('_', ' ', $membershipStatus)),
        };

        $endsAt = $user->membership_ends_at ?? $user->membership_expiry ?? null;
        $expiryDays = null;
        $isExpired = false;

        if ($endsAt !== null) {
            $carbonEnd = Carbon::parse($endsAt);
            $diff = now()->floatDiffInDays($carbonEnd, false);
            $expiryDays = $diff >= 0 ? (int) ceil($diff) : (int) floor($diff);
            $isExpired = $expiryDays < 0;
        }

        $userCircles = $user->circleMemberships ?? collect();
        $primaryCircle = $userCircles->first()?->circle;

        $circlesList = $userCircles->map(fn (CircleMember $m) => [
            'id' => $m->circle?->id ?? $m->circle_id,
            'name' => $m->circle?->name ?? 'Circle',
            'role' => $m->role ?? 'member',
            'status' => $m->status ?? 'approved',
            'joined_at' => $m->joined_at?->toISOString(),
        ])->values()->all();

        return [
            'id' => (string) $user->id,
            'peer_id' => $user->peer_id ?? ('PGU-'.substr((string) $user->id, 0, 8)),
            'name' => $name,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => (string) ($user->display_name ?? $name),
            'email' => (string) ($user->email ?? ''),
            'phone' => (string) ($user->phone ?? ''),
            'company_name' => (string) ($user->company_name ?? $user->company ?? ''),
            'designation' => (string) ($user->designation ?? 'Member'),
            'country' => (string) ($user->country ?? $user->business_country ?? 'India'),
            'state' => (string) ($user->state ?? $user->business_state ?? ''),
            'city' => trim((string) $cityName),
            'avatar_url' => $avatarUrl,
            'industry' => (string) $industryName,
            'industry_id' => $user->main_business_category_id ?? $user->business_category_id,
            'sub_category' => (string) ($user->business_sub_category ?? ''),
            'circle_id' => $primaryCircle?->id,
            'circle_name' => $primaryCircle?->name ?? '',
            'circles' => $circlesList,
            'status' => (string) ($user->status ?? ($user->is_active ? 'active' : 'inactive')),
            'is_active' => (bool) ($user->is_active ?? true),
            'membership_status' => $membershipStatus,
            'membership_status_label' => $membershipLabel,
            'is_pro' => $isPaidTier,
            'is_free_trial' => $isFreeTrial,
            'is_free' => $isFree,
            'is_sponsored' => $isSponsored,
            'membership_starts_at' => $user->membership_starts_at?->toISOString(),
            'membership_ends_at' => $endsAt ? Carbon::parse($endsAt)->toISOString() : null,
            'membership_expiry' => $endsAt ? Carbon::parse($endsAt)->toISOString() : null,
            'expiry_days' => $expiryDays,
            'is_expired' => $isExpired,
            'coins_balance' => (int) ($user->coins_balance ?? 0),
            'life_impacted_count' => (int) ($user->life_impacted_count ?? 0),
            'impact_badges_count' => (int) ($user->milestone_badges_count ?? $user->badges_count ?? 0),
            'members_introduced_count' => (int) ($user->members_introduced_count ?? 0),
            'roles' => $user->roles?->pluck('key')->all() ?? [],
            'created_at' => $user->created_at?->toISOString(),
            'last_login_at' => $user->last_login_at?->toISOString(),
        ];
    }

    /**
     * Format a paginator with canonical transformed peers.
     */
    public function transformPaginated(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => collect($paginator->items())->map(fn (User $user) => $this->transformPeer($user))->all(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}
