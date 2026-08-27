<?php

declare(strict_types=1);

namespace App\Services\Recommendation;

use App\Models\City;
use App\Models\User;
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
     * Rank and paginate candidate members for the authenticated user.
     */
    public function rankAndPaginate(User $authUser, Builder $query, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        $authUserId = (string) $authUser->id;
        $authConnectionIds = $this->mutualConnectionService->getAcceptedConnectionIds($authUserId);

        /** @var Collection<int, User> $candidates */
        $candidates = $query->get();

        if ($candidates->isEmpty()) {
            return new Paginator([], 0, $perPage, $page, [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]);
        }

        // Bulk load connections for all candidates to optimize graph calculations
        $candidateIds = $candidates->pluck('id')->map(fn ($id) => (string) $id)->all();
        $candidateConnectionMap = $this->bulkGetAcceptedConnectionsMap($candidateIds);

        // Find max life impacted count for normalization
        $maxLifeImpact = max(1, (int) $candidates->max('life_impacted_count'));

        $scoredCandidates = $candidates->map(function (User $candidate) use ($authUser, $authConnectionIds, $candidateConnectionMap, $maxLifeImpact): User {
            $candidateId = (string) $candidate->id;
            $candidateConnectionIds = $candidateConnectionMap[$candidateId] ?? [];

            $scoreDetails = $this->calculateMatchScore($authUser, $candidate, $authConnectionIds, $candidateConnectionIds);
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

        // Sort by rank score descending, then life_impacted_count descending, then created_at descending
        $sorted = $scoredCandidates->sort(function (User $a, User $b) {
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

        $total = $sorted->count();
        $offset = max(0, ($page - 1) * $perPage);
        $pageItems = $sorted->slice($offset, $perPage)->values();

        return new Paginator($pageItems, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }

    /**
     * Calculate multi-factor match score between auth user and candidate member.
     *
     * @param  array<int, string>  $authConnectionIds
     * @param  array<int, string>  $candidateConnectionIds
     * @return array{match_percentage: int, breakdown: array<string, int>}
     */
    public function calculateMatchScore(
        User $authUser,
        User $candidate,
        array $authConnectionIds = [],
        array $candidateConnectionIds = []
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

        // 1. Business Category Match (Max 25%)
        $categoryScore = $this->calculateCategoryScore($authUser, $candidate);

        // 2. Industry & Skills Match (Max 20%)
        $industrySkillsScore = $this->calculateIndustrySkillsScore($authUser, $candidate);

        // 3. Location / City Match (Max 20%)
        $locationScore = $this->calculateLocationScore($authUser, $candidate);

        // 4. 2nd-Degree Mutual Connections (Max 15%)
        $mutualConnectionsScore = $this->calculateMutualConnectionsScore(
            (string) $authUser->id,
            (string) $candidate->id,
            $authConnectionIds,
            $candidateConnectionIds
        );

        // 5. Synergy (Give & Take Match) (Max 10%)
        $synergyScore = $this->calculateSynergyScore($authUser, $candidate);

        // 6. Interests & Collaboration Goals (Max 10%)
        $interestsScore = $this->calculateInterestsScore($authUser, $candidate);

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
     */
    protected function calculateCategoryScore(User $authUser, User $candidate): int
    {
        $score = 0;

        $authCat = $this->extractScalarAttribute($authUser, 'business_category_id');
        $candidateCat = $this->extractScalarAttribute($candidate, 'business_category_id');

        $authMainCat = $this->extractScalarAttribute($authUser, 'main_business_category_id');
        $candidateMainCat = $this->extractScalarAttribute($candidate, 'main_business_category_id');

        // Direct category match
        if ($authCat && $candidateCat && (string) $authCat === (string) $candidateCat) {
            $score += 20;
        } elseif ($authMainCat && $candidateMainCat && (string) $authMainCat === (string) $candidateMainCat) {
            $score += 15;
        }

        // Target Business Categories match
        $authTargetCats = $this->normalizeList($this->extractRawAttribute($authUser, 'target_business_categories'));
        $candidateTargetCats = $this->normalizeList($this->extractRawAttribute($candidate, 'target_business_categories'));

        if (! empty($authTargetCats) && $candidateCat && in_array((string) $candidateCat, $authTargetCats, true)) {
            $score += 8;
        }
        if (! empty($candidateTargetCats) && $authCat && in_array((string) $authCat, $candidateTargetCats, true)) {
            $score += 8;
        }

        // Sub-category / Level4 Category match
        $authSub = trim((string) ($this->extractScalarAttribute($authUser, 'business_sub_category') ?? ''));
        $candSub = trim((string) ($this->extractScalarAttribute($candidate, 'business_sub_category') ?? ''));
        if ($authSub !== '' && $candSub !== '' && strcasecmp($authSub, $candSub) === 0) {
            $score += 5;
        }

        return min(self::MAX_CATEGORY_SCORE, $score);
    }

    /**
     * 2. Industry & Skills Match (Max 20%)
     */
    protected function calculateIndustrySkillsScore(User $authUser, User $candidate): int
    {
        $score = 0;

        // Industries of interest overlap
        $authIndustries = $this->normalizeList($this->extractRawAttribute($authUser, 'industries_of_interest'));
        $candidateIndustries = $this->normalizeList($this->extractRawAttribute($candidate, 'industries_of_interest'));
        $commonIndustries = array_intersect($authIndustries, $candidateIndustries);
        if (! empty($commonIndustries)) {
            $score += min(10, count($commonIndustries) * 5);
        }

        // Industry tags overlap
        $authTags = $this->normalizeList($this->extractRawAttribute($authUser, 'industry_tags'));
        $candidateTags = $this->normalizeList($this->extractRawAttribute($candidate, 'industry_tags'));
        $commonTags = array_intersect($authTags, $candidateTags);
        if (! empty($commonTags)) {
            $score += min(8, count($commonTags) * 4);
        }

        // Skills overlap
        $authSkills = $this->normalizeList($this->extractRawAttribute($authUser, 'skills'));
        $candidateSkills = $this->normalizeList($this->extractRawAttribute($candidate, 'skills'));
        $commonSkills = array_intersect($authSkills, $candidateSkills);
        if (! empty($commonSkills)) {
            $score += min(8, count($commonSkills) * 4);
        }

        // Business Type match
        $authType = trim((string) ($this->extractScalarAttribute($authUser, 'business_type') ?? ''));
        $candType = trim((string) ($this->extractScalarAttribute($candidate, 'business_type') ?? ''));
        if ($authType !== '' && $candType !== '' && strcasecmp($authType, $candType) === 0) {
            $score += 4;
        }

        return min(self::MAX_INDUSTRY_SKILLS_SCORE, $score);
    }

    /**
     * 3. Location / City Match (Max 20%)
     */
    protected function calculateLocationScore(User $authUser, User $candidate): int
    {
        $score = 0;

        $authCityId = $this->resolveCityId($authUser);
        $candidateCityId = $this->resolveCityId($candidate);

        $authCity = $this->resolveCityName($authUser);
        $candCity = $this->resolveCityName($candidate);

        // Direct City Match
        if ($authCityId && $candidateCityId && (string) $authCityId === (string) $candidateCityId) {
            return 20;
        }

        if ($authCity && $candCity) {
            $cleanAuth = $this->cleanCityName($authCity);
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
        $authBizCity = trim((string) ($this->extractScalarAttribute($authUser, 'business_city') ?? ''));
        $candBizCity = trim((string) ($this->extractScalarAttribute($candidate, 'business_city') ?? ''));
        $authResCity = trim((string) ($this->extractScalarAttribute($authUser, 'city_of_residence') ?? ''));
        $candResCity = trim((string) ($this->extractScalarAttribute($candidate, 'city_of_residence') ?? ''));

        if ($authBizCity !== '' && ($authBizCity === $candBizCity || ($candCity && strcasecmp($authBizCity, $candCity) === 0))) {
            $score += 10;
        } elseif ($authResCity !== '' && ($authResCity === $candResCity || ($candCity && strcasecmp($authResCity, $candCity) === 0))) {
            $score += 10;
        }

        // Target regions overlap
        $authRegions = $this->normalizeList($this->extractRawAttribute($authUser, 'target_regions'));
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
     */
    protected function calculateSynergyScore(User $authUser, User $candidate): int
    {
        $score = 0;

        $authCanHelp = $this->normalizeList($this->extractRawAttribute($authUser, 'i_can_help_with'));
        $authLookingFor = $this->normalizeList($this->extractRawAttribute($authUser, 'i_am_looking_for'));
        $candCanHelp = $this->normalizeList($this->extractRawAttribute($candidate, 'i_can_help_with'));
        $candLookingFor = $this->normalizeList($this->extractRawAttribute($candidate, 'i_am_looking_for'));

        $authSuperpower = trim((string) ($this->extractScalarAttribute($authUser, 'superpower') ?? ''));
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
     */
    protected function calculateInterestsScore(User $authUser, User $candidate): int
    {
        $score = 0;

        $authInterests = array_merge(
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

        $authGoals = $this->normalizeList($this->extractRawAttribute($authUser, 'collaboration_goals'));
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
     * Bulk fetch accepted connections map for a list of candidate user IDs.
     *
     * @param  array<int, string>  $userIds
     * @return array<string, array<int, string>>
     */
    protected function bulkGetAcceptedConnectionsMap(array $userIds): array
    {
        if (empty($userIds) || ! Schema::hasTable('connections')) {
            return [];
        }

        $records = DB::table('connections')
            ->where(function ($q): void {
                $q->where('is_approved', true)->orWhere('is_approved', 1);
            })
            ->where(function ($q) use ($userIds): void {
                $q->whereIn('requester_id', $userIds)
                    ->orWhereIn('addressee_id', $userIds);
            })
            ->select(['requester_id', 'addressee_id'])
            ->get();

        $map = [];
        foreach ($userIds as $id) {
            $map[$id] = [];
        }

        foreach ($records as $record) {
            $req = (string) $record->requester_id;
            $addr = (string) $record->addressee_id;

            if (isset($map[$req])) {
                $map[$req][] = $addr;
            }
            if (isset($map[$addr])) {
                $map[$addr][] = $req;
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
