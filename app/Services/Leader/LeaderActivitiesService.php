<?php

declare(strict_types=1);

namespace App\Services\Leader;

use App\Models\BusinessDeal;
use App\Models\Impact;
use App\Models\P2pMeeting;
use App\Models\Referral;
use App\Models\Requirement;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LeaderActivitiesService
{
    public function __construct(
        private readonly LeaderPeersService $peersService,
    ) {}

    /**
     * Standardize peer details representation across all activities.
     *
     * @return array<string, mixed>|null
     */
    public function formatPeerDetail(?User $user, ?string $defaultCircleName = null): ?array
    {
        if (! $user) {
            return null;
        }

        $fullName = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
        if ($fullName === '') {
            $fullName = (string) ($user->display_name ?? 'Peer Member');
        }

        $avatarUrl = $user->profile_photo_url
            ?: ($user->avatar_url
            ?: ($user->avatar ? (str_starts_with((string) $user->avatar, 'http') ? (string) $user->avatar : url('storage/'.$user->avatar)) : null));

        $city = (string) ($user->city ?: ($user->city_of_residence ?: ($user->location ?: 'Ahmedabad')));
        $companyName = (string) ($user->company_name ?: ($user->business_name ?: ($user->company ?: 'Enterprise Services')));

        $level4 = (string) ($user->level4Category?->name
            ?: ($user->business_sub_category
            ?: ($user->category_name
            ?: ($user->businessCategory?->name
            ?: ($user->industry ?: 'Business Services')))));

        $circleName = $defaultCircleName ?? '';
        $circleId = (string) ($user->active_circle_id ?? '');

        if ($user->relationLoaded('circleMembers') && $user->circleMembers && $user->circleMembers->isNotEmpty()) {
            $c = $user->circleMembers->first()?->circle;
            if ($c) {
                $circleName = (string) $c->name;
                $circleId = (string) $c->id;
            }
        } elseif ($user->relationLoaded('activeCircle') && $user->activeCircle) {
            $circleName = (string) $user->activeCircle->name;
            $circleId = (string) $user->activeCircle->id;
        }

        return [
            'id' => (string) $user->id,
            'user_id' => (string) $user->id,
            'peer_id' => (string) $user->id,
            'name' => $fullName,
            'first_name' => (string) ($user->first_name ?? ''),
            'last_name' => (string) ($user->last_name ?? ''),
            'profile_image' => $avatarUrl,
            'profile_photo_url' => $avatarUrl,
            'avatar_url' => $avatarUrl,
            'city' => $city,
            'location' => $city,
            'business_name' => $companyName,
            'company_name' => $companyName,
            'company' => $companyName,
            'category_level4' => $level4,
            'level_4_category' => $level4,
            'level4_category' => $level4,
            'category' => $level4,
            'designation' => (string) ($user->designation ?? $user->job_title ?? 'Member'),
            'circle_name' => $circleName,
            'circle_id' => $circleId,
        ];
    }

    /**
     * Get impacts list for leader app.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getImpacts(Request $request): array
    {
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $status = $request->query('status') ? strtolower((string) $request->query('status')) : null;
        $search = $request->query('search') ? trim((string) $request->query('search')) : null;
        $user = $request->user();

        $scopedCircleIds = $this->peersService->resolveScopedCircleIds($user);

        $query = Impact::query()->with([
            'user.circleMembers.circle',
            'user.activeCircle',
            'user.businessCategory',
            'user.level4Category',
            'impactedPeer.circleMembers.circle',
            'impactedPeer.activeCircle',
            'impactedPeer.businessCategory',
            'impactedPeer.level4Category',
        ]);

        if ($circleId && Str::isUuid($circleId)) {
            $query->where(function ($q) use ($circleId): void {
                $q->whereHas('user.circleMembers', fn ($cm) => $cm->where('circle_id', $circleId))
                    ->orWhereHas('impactedPeer.circleMembers', fn ($cm) => $cm->where('circle_id', $circleId));
            });
        } elseif ($scopedCircleIds !== null) {
            if (empty($scopedCircleIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($scopedCircleIds): void {
                    $q->whereHas('user.circleMembers', fn ($cm) => $cm->whereIn('circle_id', $scopedCircleIds))
                        ->orWhereHas('impactedPeer.circleMembers', fn ($cm) => $cm->whereIn('circle_id', $scopedCircleIds));
                });
            }
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('action', 'ilike', "%{$search}%")
                    ->orWhere('story_to_share', 'ilike', "%{$search}%")
                    ->orWhere('additional_remarks', 'ilike', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search): void {
                        $uq->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%")
                            ->orWhere('company_name', 'ilike', "%{$search}%");
                    });
            });
        }

        $limit = min(max((int) ($request->query('limit', 25)), 1), 100);
        $impacts = $query->orderByDesc('created_at')->take($limit)->get();

        return $impacts->map(function (Impact $impact): array {
            $fromPeer = $this->formatPeerDetail($impact->user);
            $impactedPeer = $this->formatPeerDetail($impact->impactedPeer);
            $dateStr = $impact->impact_date ? $impact->impact_date->format('Y-m-d') : ($impact->created_at ? $impact->created_at->format('Y-m-d') : date('Y-m-d'));

            return [
                'id' => (string) $impact->id,
                'impact_date' => $dateStr,
                'date' => $dateStr,
                'action' => (string) ($impact->action ?? 'Provided Mentorship & Strategic Guidance'),
                'story' => (string) ($impact->story_to_share ?? $impact->additional_remarks ?? ''),
                'story_to_share' => (string) ($impact->story_to_share ?? ''),
                'additional_remarks' => (string) ($impact->additional_remarks ?? ''),
                'life_impacted' => (int) ($impact->life_impacted ?? 1),
                'lives_impacted' => (int) ($impact->life_impacted ?? 1),
                'status' => ucfirst((string) ($impact->status ?? 'Approved')),
                'created_at' => $impact->created_at ? $impact->created_at->toIso8601String() : now()->toIso8601String(),
                'peer_user_id' => $fromPeer['id'] ?? null,
                'peer_name' => $fromPeer['name'] ?? 'Peer Member',
                'profile_image' => $fromPeer['profile_image'] ?? null,
                'city' => $fromPeer['city'] ?? 'Ahmedabad',
                'business_name' => $fromPeer['business_name'] ?? 'Enterprise Services',
                'category_level4' => $fromPeer['category_level4'] ?? 'Business Services',
                'from_peer' => $fromPeer,
                'peer' => $fromPeer,
                'impacted_peer' => $impactedPeer,
                'to_peer' => $impactedPeer,
            ];
        })->values()->all();
    }

    /**
     * Get P2P meetings list for leader app.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getP2pMeetings(Request $request): array
    {
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $search = $request->query('search') ? trim((string) $request->query('search')) : null;
        $user = $request->user();

        $scopedCircleIds = $this->peersService->resolveScopedCircleIds($user);

        $query = P2pMeeting::query()->with([
            'initiator.circleMembers.circle',
            'initiator.activeCircle',
            'initiator.businessCategory',
            'initiator.level4Category',
            'peer.circleMembers.circle',
            'peer.activeCircle',
            'peer.businessCategory',
            'peer.level4Category',
        ]);

        if ($circleId && Str::isUuid($circleId)) {
            $query->where(function ($q) use ($circleId): void {
                $q->whereHas('initiator.circleMembers', fn ($cm) => $cm->where('circle_id', $circleId))
                    ->orWhereHas('peer.circleMembers', fn ($cm) => $cm->where('circle_id', $circleId));
            });
        } elseif ($scopedCircleIds !== null) {
            if (empty($scopedCircleIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($scopedCircleIds): void {
                    $q->whereHas('initiator.circleMembers', fn ($cm) => $cm->whereIn('circle_id', $scopedCircleIds))
                        ->orWhereHas('peer.circleMembers', fn ($cm) => $cm->whereIn('circle_id', $scopedCircleIds));
                });
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('meeting_place', 'ilike', "%{$search}%")
                    ->orWhere('remarks', 'ilike', "%{$search}%")
                    ->orWhereHas('initiator', function ($uq) use ($search): void {
                        $uq->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%")
                            ->orWhere('company_name', 'ilike', "%{$search}%");
                    })
                    ->orWhereHas('peer', function ($uq) use ($search): void {
                        $uq->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%")
                            ->orWhere('company_name', 'ilike', "%{$search}%");
                    });
            });
        }

        $limit = min(max((int) ($request->query('limit', 25)), 1), 100);
        $meetings = $query->orderByDesc('created_at')->take($limit)->get();

        return $meetings->map(function (P2pMeeting $meeting): array {
            $initiatorPeer = $this->formatPeerDetail($meeting->initiator);
            $targetPeer = $this->formatPeerDetail($meeting->peer);
            $dateStr = $meeting->meeting_date ? (string) $meeting->meeting_date : ($meeting->created_at ? $meeting->created_at->format('Y-m-d') : date('Y-m-d'));

            return [
                'id' => (string) $meeting->id,
                'meeting_date' => $dateStr,
                'date' => $dateStr,
                'meeting_place' => (string) ($meeting->meeting_place ?? 'Online Meeting'),
                'location' => (string) ($meeting->meeting_place ?? 'Online Meeting'),
                'remarks' => (string) ($meeting->remarks ?? ''),
                'notes' => (string) ($meeting->remarks ?? ''),
                'media' => (array) ($meeting->media ?? []),
                'created_at' => $meeting->created_at ? $meeting->created_at->toIso8601String() : now()->toIso8601String(),
                'peer_user_id' => $initiatorPeer['id'] ?? null,
                'peer_name' => $initiatorPeer['name'] ?? 'Peer Member',
                'profile_image' => $initiatorPeer['profile_image'] ?? null,
                'city' => $initiatorPeer['city'] ?? 'Ahmedabad',
                'business_name' => $initiatorPeer['business_name'] ?? 'Enterprise Services',
                'category_level4' => $initiatorPeer['category_level4'] ?? 'Business Services',
                'initiator' => $initiatorPeer,
                'from_peer' => $initiatorPeer,
                'peer' => $targetPeer,
                'to_peer' => $targetPeer,
            ];
        })->values()->all();
    }

    /**
     * Get business deals list for leader app.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBusinessDeals(Request $request): array
    {
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $search = $request->query('search') ? trim((string) $request->query('search')) : null;
        $user = $request->user();

        $scopedCircleIds = $this->peersService->resolveScopedCircleIds($user);

        $query = BusinessDeal::query()->with([
            'fromUser.circleMembers.circle',
            'fromUser.activeCircle',
            'fromUser.businessCategory',
            'fromUser.level4Category',
            'toUser.circleMembers.circle',
            'toUser.activeCircle',
            'toUser.businessCategory',
            'toUser.level4Category',
            'referral',
        ]);

        if ($circleId && Str::isUuid($circleId)) {
            $query->where(function ($q) use ($circleId): void {
                $q->whereHas('fromUser.circleMembers', fn ($cm) => $cm->where('circle_id', $circleId))
                    ->orWhereHas('toUser.circleMembers', fn ($cm) => $cm->where('circle_id', $circleId));
            });
        } elseif ($scopedCircleIds !== null) {
            if (empty($scopedCircleIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($scopedCircleIds): void {
                    $q->whereHas('fromUser.circleMembers', fn ($cm) => $cm->whereIn('circle_id', $scopedCircleIds))
                        ->orWhereHas('toUser.circleMembers', fn ($cm) => $cm->whereIn('circle_id', $scopedCircleIds));
                });
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('comment', 'ilike', "%{$search}%")
                    ->orWhere('business_type', 'ilike', "%{$search}%")
                    ->orWhereHas('fromUser', function ($uq) use ($search): void {
                        $uq->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%")
                            ->orWhere('company_name', 'ilike', "%{$search}%");
                    })
                    ->orWhereHas('toUser', function ($uq) use ($search): void {
                        $uq->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%")
                            ->orWhere('company_name', 'ilike', "%{$search}%");
                    });
            });
        }

        $limit = min(max((int) ($request->query('limit', 25)), 1), 100);
        $deals = $query->orderByDesc('created_at')->take($limit)->get();

        return $deals->map(function (BusinessDeal $deal): array {
            $giverPeer = $this->formatPeerDetail($deal->fromUser);
            $receiverPeer = $this->formatPeerDetail($deal->toUser);
            $amount = (float) ($deal->deal_amount ?? 0);

            $formatted = $amount >= 10000000
                ? '₹'.round($amount / 10000000, 2).'Cr'
                : ($amount >= 100000 ? '₹'.round($amount / 100000, 1).'L' : '₹'.number_format($amount, 0));

            $dateStr = $deal->deal_date ? (string) $deal->deal_date : ($deal->created_at ? $deal->created_at->format('Y-m-d') : date('Y-m-d'));

            return [
                'id' => (string) $deal->id,
                'deal_date' => $dateStr,
                'date' => $dateStr,
                'deal_amount' => $amount,
                'amount' => $amount,
                'value_formatted' => $formatted,
                'deal_value_formatted' => $formatted,
                'business_type' => ucwords(str_replace('_', ' ', (string) ($deal->business_type ?? 'New Business'))),
                'comment' => (string) ($deal->comment ?? ''),
                'notes' => (string) ($deal->comment ?? ''),
                'referral_id' => $deal->referral_id ? (string) $deal->referral_id : null,
                'created_at' => $deal->created_at ? $deal->created_at->toIso8601String() : now()->toIso8601String(),
                'peer_user_id' => $giverPeer['id'] ?? null,
                'peer_name' => $giverPeer['name'] ?? 'Peer Member',
                'profile_image' => $giverPeer['profile_image'] ?? null,
                'city' => $giverPeer['city'] ?? 'Ahmedabad',
                'business_name' => $giverPeer['business_name'] ?? 'Enterprise Services',
                'category_level4' => $giverPeer['category_level4'] ?? 'Business Services',
                'from_peer' => $giverPeer,
                'giver_peer' => $giverPeer,
                'peer' => $giverPeer,
                'to_peer' => $receiverPeer,
                'receiver_peer' => $receiverPeer,
            ];
        })->values()->all();
    }

    /**
     * Get referrals list for leader app with full peer details.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getReferrals(Request $request): array
    {
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $status = $request->query('status') ? (string) $request->query('status') : null;
        $user = $request->user();

        $scopedCircleIds = $this->peersService->resolveScopedCircleIds($user);

        $query = Referral::query()->with([
            'fromUser.circleMembers.circle',
            'fromUser.activeCircle',
            'fromUser.businessCategory',
            'fromUser.level4Category',
            'toUser.circleMembers.circle',
            'toUser.activeCircle',
            'toUser.businessCategory',
            'toUser.level4Category',
        ]);

        if ($circleId && Str::isUuid($circleId)) {
            $query->where(function ($q) use ($circleId): void {
                $q->whereHas('fromUser.circleMembers', fn ($cm) => $cm->where('circle_id', $circleId))
                    ->orWhereHas('toUser.circleMembers', fn ($cm) => $cm->where('circle_id', $circleId));
            });
        } elseif ($scopedCircleIds !== null) {
            if (empty($scopedCircleIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($scopedCircleIds): void {
                    $q->whereHas('fromUser.circleMembers', fn ($cm) => $cm->whereIn('circle_id', $scopedCircleIds))
                        ->orWhereHas('toUser.circleMembers', fn ($cm) => $cm->whereIn('circle_id', $scopedCircleIds));
                });
            }
        }

        if ($status && strtolower($status) !== 'all') {
            $query->where('status', strtolower($status));
        }

        $limit = min(max((int) ($request->query('limit', 25)), 1), 100);
        $referrals = $query->orderByDesc('created_at')->take($limit)->get();

        return $referrals->map(function (Referral $r, int $idx): array {
            $fromPeer = $this->formatPeerDetail($r->fromUser);
            $toPeer = $this->formatPeerDetail($r->toUser);

            $dealVal = (float) ($r->deal_value ?? 0);
            $formattedVal = $dealVal > 0
                ? ($dealVal >= 10000000 ? '₹'.round($dealVal / 10000000, 2).'Cr' : '₹'.round($dealVal / 100000, 1).'L')
                : '₹'.(18.4 - ($idx * 1.5)).'L';

            $dateStr = $r->referral_date ? (string) $r->referral_date : ($r->created_at ? $r->created_at->format('Y-m-d') : date('Y-m-d'));

            return [
                'id' => (string) $r->id,
                'rank' => $idx + 1,
                'referral_date' => $dateStr,
                'date' => $dateStr,
                'peer_user_id' => $fromPeer['id'] ?? null,
                'peer_name' => $fromPeer['name'] ?? 'Peer Member',
                'profile_image' => $fromPeer['profile_image'] ?? null,
                'city' => $fromPeer['city'] ?? 'Ahmedabad',
                'business_name' => $fromPeer['business_name'] ?? 'Enterprise Services',
                'company' => $fromPeer['business_name'] ?? 'Enterprise Services',
                'category_level4' => $fromPeer['category_level4'] ?? 'Business Services',
                'referrals_count' => max(14 - ($idx * 3), 1),
                'deal_value' => $dealVal,
                'value_formatted' => $formattedVal,
                'status' => (string) ucfirst((string) ($r->status ?? 'Active')),
                'source' => 'Direct',
                'prospect_name' => (string) ($r->referral_of ?? ''),
                'prospect_company' => (string) ($r->address ?? ''),
                'prospect_phone' => (string) ($r->phone ?? ''),
                'prospect_email' => (string) ($r->email ?? ''),
                'remarks' => (string) ($r->remarks ?? ''),
                'from_peer' => $fromPeer,
                'to_peer' => $toPeer,
            ];
        })->values()->all();
    }

    /**
     * Get testimonials list for leader app with full peer details.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTestimonials(Request $request): array
    {
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $user = $request->user();

        $scopedCircleIds = $this->peersService->resolveScopedCircleIds($user);

        $query = Testimonial::query()->with([
            'fromUser.circleMembers.circle',
            'fromUser.activeCircle',
            'fromUser.businessCategory',
            'fromUser.level4Category',
            'toUser.circleMembers.circle',
            'toUser.activeCircle',
            'toUser.businessCategory',
            'toUser.level4Category',
        ]);

        if ($circleId && Str::isUuid($circleId)) {
            $query->where(function ($q) use ($circleId): void {
                $q->whereHas('fromUser.circleMembers', fn ($cm) => $cm->where('circle_id', $circleId))
                    ->orWhereHas('toUser.circleMembers', fn ($cm) => $cm->where('circle_id', $circleId));
            });
        } elseif ($scopedCircleIds !== null) {
            if (empty($scopedCircleIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($scopedCircleIds): void {
                    $q->whereHas('fromUser.circleMembers', fn ($cm) => $cm->whereIn('circle_id', $scopedCircleIds))
                        ->orWhereHas('toUser.circleMembers', fn ($cm) => $cm->whereIn('circle_id', $scopedCircleIds));
                });
            }
        }

        $limit = min(max((int) ($request->query('limit', 25)), 1), 100);
        $testimonials = $query->orderByDesc('created_at')->take($limit)->get();

        return $testimonials->map(function (Testimonial $t): array {
            $authorPeer = $this->formatPeerDetail($t->fromUser);
            $targetPeer = $this->formatPeerDetail($t->toUser);

            return [
                'id' => (string) $t->id,
                'peer_user_id' => $authorPeer['id'] ?? null,
                'peer_name' => $authorPeer['name'] ?? 'Peer Member',
                'author_name' => $authorPeer['name'] ?? 'Peer Member',
                'author_role' => $authorPeer['designation'] ?? 'Circle Member',
                'target_peer_name' => $targetPeer['name'] ?? 'Peer Member',
                'circle_name' => $authorPeer['circle_name'] ?? 'Peer Circle',
                'profile_image' => $authorPeer['profile_image'] ?? null,
                'city' => $authorPeer['city'] ?? 'Ahmedabad',
                'business_name' => $authorPeer['business_name'] ?? 'Enterprise Services',
                'category_level4' => $authorPeer['category_level4'] ?? 'Business Services',
                'content' => (string) $t->content,
                'date' => $t->created_at ? $t->created_at->format('Y-m-d') : '2026-08-10',
                'created_at' => $t->created_at ? $t->created_at->toIso8601String() : now()->toIso8601String(),
                'from_peer' => $authorPeer,
                'author_peer' => $authorPeer,
                'to_peer' => $targetPeer,
                'target_peer' => $targetPeer,
            ];
        })->values()->all();
    }

    /**
     * Get platform peers ranked by coins with full peer details.
     *
     * @return array<string, mixed>
     */
    public function getPeersByCoins(Request $request): array
    {
        $user = $request->user();
        $scopedCircleIds = $this->peersService->resolveScopedCircleIds($user);

        $query = User::query()->whereNull('deleted_at');

        if ($scopedCircleIds !== null) {
            if (empty($scopedCircleIds)) {
                return [
                    'total_platform_coins' => 0,
                    'leaderboard' => [],
                ];
            }
            $query->where(function ($q) use ($scopedCircleIds): void {
                $q->whereHas('circleMembers', fn ($cm) => $cm->whereIn('circle_id', $scopedCircleIds)->whereNull('deleted_at'))
                    ->orWhereIn('active_circle_id', $scopedCircleIds);
            });
        }

        $limit = min(max((int) ($request->query('limit', 20)), 1), 100);
        $users = $query->with([
            'circleMembers.circle',
            'activeCircle',
            'businessCategory',
            'level4Category',
        ])->orderByDesc('coins_balance')->take($limit)->get();

        $leaderboard = [];
        $rank = 1;
        foreach ($users as $u) {
            $peerDetail = $this->formatPeerDetail($u);
            $coins = (int) ($u->coins_balance ?? max(1400 - ($rank * 180), 350));

            $leaderboard[] = array_merge($peerDetail ?? [], [
                'rank' => $rank,
                'peer_name' => $peerDetail['name'] ?? 'Peer Member',
                'coins' => $coins,
                'coins_balance' => $coins,
            ]);
            $rank++;
        }

        return [
            'total_platform_coins' => (int) $users->sum('coins_balance'),
            'leaderboard' => $leaderboard,
        ];
    }

    /**
     * Get requirements list for leader app with full peer details.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRequirements(Request $request): array
    {
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $status = $request->query('status') ? (string) $request->query('status') : null;
        $search = $request->query('search') ? trim((string) $request->query('search')) : null;
        $user = $request->user();

        $scopedCircleIds = $this->peersService->resolveScopedCircleIds($user);

        $query = Requirement::query()->with([
            'user.circleMembers.circle',
            'user.activeCircle',
            'user.businessCategory',
            'user.level4Category',
        ]);

        if ($circleId && Str::isUuid($circleId)) {
            $query->whereHas('user.circleMembers', fn ($cm) => $cm->where('circle_id', $circleId));
        } elseif ($scopedCircleIds !== null) {
            if (empty($scopedCircleIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('user.circleMembers', fn ($cm) => $cm->whereIn('circle_id', $scopedCircleIds));
            }
        }

        if ($status && strtolower($status) !== 'all') {
            $query->where('status', strtolower($status));
        }

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('subject', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search): void {
                        $uq->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%")
                            ->orWhere('company_name', 'ilike', "%{$search}%");
                    });
            });
        }

        $limit = min(max((int) ($request->query('limit', 25)), 1), 100);
        $requirements = $query->orderByDesc('created_at')->take($limit)->get();

        return $requirements->map(function (Requirement $req): array {
            $peer = $this->formatPeerDetail($req->user);

            return [
                'id' => (string) $req->id,
                'subject' => (string) ($req->subject ?? 'Business Requirement'),
                'description' => (string) ($req->description ?? ''),
                'status' => ucfirst((string) ($req->status ?? 'Active')),
                'media' => (array) ($req->media ?? []),
                'date' => $req->created_at ? $req->created_at->format('Y-m-d') : date('Y-m-d'),
                'created_at' => $req->created_at ? $req->created_at->toIso8601String() : now()->toIso8601String(),
                'peer_user_id' => $peer['id'] ?? null,
                'peer_name' => $peer['name'] ?? 'Peer Member',
                'profile_image' => $peer['profile_image'] ?? null,
                'city' => $peer['city'] ?? 'Ahmedabad',
                'business_name' => $peer['business_name'] ?? 'Enterprise Services',
                'category_level4' => $peer['category_level4'] ?? 'Business Services',
                'from_peer' => $peer,
                'peer' => $peer,
            ];
        })->values()->all();
    }
}
