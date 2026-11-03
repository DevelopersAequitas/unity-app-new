<?php

declare(strict_types=1);

namespace App\Services\Recommendation;

use App\Models\City;
use App\Models\Connection;
use App\Models\User;
use App\Models\UserFollow;
use App\Services\MutualConnectionService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MemberMatchingService
{
    private const MAX_CATEGORY_SCORE = 25;

    private const MAX_INDUSTRY_SKILLS_SCORE = 20;

    private const MAX_LOCATION_SCORE = 20;

    private const MAX_MUTUAL_CONNECTIONS_SCORE = 15;

    private const MAX_SYNERGY_SCORE = 10;

    private const MAX_INTERESTS_SCORE = 10;

    public function __construct(
        protected ?MutualConnectionService $mutualConnectionService = null
    ) {
        $this->mutualConnectionService = $mutualConnectionService ?? app(MutualConnectionService::class);
    }

    /**
     * Rank candidate members for the authenticated user without pagination.
     *
     * @return Collection<int, User>
     */
    public function rank(User $authUser, Builder $query): Collection
    {
        $authUserId = (string) $authUser->id;
        $authConnectionIds = $this->mutualConnectionService->getAcceptedConnectionIds($authUserId);

        /** @var Collection<int, User> $candidates */
        $candidates = $query->get();

        if ($candidates->isEmpty()) {
            return collect();
        }

        // Bulk load connections for all candidates that intersect with auth user's connections
        $candidateIds = $candidates->pluck('id')->map(fn ($id) => (string) $id)->all();
        $candidateConnectionMap = $this->bulkGetAcceptedConnectionsMap($candidateIds, $authConnectionIds);

        // Bulk load direct connections between auth user and candidates
        $directConnections = collect();
        if (Schema::hasTable('connections')) {
            $directConnections = Connection::query()
                ->where(function ($q) use ($authUserId): void {
                    $q->where('requester_id', $authUserId)
                        ->orWhere('addressee_id', $authUserId);
                })
                ->get()
                ->keyBy(function (Connection $connection) use ($authUserId): string {
                    return (string) ((string) $connection->requester_id === $authUserId
                        ? $connection->addressee_id
                        : $connection->requester_id);
                });
        }

        // Bulk load followed users
        $followedUserIds = [];
        if (Schema::hasTable('user_follows')) {
            $followedUserIds = UserFollow::query()
                ->where('follower_id', $authUserId)
                ->whereIn('status', ['accepted', 'pending'])
                ->pluck('following_id')
                ->map(fn ($id): string => (string) $id)
                ->all();
        }

        // Find max life impacted count for normalization
        $maxLifeImpact = max(1, (int) $candidates->max('life_impacted_count'));

        // Precompute auth user attributes once to avoid recalculating in candidate loops
        $precomputedAuthData = $this->precomputeAuthData($authUser);

        $bookmarks = (array) ($authUser->bookmarks ?? []);
        $bookmarkSet = ! empty($bookmarks) ? array_flip($bookmarks) : [];

        $scoredCandidates = $candidates->map(function (User $candidate) use (
            $authUser,
            $authUserId,
            $authConnectionIds,
            $candidateConnectionMap,
            $directConnections,
            $followedUserIds,
            $maxLifeImpact,
            $precomputedAuthData,
            $bookmarkSet
        ): User {
            $candidateId = (string) $candidate->id;
            $candidateConnectionIds = $candidateConnectionMap[$candidateId] ?? [];

            $candidate->setAttribute('is_bookmark', isset($bookmarkSet[$candidateId]));

            $isFollowing = in_array($candidateId, $followedUserIds, true);
            $candidate->setAttribute('is_following', $isFollowing);

            $isPro = $this->determineIsPro($candidate);
            $candidate->setAttribute('is_pro', $isPro);

            $connection = $directConnections->get($candidateId);
            $isConnected = false;
            $isRequested = false;
            $connectionStatus = null;
            $canSendConnectionRequest = true;

            if ($connection) {
                $isConnected = (bool) $connection->is_approved;
                $isRequested = ! $connection->is_approved && (string) $connection->requester_id === $authUserId;
                $connectionStatus = $isConnected
                    ? 'connected'
                    : ($isRequested ? 'pending_sent' : 'pending_received');
                $canSendConnectionRequest = false;
            }

            $candidate->setAttribute('is_connected', $isConnected);
            $candidate->setAttribute('connection_status', $connectionStatus);
            $candidate->setAttribute('is_requested', $isRequested);
            $candidate->setAttribute('can_send_connection_request', $canSendConnectionRequest);

            $tier = $this->determineTier($isPro, $isConnected, $isRequested, $connectionStatus);
            $candidate->setAttribute('priority_tier', $tier);

            $scoreDetails = $this->calculateMatchScore(
                $authUser,
                $candidate,
                $authConnectionIds,
                $candidateConnectionIds,
                $precomputedAuthData
            );
            $matchPercentage = $scoreDetails['match_percentage'];

            // Life Impact Normalization (0 - 100 scale)
            $lifeImpact = max(0, (int) ($candidate->life_impacted_count ?? 0));
            $normalizedLifeImpact = min(100, ($lifeImpact / $maxLifeImpact) * 100);

            // Final Ranking Score: 70% Match Score + 30% Life Impact Score
            $rankScore = ($matchPercentage * 0.70) + ($normalizedLifeImpact * 0.30);

            $candidate->setAttribute('match_percentage', $matchPercentage);
            $candidate->setAttribute('match_breakdown', $scoreDetails['breakdown']);
            $candidate->setAttribute('recommendation_rank_score', $rankScore);

            return $candidate;
        });

        // Primary: priority_tier ascending (Tier 1 -> Tier 2 -> Tier 3 -> Tier 4 -> Tier 5 -> Tier 6)
        // Secondary: recommendation_rank_score descending
        // Tertiary: life_impacted_count descending
        // Quaternary: created_at descending
        return $scoredCandidates->sort(function (User $a, User $b) {
            $tierA = (int) ($a->getAttribute('priority_tier') ?? 1);
            $tierB = (int) ($b->getAttribute('priority_tier') ?? 1);

            if ($tierA !== $tierB) {
                return $tierA <=> $tierB;
            }

            $rankA = (float) $a->getAttribute('recommendation_rank_score');
            $rankB = (float) $b->getAttribute('recommendation_rank_score');

            if ($rankA !== $rankB) {
                return $rankB <=> $rankA;
            }

            $impactA = (int) ($a->life_impacted_count ?? 0);
            $impactB = (int) ($b->life_impacted_count ?? 0);

            if ($impactA !== $impactB) {
                return $impactB <=> $impactA;
            }

            return $b->created_at <=> $a->created_at;
        })->values();
    }

    /**
     * Determine if a candidate is a Pro/Paid member.
     */
    public function determineIsPro(User $user): bool
    {
        $rawVerified = $user->is_verified ?? null;
        if ($rawVerified !== null && (bool) $rawVerified) {
            return true;
        }

        if (method_exists($user, 'isPaidMember')) {
            return (bool) $user->isPaidMember();
        }

        $status = strtolower(trim((string) ($user->effective_membership_status ?? $user->membership_status ?? '')));

        return $status !== '' && ! in_array($status, ['free_peer', 'free_trial_peer', 'visitor', 'suspended', 'free peer', 'free'], true);
    }

    /**
     * Determine sorting tier:
     * Tier 1: Pro & Not Connected (can connect)
     * Tier 2: Pro & Requested (pending)
     * Tier 3: Pro & Connected
     * Tier 4: Non-Pro & Not Connected
     * Tier 5: Non-Pro & Requested
     * Tier 6: Non-Pro & Connected
     */
    public function determineTier(bool $isPro, bool $isConnected, bool $isRequested, ?string $connectionStatus): int
    {
        if ($isPro) {
            if (! $isConnected && ! $isRequested && $connectionStatus === null) {
                return 1;
            }
            if (! $isConnected && ($isRequested || in_array($connectionStatus, ['pending_sent', 'pending_received'], true))) {
                return 2;
            }
            if ($isConnected) {
                return 3;
            }

            return 1;
        }

        if (! $isConnected && ! $isRequested && $connectionStatus === null) {
            return 4;
        }
        if (! $isConnected && ($isRequested || in_array($connectionStatus, ['pending_sent', 'pending_received'], true))) {
            return 5;
        }
        if ($isConnected) {
            return 6;
        }

        return 4;
    }

    /**
     * Rank and paginate candidate members for the authenticated user.
     */
    public function rankAndPaginate(User $authUser, Builder $query, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        $sorted = $this->rank($authUser, $query);

        if ($sorted->isEmpty()) {
            return new Paginator([], 0, $perPage, $page, [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]);
        }

        $total = $sorted->count();
        $offset = max(0, ($page - 1) * $perPage);
        $pageItems = $sorted->slice($offset, $perPage)->values();

        return new Paginator($pageItems, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }

    /**
     * Precompute scalar/list attributes for the auth user to avoid redundant calls in candidate scoring loops.
     *
     * @return array<string, mixed>
     */
    public function precomputeAuthData(User $authUser): array
    {
        $authCity = $this->resolveCityName($authUser);

        return [
            'cat' => $this->extractScalarAttribute($authUser, 'business_category_id'),
            'main_cat' => $this->extractScalarAttribute($authUser, 'main_business_category_id'),
            'target_cats' => $this->normalizeList($this->extractRawAttribute($authUser, 'target_business_categories')),
            'sub_cat' => trim((string) ($this->extractScalarAttribute($authUser, 'business_sub_category') ?? '')),
            'industries' => $this->normalizeList($this->extractRawAttribute($authUser, 'industries_of_interest')),
            'tags' => $this->normalizeList($this->extractRawAttribute($authUser, 'industry_tags')),
            'skills' => $this->normalizeList($this->extractRawAttribute($authUser, 'skills')),
            'business_type' => trim((string) ($this->extractScalarAttribute($authUser, 'business_type') ?? '')),
            'city_id' => $this->resolveCityId($authUser),
            'city_name' => $authCity,
            'clean_city' => $authCity ? $this->cleanCityName($authCity) : '',
            'biz_city' => trim((string) ($this->extractScalarAttribute($authUser, 'business_city') ?? '')),
            'res_city' => trim((string) ($this->extractScalarAttribute($authUser, 'city_of_residence') ?? '')),
            'regions' => $this->normalizeList($this->extractRawAttribute($authUser, 'target_regions')),
            'can_help' => $this->normalizeList($this->extractRawAttribute($authUser, 'i_can_help_with')),
            'looking_for' => $this->normalizeList($this->extractRawAttribute($authUser, 'i_am_looking_for')),
            'superpower' => trim((string) ($this->extractScalarAttribute($authUser, 'superpower') ?? '')),
            'interests' => array_values(array_unique(array_merge(
                $this->normalizeList($this->extractRawAttribute($authUser, 'interests')),
                $this->normalizeList($this->extractRawAttribute($authUser, 'hobbies_interests'))
            ))),
            'goals' => $this->normalizeList($this->extractRawAttribute($authUser, 'collaboration_goals')),
        ];
    }

    /**
     * Calculate multi-factor match score between auth user and candidate member.
     *
     * @param  array<int, string>  $authConnectionIds
     * @param  array<int, string>  $candidateConnectionIds
     * @param  array<string, mixed>|null  $precomputedAuthData
     * @return array{match_percentage: int, breakdown: array<string, int>}
     */
    public function calculateMatchScore(
        User $authUser,
        User $candidate,
        array $authConnectionIds = [],
        array $candidateConnectionIds = [],
        ?array $precomputedAuthData = null
    ): array {
        if ((string) $authUser->id === (string) $candidate->id) {
            return [
                'match_percentage' => 100,
                'breakdown' => [
                    'category_score' => self::MAX_CATEGORY_SCORE,
                    'industry_skills_score' => self::MAX_INDUSTRY_SKILLS_SCORE,
                    'location_score' => self::MAX_LOCATION_SCORE,
                    'mutual_connections_score' => self::MAX_MUTUAL_CONNECTIONS_SCORE,
                    'synergy_score' => self::MAX_SYNERGY_SCORE,
                    'interests_score' => self::MAX_INTERESTS_SCORE,
                ],
            ];
        }

        $authData = $precomputedAuthData ?? $this->precomputeAuthData($authUser);

        // 1. Business Category Match (Max 25%)
        $categoryScore = $this->calculateCategoryScore($authUser, $candidate, $authData);

        // 2. Industry & Skills Match (Max 20%)
        $industrySkillsScore = $this->calculateIndustrySkillsScore($authUser, $candidate, $authData);

        // 3. Location / City Match (Max 20%)
        $locationScore = $this->calculateLocationScore($authUser, $candidate, $authData);

        // 4. 2nd-Degree Mutual Connections (Max 15%)
        $mutualConnectionsScore = $this->calculateMutualConnectionsScore(
            (string) $authUser->id,
            (string) $candidate->id,
            $authConnectionIds,
            $candidateConnectionIds
        );

        // 5. Synergy (Give & Take Match) (Max 10%)
        $synergyScore = $this->calculateSynergyScore($authUser, $candidate, $authData);

        // 6. Interests & Collaboration Goals (Max 10%)
        $interestsScore = $this->calculateInterestsScore($authUser, $candidate, $authData);

        $totalScore = $categoryScore + $industrySkillsScore + $locationScore + $mutualConnectionsScore + $synergyScore + $interestsScore;
        $matchPercentage = (int) round(min(100, max(0, $totalScore)));

        return [
            'match_percentage' => $matchPercentage,
            'breakdown' => [
                'category_score' => $categoryScore,
                'industry_skills_score' => $industrySkillsScore,
                'location_score' => $locationScore,
                'mutual_connections_score' => $mutualConnectionsScore,
                'synergy_score' => $synergyScore,
                'interests_score' => $interestsScore,
            ],
        ];
    }

    /**
     * 1. Business Category & Sub-Category Match (Max 25%)
     *
     * @param  array<string, mixed>|null  $authData
     */
    protected function calculateCategoryScore(User $authUser, User $candidate, ?array $authData = null): int
    {
        $score = 0;

        $authCat = $authData !== null ? ($authData['cat'] ?? null) : $this->extractScalarAttribute($authUser, 'business_category_id');
        $candidateCat = $this->extractScalarAttribute($candidate, 'business_category_id');

        $authMainCat = $authData !== null ? ($authData['main_cat'] ?? null) : $this->extractScalarAttribute($authUser, 'main_business_category_id');
        $candidateMainCat = $this->extractScalarAttribute($candidate, 'main_business_category_id');

        // Direct category match
        if ($authCat && $candidateCat && (string) $authCat === (string) $candidateCat) {
            $score += 20;
        } elseif ($authMainCat && $candidateMainCat && (string) $authMainCat === (string) $candidateMainCat) {
            $score += 15;
        }

        // Target Business Categories match
        $authTargetCats = $authData !== null ? ($authData['target_cats'] ?? []) : $this->normalizeList($this->extractRawAttribute($authUser, 'target_business_categories'));
        $candidateTargetCats = $this->normalizeList($this->extractRawAttribute($candidate, 'target_business_categories'));

        if (! empty($authTargetCats) && $candidateCat && in_array((string) $candidateCat, $authTargetCats, true)) {
            $score += 8;
        }
        if (! empty($candidateTargetCats) && $authCat && in_array((string) $authCat, $candidateTargetCats, true)) {
            $score += 8;
        }

        // Sub-category / Level4 Category match
        $authSub = $authData !== null ? ($authData['sub_cat'] ?? '') : trim((string) ($this->extractScalarAttribute($authUser, 'business_sub_category') ?? ''));
        $candSub = trim((string) ($this->extractScalarAttribute($candidate, 'business_sub_category') ?? ''));
        if ($authSub !== '' && $candSub !== '' && strcasecmp($authSub, $candSub) === 0) {
            $score += 5;
        }

        return min(self::MAX_CATEGORY_SCORE, $score);
    }

    /**
     * 2. Industry & Skills Match (Max 20%)
     *
     * @param  array<string, mixed>|null  $authData
     */
    protected function calculateIndustrySkillsScore(User $authUser, User $candidate, ?array $authData = null): int
    {
        $score = 0;

        // Industries of interest overlap
        $authIndustries = $authData !== null ? ($authData['industries'] ?? []) : $this->normalizeList($this->extractRawAttribute($authUser, 'industries_of_interest'));
        $candidateIndustries = $this->normalizeList($this->extractRawAttribute($candidate, 'industries_of_interest'));
        $commonIndustries = array_intersect($authIndustries, $candidateIndustries);
        if (! empty($commonIndustries)) {
            $score += min(10, count($commonIndustries) * 5);
        }

        // Industry tags overlap
        $authTags = $authData !== null ? ($authData['tags'] ?? []) : $this->normalizeList($this->extractRawAttribute($authUser, 'industry_tags'));
        $candidateTags = $this->normalizeList($this->extractRawAttribute($candidate, 'industry_tags'));
        $commonTags = array_intersect($authTags, $candidateTags);
        if (! empty($commonTags)) {
            $score += min(8, count($commonTags) * 4);
        }

        // Skills overlap
        $authSkills = $authData !== null ? ($authData['skills'] ?? []) : $this->normalizeList($this->extractRawAttribute($authUser, 'skills'));
        $candidateSkills = $this->normalizeList($this->extractRawAttribute($candidate, 'skills'));
        $commonSkills = array_intersect($authSkills, $candidateSkills);
        if (! empty($commonSkills)) {
            $score += min(8, count($commonSkills) * 4);
        }

        // Business Type match
        $authType = $authData !== null ? ($authData['business_type'] ?? '') : trim((string) ($this->extractScalarAttribute($authUser, 'business_type') ?? ''));
        $candType = trim((string) ($this->extractScalarAttribute($candidate, 'business_type') ?? ''));
        if ($authType !== '' && $candType !== '' && strcasecmp($authType, $candType) === 0) {
            $score += 4;
        }

        return min(self::MAX_INDUSTRY_SKILLS_SCORE, $score);
    }

    /**
     * 3. Location / City Match (Max 20%)
     *
     * @param  array<string, mixed>|null  $authData
     */
    protected function calculateLocationScore(User $authUser, User $candidate, ?array $authData = null): int
    {
        $score = 0;

        $authCityId = $authData !== null ? ($authData['city_id'] ?? null) : $this->resolveCityId($authUser);
        $candidateCityId = $this->resolveCityId($candidate);

        $authCity = $authData !== null ? ($authData['city_name'] ?? null) : $this->resolveCityName($authUser);
        $candCity = $this->resolveCityName($candidate);

        // Direct City Match
        if ($authCityId && $candidateCityId && (string) $authCityId === (string) $candidateCityId) {
            return 20;
        }

        if ($authCity && $candCity) {
            $cleanAuth = $authData !== null ? ($authData['clean_city'] ?? '') : $this->cleanCityName($authCity);
            $cleanCand = $this->cleanCityName($candCity);

            if ($cleanAuth !== '' && $cleanCand !== '') {
                if (strcasecmp($cleanAuth, $cleanCand) === 0) {
                    return 20;
                }
                if (stripos($cleanAuth, $cleanCand) !== false || stripos($cleanCand, $cleanAuth) !== false) {
                    return 20;
                }
            }
        }

        // Business City / Residence City Match
        $authBizCity = $authData !== null ? ($authData['biz_city'] ?? '') : trim((string) ($this->extractScalarAttribute($authUser, 'business_city') ?? ''));
        $candBizCity = trim((string) ($this->extractScalarAttribute($candidate, 'business_city') ?? ''));
        $authResCity = $authData !== null ? ($authData['res_city'] ?? '') : trim((string) ($this->extractScalarAttribute($authUser, 'city_of_residence') ?? ''));
        $candResCity = trim((string) ($this->extractScalarAttribute($candidate, 'city_of_residence') ?? ''));

        if ($authBizCity !== '' && ($authBizCity === $candBizCity || ($candCity && strcasecmp($authBizCity, $candCity) === 0))) {
            $score += 10;
        } elseif ($authResCity !== '' && ($authResCity === $candResCity || ($candCity && strcasecmp($authResCity, $candCity) === 0))) {
            $score += 10;
        }

        // Target regions overlap
        $authRegions = $authData !== null ? ($authData['regions'] ?? []) : $this->normalizeList($this->extractRawAttribute($authUser, 'target_regions'));
        $candidateRegions = $this->normalizeList($this->extractRawAttribute($candidate, 'target_regions'));
        $commonRegions = array_intersect($authRegions, $candidateRegions);
        if (! empty($commonRegions)) {
            $score += min(6, count($commonRegions) * 3);
        }

        return min(self::MAX_LOCATION_SCORE, $score);
    }

    /**
     * 4. 2nd-Degree Mutual Connections (Max 15%)
     */
    protected function calculateMutualConnectionsScore(
        string $authUserId,
        string $candidateUserId,
        array $authConnectionIds,
        array $candidateConnectionIds
    ): int {
        if (empty($authConnectionIds) || empty($candidateConnectionIds)) {
            return 0;
        }

        $commonIds = array_diff(
            array_intersect($authConnectionIds, $candidateConnectionIds),
            [$authUserId, $candidateUserId]
        );

        $mutualCount = count($commonIds);

        if ($mutualCount <= 0) {
            return 0;
        }

        // 1 mutual = 6 pts, 2 mutuals = 10 pts, 3+ mutuals = 15 pts
        if ($mutualCount === 1) {
            return 6;
        }
        if ($mutualCount === 2) {
            return 10;
        }

        return min(self::MAX_MUTUAL_CONNECTIONS_SCORE, 10 + ($mutualCount - 2) * 5);
    }

    /**
     * 5. Synergy (Give & Take Match) (Max 10%)
     *
     * @param  array<string, mixed>|null  $authData
     */
    protected function calculateSynergyScore(User $authUser, User $candidate, ?array $authData = null): int
    {
        $score = 0;

        $authCanHelp = $authData !== null ? ($authData['can_help'] ?? []) : $this->normalizeList($this->extractRawAttribute($authUser, 'i_can_help_with'));
        $authLookingFor = $authData !== null ? ($authData['looking_for'] ?? []) : $this->normalizeList($this->extractRawAttribute($authUser, 'i_am_looking_for'));
        $candCanHelp = $this->normalizeList($this->extractRawAttribute($candidate, 'i_can_help_with'));
        $candLookingFor = $this->normalizeList($this->extractRawAttribute($candidate, 'i_am_looking_for'));

        $authSuperpower = $authData !== null ? ($authData['superpower'] ?? '') : trim((string) ($this->extractScalarAttribute($authUser, 'superpower') ?? ''));
        $candSuperpower = trim((string) ($this->extractScalarAttribute($candidate, 'superpower') ?? ''));

        // What Auth can help with matches what Candidate is looking for
        $matchA = array_intersect($authCanHelp, $candLookingFor);
        if (! empty($matchA)) {
            $score += 5;
        } elseif ($authSuperpower !== '' && ! empty($candLookingFor)) {
            foreach ($candLookingFor as $item) {
                if (stripos($authSuperpower, $item) !== false || stripos($item, $authSuperpower) !== false) {
                    $score += 4;
                    break;
                }
            }
        }

        // What Candidate can help with matches what Auth is looking for
        $matchB = array_intersect($candCanHelp, $authLookingFor);
        if (! empty($matchB)) {
            $score += 5;
        } elseif ($candSuperpower !== '' && ! empty($authLookingFor)) {
            foreach ($authLookingFor as $item) {
                if (stripos($candSuperpower, $item) !== false || stripos($item, $candSuperpower) !== false) {
                    $score += 4;
                    break;
                }
            }
        }

        return min(self::MAX_SYNERGY_SCORE, $score);
    }

    /**
     * 6. Interests & Collaboration Goals (Max 10%)
     *
     * @param  array<string, mixed>|null  $authData
     */
    protected function calculateInterestsScore(User $authUser, User $candidate, ?array $authData = null): int
    {
        $score = 0;

        $authInterests = $authData !== null ? ($authData['interests'] ?? []) : array_merge(
            $this->normalizeList($this->extractRawAttribute($authUser, 'interests')),
            $this->normalizeList($this->extractRawAttribute($authUser, 'hobbies_interests'))
        );
        $candInterests = array_merge(
            $this->normalizeList($this->extractRawAttribute($candidate, 'interests')),
            $this->normalizeList($this->extractRawAttribute($candidate, 'hobbies_interests'))
        );

        $commonInterests = array_intersect($authInterests, $candInterests);
        if (! empty($commonInterests)) {
            $score += min(5, count($commonInterests) * 2.5);
        }

        $authGoals = $authData !== null ? ($authData['goals'] ?? []) : $this->normalizeList($this->extractRawAttribute($authUser, 'collaboration_goals'));
        $candGoals = $this->normalizeList($this->extractRawAttribute($candidate, 'collaboration_goals'));

        $commonGoals = array_intersect($authGoals, $candGoals);
        if (! empty($commonGoals)) {
            $score += min(5, count($commonGoals) * 2.5);
        }

        return (int) min(self::MAX_INTERESTS_SCORE, round($score));
    }

    /**
     * Resolve clean city name from user instance.
     */
    public function resolveCityName(User $user): ?string
    {
        if ($user->relationLoaded('city')) {
            $cityRel = $user->getRelation('city');
            if ($cityRel instanceof City && ! empty($cityRel->name)) {
                return (string) $cityRel->name;
            }
        }

        $raw = $this->extractScalarAttribute($user, 'city');
        if (! empty($raw)) {
            return (string) $raw;
        }

        $res = $this->extractScalarAttribute($user, 'city_of_residence');
        if (! empty($res)) {
            return (string) $res;
        }

        $biz = $this->extractScalarAttribute($user, 'business_city');
        if (! empty($biz)) {
            return (string) $biz;
        }

        return null;
    }

    /**
     * Resolve city ID from user instance.
     */
    public function resolveCityId(User $user): ?string
    {
        $cityId = $this->extractScalarAttribute($user, 'city_id');
        if (! empty($cityId)) {
            return (string) $cityId;
        }

        if ($user->relationLoaded('city')) {
            $cityRel = $user->getRelation('city');
            if ($cityRel instanceof City && ! empty($cityRel->id)) {
                return (string) $cityRel->id;
            }
        }

        return null;
    }

    /**
     * Clean city name (e.g. "Gandhinagar, IN" -> "Gandhinagar").
     */
    protected function cleanCityName(string $city): string
    {
        $parts = explode(',', $city);

        return trim((string) ($parts[0] ?? $city));
    }

    /**
     * Extract scalar attribute safely avoiding relationship calls.
     */
    protected function extractScalarAttribute(User $user, string $key): mixed
    {
        $attrs = $user->getAttributes();
        if (array_key_exists($key, $attrs)) {
            $val = $attrs[$key];
            if (is_scalar($val)) {
                return $val;
            }
        }

        $val = $user->getAttribute($key);
        if (is_scalar($val)) {
            return $val;
        }

        return null;
    }

    /**
     * Extract raw attribute safely (array or string).
     */
    protected function extractRawAttribute(User $user, string $key): mixed
    {
        $attrs = $user->getAttributes();
        if (array_key_exists($key, $attrs)) {
            return $attrs[$key];
        }

        return $user->getAttribute($key);
    }

    /**
     * Bulk fetch accepted connections map for candidate user IDs that touch auth user's connections.
     *
     * @param  array<int, string>  $userIds
     * @param  array<int, string>  $authConnectionIds
     * @return array<string, array<int, string>>
     */
    protected function bulkGetAcceptedConnectionsMap(array $userIds, array $authConnectionIds = []): array
    {
        if (empty($userIds) || empty($authConnectionIds) || ! Schema::hasTable('connections')) {
            return [];
        }

        $records = DB::table('connections')
            ->where(function ($q): void {
                $q->where('is_approved', true)->orWhere('is_approved', 1);
            })
            ->where(function ($q) use ($authConnectionIds, $userIds): void {
                $q->where(function ($sq) use ($authConnectionIds, $userIds): void {
                    $sq->whereIn('requester_id', $authConnectionIds)
                        ->whereIn('addressee_id', $userIds);
                })->orWhere(function ($sq) use ($authConnectionIds, $userIds): void {
                    $sq->whereIn('addressee_id', $authConnectionIds)
                        ->whereIn('requester_id', $userIds);
                });
            })
            ->select(['requester_id', 'addressee_id'])
            ->get();

        $map = [];
        foreach ($userIds as $id) {
            $map[$id] = [];
        }

        $authSet = array_flip($authConnectionIds);

        foreach ($records as $record) {
            $req = (string) $record->requester_id;
            $addr = (string) $record->addressee_id;

            if (isset($authSet[$req]) && isset($map[$addr])) {
                $map[$addr][] = $req;
            }
            if (isset($authSet[$addr]) && isset($map[$req])) {
                $map[$req][] = $addr;
            }
        }

        foreach ($map as $id => $connList) {
            $map[$id] = array_values(array_unique($connList));
        }

        return $map;
    }

    /**
     * Normalize list input (array, JSON string, comma-separated string) to clean lowercase strings.
     *
     * @return array<int, string>
     */
    protected function normalizeList(mixed $value): array
    {
        if (is_null($value)) {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = array_map('trim', explode(',', $value));
            }
        }

        if (! is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            if (is_scalar($item)) {
                $str = trim((string) $item);
                if ($str !== '') {
                    $normalized[] = mb_strtolower($str);
                }
            }
        }

        return array_values(array_unique($normalized));
    }
}
