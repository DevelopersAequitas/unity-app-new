<?php

declare(strict_types=1);

namespace App\Services\Ask;

use App\Models\Ask\Ask;
use App\Models\Ask\AskResponse;
use App\Models\Ask\AskType;
use App\Models\BusinessDeal;
use App\Models\PostSave;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AskFlowHubService
{
    /**
     * Resolve standard user details for ask feeds and cards.
     *
     * @return array{id: string, display_name: string, avatar_url: ?string, company_name: string, city: string}
     */
    public function formatAuthor(?User $user): array
    {
        if (! $user) {
            return [
                'id' => '',
                'display_name' => 'Peer Member',
                'avatar_url' => null,
                'company_name' => '',
                'city' => '',
            ];
        }

        $displayName = $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
        if ($displayName === '') {
            $displayName = 'Peer Member';
        }

        $avatarUrl = $user->profile_photo_file_id
            ? url('/api/v1/files/'.$user->profile_photo_file_id)
            : ($user->profile_photo_url ?? null);

        $city = $user->city_of_residence ?: (is_string($user->city) ? $user->city : data_get($user, 'city.name', ''));

        return [
            'id' => (string) $user->id,
            'display_name' => $displayName,
            'avatar_url' => $avatarUrl,
            'company_name' => (string) ($user->company_name ?? ''),
            'city' => (string) $city,
        ];
    }

    /**
     * 1. Global Feed API for a specific Ask Flow (collaboration, referral, help)
     *
     * @param  array{page?: int, limit?: int, category_id?: string, search?: string}  $params
     * @return array{items: array<int, mixed>, pagination: array{current_page: int, has_more: bool, total: int}}
     */
    public function getGlobalFeed(User $user, string $flowCode, array $params = []): array
    {
        $normalizedFlow = $this->normalizeFlowCode($flowCode);
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = max(1, min(100, (int) ($params['limit'] ?? 20)));
        $categoryId = filled($params['category_id'] ?? null) ? trim((string) $params['category_id']) : null;
        $search = filled($params['search'] ?? null) ? trim((string) $params['search']) : null;

        $query = Ask::query()
            ->whereHas('flow', function (Builder $fq) use ($normalizedFlow): void {
                $fq->where('code', $normalizedFlow);
            })
            ->whereIn('status', [Ask::STATUS_PUBLISHED, 'open', 'active'])
            ->whereNotIn('status', ['fulfilled', Ask::STATUS_CLOSED, 'completed', Ask::STATUS_CANCELLED, Ask::STATUS_EXPIRED, Ask::STATUS_DRAFT])
            ->with(['user', 'type', 'flow', 'answers', 'timelineLink'])
            ->withCount('responses')
            ->orderByDesc('created_at');

        if ($categoryId !== null) {
            $query->where(function (Builder $cq) use ($categoryId): void {
                $cq->where('type_id', $categoryId)
                    ->orWhereHas('type', function (Builder $tq) use ($categoryId): void {
                        $tq->where('code', $categoryId)
                            ->orWhere('name', 'ILIKE', '%'.$categoryId.'%');
                    });
            });
        }

        if ($search !== null) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function (Builder $sq) use ($like): void {
                $sq->where('title', 'ILIKE', $like)
                    ->orWhereHas('answers', function (Builder $aq) use ($like): void {
                        $aq->where('value_text', 'ILIKE', $like);
                    })
                    ->orWhereHas('user', function (Builder $uq) use ($like): void {
                        $uq->where('display_name', 'ILIKE', $like)
                            ->orWhere('first_name', 'ILIKE', $like)
                            ->orWhere('last_name', 'ILIKE', $like)
                            ->orWhere('company_name', 'ILIKE', $like);
                    });
            });
        }

        $paginator = $query->paginate(perPage: $limit, page: $page);

        // Batch lookup bookmarks/saves for current user
        $postIds = [];
        foreach ($paginator->items() as $ask) {
            if ($ask->timelineLink?->post_id) {
                $postIds[] = $ask->timelineLink->post_id;
            }
        }
        $savedPostIds = [];
        if (! empty($postIds)) {
            $savedPostIds = PostSave::query()
                ->where('user_id', $user->id)
                ->whereIn('post_id', $postIds)
                ->pluck('post_id')
                ->flip()
                ->all();
        }

        $items = [];
        foreach ($paginator->items() as $ask) {
            /** @var Ask $ask */
            $author = $this->formatAuthor($ask->user);
            $description = $ask->answers->firstWhere('field_key', 'details')?->value_text
                ?? $ask->answers->firstWhere('field_key', 'description')?->value_text
                ?? $ask->title;

            $postId = $ask->timelineLink?->post_id;
            $isSaved = $postId ? isset($savedPostIds[$postId]) : false;

            if ($normalizedFlow === 'collaboration') {
                $items[] = [
                    'id' => (string) $ask->id,
                    'flow_code' => 'collaboration',
                    'title' => (string) $ask->title,
                    'description' => (string) $description,
                    'category' => (string) ($ask->type?->name ?? 'Distribution Partner'),
                    'status' => 'open',
                    'created_at' => $ask->created_at?->toISOString() ?? now()->toISOString(),
                    'author' => $author,
                    'responses_count' => (int) $ask->responses_count,
                    'is_saved' => $isSaved,
                ];
            } elseif ($normalizedFlow === 'referral') {
                $offeringInReturn = $ask->answers->firstWhere('field_key', 'what_i_offer')?->value_text
                    ?? $ask->answers->firstWhere('field_key', 'offering_in_return')?->value_text
                    ?? ($ask->metadata['offering_in_return'] ?? null);

                $items[] = [
                    'id' => (string) $ask->id,
                    'flow_code' => 'referral',
                    'title' => (string) $ask->title,
                    'description' => (string) $description,
                    'offering_in_return' => $offeringInReturn ? (string) $offeringInReturn : 'Direct business referrals in reciprocal networks.',
                    'status' => 'open',
                    'created_at' => $ask->created_at?->toISOString() ?? now()->toISOString(),
                    'author' => $author,
                    'responses_count' => (int) $ask->responses_count,
                ];
            } else {
                // help flow
                $items[] = [
                    'id' => (string) $ask->id,
                    'flow_code' => 'help',
                    'title' => (string) $ask->title,
                    'description' => (string) $description,
                    'status' => 'open',
                    'created_at' => $ask->created_at?->toISOString() ?? now()->toISOString(),
                    'author' => $author,
                    'responses_count' => (int) $ask->responses_count,
                ];
            }
        }

        return [
            'items' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'has_more' => $paginator->hasMorePages(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * 2. My Asks & History API for a specific Flow
     *
     * @param  array{page?: int, limit?: int}  $params
     * @return array<string, mixed>
     */
    public function getMyAsks(User $user, string $flowCode, array $params = []): array
    {
        $normalizedFlow = $this->normalizeFlowCode($flowCode);

        if ($normalizedFlow === 'referral') {
            return $this->getMyReferralAsks($user, $params);
        }

        if ($normalizedFlow === 'collaboration') {
            return $this->getMyCollaborationAsks($user, $params);
        }

        return $this->getMyHelpAsks($user, $params);
    }

    /**
     * Collaboration: My Collaborations & History
     *
     * @param  array{page?: int, limit?: int}  $params
     * @return array{items: array<int, mixed>}
     */
    protected function getMyCollaborationAsks(User $user, array $params = []): array
    {
        $asks = Ask::query()
            ->where('user_id', $user->id)
            ->whereHas('flow', fn (Builder $q) => $q->where('code', 'collaboration'))
            ->with(['responses.responder', 'responses.introducedUser'])
            ->withCount('responses')
            ->orderByDesc('created_at')
            ->get();

        $items = [];
        foreach ($asks as $ask) {
            /** @var Ask $ask */
            $acceptedResponse = $ask->responses->firstWhere('status', AskResponse::STATUS_ACCEPTED)
                ?? $ask->responses->firstWhere('status', AskResponse::STATUS_IN_PROGRESS)
                ?? $ask->responses->firstWhere('status', AskResponse::STATUS_COMPLETED);

            $acceptedPeer = null;
            if ($acceptedResponse) {
                $peerUser = $acceptedResponse->introducedUser ?? $acceptedResponse->responder;
                if ($peerUser) {
                    $acceptedPeer = [
                        'id' => (string) $peerUser->id,
                        'display_name' => $peerUser->display_name ?: trim(($peerUser->first_name ?? '').' '.($peerUser->last_name ?? '')),
                        'company_name' => (string) ($peerUser->company_name ?? ''),
                        'avatar_url' => $peerUser->profile_photo_file_id
                            ? url('/api/v1/files/'.$peerUser->profile_photo_file_id)
                            : ($peerUser->profile_photo_url ?? null),
                    ];
                }
            }

            $status = match ($ask->status) {
                Ask::STATUS_FULFILLED => 'fulfilled',
                Ask::STATUS_CLOSED => 'closed',
                Ask::STATUS_IN_PROGRESS => 'in_progress',
                default => ($acceptedPeer !== null ? 'in_progress' : 'open'),
            };

            $totalSteps = 4;
            $progressStep = match ($status) {
                'fulfilled', 'closed' => 4,
                'in_progress' => 3,
                default => ($ask->responses_count > 0 ? 2 : 1),
            };

            $items[] = [
                'id' => (string) $ask->id,
                'flow_code' => 'collaboration',
                'title' => (string) $ask->title,
                'status' => $status,
                'responses_count' => (int) $ask->responses_count,
                'accepted_peer' => $acceptedPeer,
                'progress_step' => $progressStep,
                'total_steps' => $totalSteps,
                'created_at' => $ask->created_at?->toISOString() ?? now()->toISOString(),
            ];
        }

        return [
            'items' => $items,
        ];
    }

    /**
     * Referral: My Referral Asks & Received Introductions
     *
     * @param  array{page?: int, limit?: int}  $params
     * @return array{my_asks: array<int, mixed>, received_introductions: array<int, mixed>}
     */
    protected function getMyReferralAsks(User $user, array $params = []): array
    {
        $asks = Ask::query()
            ->where('user_id', $user->id)
            ->whereHas('flow', fn (Builder $q) => $q->where('code', 'referral'))
            ->with(['responses.responder', 'responses.contact', 'responses.introducedUser'])
            ->orderByDesc('created_at')
            ->get();

        $myAsks = [];
        foreach ($asks as $ask) {
            /** @var Ask $ask */
            $responsesList = [];
            foreach ($ask->responses as $resp) {
                /** @var AskResponse $resp */
                $responder = $resp->responder;
                $contactName = $resp->contact?->full_name
                    ?? ($resp->introducedUser ? ($resp->introducedUser->display_name ?: trim(($resp->introducedUser->first_name ?? '').' '.($resp->introducedUser->last_name ?? ''))) : 'Contact Person');
                $contactDesignation = $resp->contact?->designation ?? 'Key Contact';

                $responsesList[] = [
                    'response_id' => (string) $resp->id,
                    'responder' => [
                        'id' => (string) ($responder?->id ?? ''),
                        'display_name' => $responder ? ($responder->display_name ?: trim(($responder->first_name ?? '').' '.($responder->last_name ?? ''))) : 'Peer Member',
                        'avatar_url' => $responder?->profile_photo_file_id
                            ? url('/api/v1/files/'.$responder->profile_photo_file_id)
                            : ($responder?->profile_photo_url ?? null),
                    ],
                    'contact_name' => (string) $contactName,
                    'contact_designation' => (string) $contactDesignation,
                    'status' => in_array($resp->status, [AskResponse::STATUS_ACCEPTED, AskResponse::STATUS_IN_PROGRESS, 'connected'], true) ? 'connected' : (string) $resp->status,
                ];
            }

            $myAsks[] = [
                'id' => (string) $ask->id,
                'title' => (string) $ask->title,
                'status' => $ask->status === Ask::STATUS_FULFILLED ? 'fulfilled' : (count($responsesList) > 0 ? 'in_progress' : 'open'),
                'responses' => $responsesList,
            ];
        }

        // Received Introductions from referrals table
        $referrals = Referral::query()
            ->where('to_user_id', $user->id)
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->with(['fromUser', 'status'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $receivedIntroductions = [];
        foreach ($referrals as $ref) {
            /** @var Referral $ref */
            $introducedBy = $ref->fromUser;
            $statusName = $ref->status?->name ?? 'Contacted';

            $receivedIntroductions[] = [
                'id' => (string) $ref->id,
                'referral_of' => (string) ($ref->referral_of ?? 'Business Referral'),
                'introduced_by' => [
                    'id' => (string) ($introducedBy?->id ?? ''),
                    'display_name' => $introducedBy ? ($introducedBy->display_name ?: trim(($introducedBy->first_name ?? '').' '.($introducedBy->last_name ?? ''))) : 'Peer Member',
                ],
                'status_id' => (int) ($ref->status_id ?? 1),
                'status_label' => (string) $statusName,
            ];
        }

        return [
            'my_asks' => $myAsks,
            'received_introductions' => $receivedIntroductions,
        ];
    }

    /**
     * Help: My Help Requests & Guidance History
     *
     * @param  array{page?: int, limit?: int}  $params
     * @return array{items: array<int, mixed>}
     */
    protected function getMyHelpAsks(User $user, array $params = []): array
    {
        $asks = Ask::query()
            ->where('user_id', $user->id)
            ->whereHas('flow', fn (Builder $q) => $q->where('code', 'help'))
            ->with(['responses.responder'])
            ->orderByDesc('created_at')
            ->get();

        $items = [];
        foreach ($asks as $ask) {
            /** @var Ask $ask */
            $fulfilledBy = null;
            if ($ask->status === Ask::STATUS_FULFILLED || filled($ask->fulfilled_at)) {
                $giverId = $ask->metadata['giver_id'] ?? null;
                $giverUser = null;
                if ($giverId) {
                    $giverUser = User::find($giverId);
                }
                if (! $giverUser) {
                    $acceptedResp = $ask->responses->firstWhere('status', AskResponse::STATUS_ACCEPTED)
                        ?? $ask->responses->firstWhere('status', AskResponse::STATUS_COMPLETED)
                        ?? $ask->responses->first();
                    $giverUser = $acceptedResp?->responder;
                }

                if ($giverUser) {
                    $fulfilledBy = [
                        'id' => (string) $giverUser->id,
                        'display_name' => $giverUser->display_name ?: trim(($giverUser->first_name ?? '').' '.($giverUser->last_name ?? '')),
                        'avatar_url' => $giverUser->profile_photo_file_id
                            ? url('/api/v1/files/'.$giverUser->profile_photo_file_id)
                            : ($giverUser->profile_photo_url ?? null),
                    ];
                }
            }

            $items[] = [
                'id' => (string) $ask->id,
                'flow_code' => 'help',
                'title' => (string) $ask->title,
                'status' => $ask->status === Ask::STATUS_FULFILLED ? 'fulfilled' : (string) $ask->status,
                'fulfilled_by' => $fulfilledBy,
                'created_at' => $ask->created_at?->toISOString() ?? now()->toISOString(),
            ];
        }

        return [
            'items' => $items,
        ];
    }

    /**
     * 3. Leaderboard for a specific Flow
     *
     * @param  array{limit?: int}  $params
     * @return array{leaderboard: array<int, mixed>}
     */
    public function getLeaderboard(User $user, string $flowCode, array $params = []): array
    {
        $normalizedFlow = $this->normalizeFlowCode($flowCode);
        $limit = max(1, min(100, (int) ($params['limit'] ?? 20)));

        if ($normalizedFlow === 'collaboration') {
            return $this->getCollaborationLeaderboard($limit);
        }

        if ($normalizedFlow === 'referral') {
            return $this->getReferralLeaderboard($limit);
        }

        return $this->getHelpLeaderboard($limit);
    }

    /**
     * Collaboration Leaderboard
     *
     * @return array{leaderboard: array<int, mixed>}
     */
    protected function getCollaborationLeaderboard(int $limit = 20): array
    {
        $collabCounts = Ask::query()
            ->whereHas('flow', fn (Builder $q) => $q->where('code', 'collaboration'))
            ->select('user_id', DB::raw('count(*) as count'))
            ->groupBy('user_id')
            ->pluck('count', 'user_id')
            ->all();

        $userIds = array_keys($collabCounts);

        $users = User::query()
            ->where('status', 'active')
            ->when(! empty($userIds), fn ($q) => $q->whereIn('id', $userIds))
            ->limit($limit * 2)
            ->get();

        if ($users->isEmpty()) {
            $users = User::query()->where('status', 'active')->limit($limit)->get();
        }

        $leaderboard = [];
        foreach ($users as $u) {
            /** @var User $u */
            $collabCount = (int) ($collabCounts[$u->id] ?? 0);

            $leaderboard[] = [
                'user_id' => (string) $u->id,
                'display_name' => $u->display_name ?: trim(($u->first_name ?? '').' '.($u->last_name ?? '')),
                'avatar_url' => $u->profile_photo_file_id
                    ? url('/api/v1/files/'.$u->profile_photo_file_id)
                    : ($u->profile_photo_url ?? null),
                'collaborations_count' => $collabCount,
                'life_impacted' => (int) ($u->life_impact_points ?? 0),
            ];
        }

        usort($leaderboard, fn ($a, $b) => ($b['collaborations_count'] <=> $a['collaborations_count']) ?: ($b['life_impacted'] <=> $a['life_impacted']));

        $result = [];
        $rank = 1;
        foreach (array_slice($leaderboard, 0, $limit) as $item) {
            $item['rank'] = $rank++;
            $result[] = $item;
        }

        return [
            'leaderboard' => $result,
        ];
    }

    /**
     * Referral Leaderboard
     *
     * @return array{leaderboard: array<int, mixed>}
     */
    protected function getReferralLeaderboard(int $limit = 20): array
    {
        $givenCounts = Referral::query()
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->select('from_user_id', DB::raw('count(*) as count'))
            ->groupBy('from_user_id')
            ->pluck('count', 'from_user_id')
            ->all();

        $receivedCounts = Referral::query()
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->select('to_user_id', DB::raw('count(*) as count'))
            ->groupBy('to_user_id')
            ->pluck('count', 'to_user_id')
            ->all();

        $dealsByUser = BusinessDeal::query()
            ->where('is_deleted', false)
            ->select('from_user_id', DB::raw('count(*) as deals_count'))
            ->groupBy('from_user_id')
            ->pluck('deals_count', 'from_user_id')
            ->all();

        $userIds = array_unique(array_merge(
            array_keys($givenCounts),
            array_keys($receivedCounts),
            array_keys($dealsByUser)
        ));

        $users = User::query()
            ->where('status', 'active')
            ->when(! empty($userIds), fn ($q) => $q->whereIn('id', $userIds))
            ->limit($limit * 2)
            ->get();

        if ($users->isEmpty()) {
            $users = User::query()->where('status', 'active')->limit($limit)->get();
        }

        $leaderboard = [];
        foreach ($users as $u) {
            /** @var User $u */
            $given = (int) ($givenCounts[$u->id] ?? 0);
            $received = (int) ($receivedCounts[$u->id] ?? 0);
            $deals = (int) ($dealsByUser[$u->id] ?? 0);

            $leaderboard[] = [
                'user_id' => (string) $u->id,
                'display_name' => $u->display_name ?: trim(($u->first_name ?? '').' '.($u->last_name ?? '')),
                'avatar_url' => $u->profile_photo_file_id
                    ? url('/api/v1/files/'.$u->profile_photo_file_id)
                    : ($u->profile_photo_url ?? null),
                'referrals_given' => $given,
                'referrals_received' => $received,
                'deals_closed' => $deals,
            ];
        }

        usort($leaderboard, fn ($a, $b) => ($b['referrals_given'] <=> $a['referrals_given']) ?: ($b['deals_closed'] <=> $a['deals_closed']));

        $result = [];
        $rank = 1;
        foreach (array_slice($leaderboard, 0, $limit) as $item) {
            $item['rank'] = $rank++;
            $result[] = $item;
        }

        return [
            'leaderboard' => $result,
        ];
    }

    /**
     * Help / Givers Leaderboard
     *
     * @return array{leaderboard: array<int, mixed>}
     */
    protected function getHelpLeaderboard(int $limit = 20): array
    {
        $helpCounts = AskResponse::query()
            ->whereHas('ask.flow', fn (Builder $q) => $q->where('code', 'help'))
            ->select('responder_user_id', DB::raw('count(*) as count'))
            ->groupBy('responder_user_id')
            ->pluck('count', 'responder_user_id')
            ->all();

        $userIds = array_keys($helpCounts);

        $users = User::query()
            ->where('status', 'active')
            ->when(! empty($userIds), fn ($q) => $q->whereIn('id', $userIds))
            ->limit($limit * 2)
            ->get();

        if ($users->isEmpty()) {
            $users = User::query()->where('status', 'active')->limit($limit)->get();
        }

        $leaderboard = [];
        foreach ($users as $u) {
            /** @var User $u */
            $rendered = (int) ($helpCounts[$u->id] ?? 0);

            $badge = match (true) {
                $rendered >= 20 => 'Top Giver of the Month',
                $rendered >= 10 => 'Community Pillar',
                $rendered >= 5 => 'Master Mentor',
                default => 'Active Helper',
            };

            $leaderboard[] = [
                'user_id' => (string) $u->id,
                'display_name' => $u->display_name ?: trim(($u->first_name ?? '').' '.($u->last_name ?? '')),
                'avatar_url' => $u->profile_photo_file_id
                    ? url('/api/v1/files/'.$u->profile_photo_file_id)
                    : ($u->profile_photo_url ?? null),
                'help_rendered_count' => $rendered,
                'giver_badge' => $badge,
            ];
        }

        usort($leaderboard, fn ($a, $b) => $b['help_rendered_count'] <=> $a['help_rendered_count']);

        $result = [];
        $rank = 1;
        foreach (array_slice($leaderboard, 0, $limit) as $item) {
            $item['rank'] = $rank++;
            $result[] = $item;
        }

        return [
            'leaderboard' => $result,
        ];
    }

    /**
     * Get Categories for Asks / Collaboration / Referral / Help
     *
     * @return array<int, array{id: string, code: string, name: string, flow_code: string}>
     */
    public function getCategories(?string $flowCode = null): array
    {
        $query = AskType::query()
            ->where('is_active', true)
            ->with('flow')
            ->orderBy('sort_order');

        if ($flowCode !== null && $flowCode !== '' && $flowCode !== 'all') {
            $normalized = $this->normalizeFlowCode($flowCode);
            $query->whereHas('flow', fn (Builder $q) => $q->where('code', $normalized));
        }

        return $query->get()->map(function (AskType $type): array {
            return [
                'id' => (string) $type->id,
                'code' => (string) $type->code,
                'name' => (string) $type->name,
                'flow_code' => (string) ($type->flow?->code ?? 'collaboration'),
            ];
        })->values()->all();
    }

    /**
     * Normalize flow code string
     */
    public function normalizeFlowCode(string $flow): string
    {
        return match (strtolower(trim($flow))) {
            'collaboration', 'collaborator', 'find_a_collaborator', 'collab' => 'collaboration',
            'referral', 'referrals', 'ask_for_referral' => 'referral',
            'help', 'get_help' => 'help',
            default => strtolower(trim($flow)),
        };
    }
}
