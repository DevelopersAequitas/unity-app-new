<?php

declare(strict_types=1);

namespace App\Leader\Services;

use App\Models\BusinessDeal;
use App\Models\Impact;
use App\Models\P2pMeeting;
use App\Models\Referral;
use App\Models\Requirement;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        $circleMemberL4 = null;
        if ($user->relationLoaded('circleMembers') && $user->circleMembers && $user->circleMembers->isNotEmpty()) {
            $circleMemberL4 = $user->circleMembers->first()?->level4Category?->name;
        }

        $level4 = (string) ($circleMemberL4
            ?: ($user->level4Category?->name
            ?: ($user->business_sub_category
            ?: ($user->category_name
            ?: ($user->businessCategory?->name
            ?: ($user->industry ?: 'Business Services'))))));

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
            'peer_user_id' => (string) $user->id,
            'peer_id' => (string) $user->id,
            'name' => $fullName,
            'peer_name' => $fullName,
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
            'life_impacted_count' => (int) ($user->life_impacted_count ?? 0),
            'is_pro' => method_exists($user, 'isPro') ? (bool) $user->isPro() : false,
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
            'user.circleMembers.level4Category',
            'user.activeCircle',
            'user.businessCategory',
            'user.level4Category',
            'impactedPeer.circleMembers.circle',
            'impactedPeer.circleMembers.level4Category',
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
                'user_id' => $fromPeer['id'] ?? null,
                'peer_id' => $fromPeer['id'] ?? null,
                'peer_name' => $fromPeer['name'] ?? 'Peer Member',
                'name' => $fromPeer['name'] ?? 'Peer Member',
                'profile_image' => $fromPeer['profile_image'] ?? null,
                'profile_photo_url' => $fromPeer['profile_image'] ?? null,
                'avatar_url' => $fromPeer['profile_image'] ?? null,
                'city' => $fromPeer['city'] ?? 'Ahmedabad',
                'location' => $fromPeer['city'] ?? 'Ahmedabad',
                'business_name' => $fromPeer['business_name'] ?? 'Enterprise Services',
                'company_name' => $fromPeer['company_name'] ?? 'Enterprise Services',
                'company' => $fromPeer['company_name'] ?? 'Enterprise Services',
                'category_level4' => $fromPeer['category_level4'] ?? 'Business Services',
                'level_4_category' => $fromPeer['category_level4'] ?? 'Business Services',
                'level4_category' => $fromPeer['category_level4'] ?? 'Business Services',
                'category' => $fromPeer['category_level4'] ?? 'Business Services',
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
            'initiator.circleMembers.level4Category',
            'initiator.activeCircle',
            'initiator.businessCategory',
            'initiator.level4Category',
            'peer.circleMembers.circle',
            'peer.circleMembers.level4Category',
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
                'user_id' => $initiatorPeer['id'] ?? null,
                'peer_id' => $initiatorPeer['id'] ?? null,
                'peer_name' => $initiatorPeer['name'] ?? 'Peer Member',
                'name' => $initiatorPeer['name'] ?? 'Peer Member',
                'profile_image' => $initiatorPeer['profile_image'] ?? null,
                'profile_photo_url' => $initiatorPeer['profile_image'] ?? null,
                'avatar_url' => $initiatorPeer['profile_image'] ?? null,
                'city' => $initiatorPeer['city'] ?? 'Ahmedabad',
                'location' => $initiatorPeer['city'] ?? 'Ahmedabad',
                'business_name' => $initiatorPeer['business_name'] ?? 'Enterprise Services',
                'company_name' => $initiatorPeer['company_name'] ?? 'Enterprise Services',
                'company' => $initiatorPeer['company_name'] ?? 'Enterprise Services',
                'category_level4' => $initiatorPeer['category_level4'] ?? 'Business Services',
                'level_4_category' => $initiatorPeer['category_level4'] ?? 'Business Services',
                'level4_category' => $initiatorPeer['category_level4'] ?? 'Business Services',
                'category' => $initiatorPeer['category_level4'] ?? 'Business Services',
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
            'fromUser.circleMembers.level4Category',
            'fromUser.activeCircle',
            'fromUser.businessCategory',
            'fromUser.level4Category',
            'toUser.circleMembers.circle',
            'toUser.circleMembers.level4Category',
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
                'user_id' => $giverPeer['id'] ?? null,
                'peer_id' => $giverPeer['id'] ?? null,
                'peer_name' => $giverPeer['name'] ?? 'Peer Member',
                'name' => $giverPeer['name'] ?? 'Peer Member',
                'profile_image' => $giverPeer['profile_image'] ?? null,
                'profile_photo_url' => $giverPeer['profile_image'] ?? null,
                'avatar_url' => $giverPeer['profile_image'] ?? null,
                'city' => $giverPeer['city'] ?? 'Ahmedabad',
                'location' => $giverPeer['city'] ?? 'Ahmedabad',
                'business_name' => $giverPeer['business_name'] ?? 'Enterprise Services',
                'company_name' => $giverPeer['company_name'] ?? 'Enterprise Services',
                'company' => $giverPeer['company_name'] ?? 'Enterprise Services',
                'category_level4' => $giverPeer['category_level4'] ?? 'Business Services',
                'level_4_category' => $giverPeer['category_level4'] ?? 'Business Services',
                'level4_category' => $giverPeer['category_level4'] ?? 'Business Services',
                'category' => $giverPeer['category_level4'] ?? 'Business Services',
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
            'fromUser.circleMembers.level4Category',
            'fromUser.activeCircle',
            'fromUser.businessCategory',
            'fromUser.level4Category',
            'toUser.circleMembers.circle',
            'toUser.circleMembers.level4Category',
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
                'user_id' => $fromPeer['id'] ?? null,
                'peer_id' => $fromPeer['id'] ?? null,
                'peer_name' => $fromPeer['name'] ?? 'Peer Member',
                'name' => $fromPeer['name'] ?? 'Peer Member',
                'profile_image' => $fromPeer['profile_image'] ?? null,
                'profile_photo_url' => $fromPeer['profile_image'] ?? null,
                'avatar_url' => $fromPeer['profile_image'] ?? null,
                'city' => $fromPeer['city'] ?? 'Ahmedabad',
                'location' => $fromPeer['city'] ?? 'Ahmedabad',
                'business_name' => $fromPeer['business_name'] ?? 'Enterprise Services',
                'company_name' => $fromPeer['company_name'] ?? 'Enterprise Services',
                'company' => $fromPeer['business_name'] ?? 'Enterprise Services',
                'category_level4' => $fromPeer['category_level4'] ?? 'Business Services',
                'level_4_category' => $fromPeer['category_level4'] ?? 'Business Services',
                'level4_category' => $fromPeer['category_level4'] ?? 'Business Services',
                'category' => $fromPeer['category_level4'] ?? 'Business Services',
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
            'fromUser.circleMembers.level4Category',
            'fromUser.activeCircle',
            'fromUser.businessCategory',
            'fromUser.level4Category',
            'toUser.circleMembers.circle',
            'toUser.circleMembers.level4Category',
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
                'user_id' => $authorPeer['id'] ?? null,
                'peer_id' => $authorPeer['id'] ?? null,
                'peer_name' => $authorPeer['name'] ?? 'Peer Member',
                'name' => $authorPeer['name'] ?? 'Peer Member',
                'author_name' => $authorPeer['name'] ?? 'Peer Member',
                'author_role' => $authorPeer['designation'] ?? 'Circle Member',
                'target_peer_name' => $targetPeer['name'] ?? 'Peer Member',
                'circle_name' => $authorPeer['circle_name'] ?? 'Peer Circle',
                'profile_image' => $authorPeer['profile_image'] ?? null,
                'profile_photo_url' => $authorPeer['profile_image'] ?? null,
                'avatar_url' => $authorPeer['profile_image'] ?? null,
                'city' => $authorPeer['city'] ?? 'Ahmedabad',
                'location' => $authorPeer['city'] ?? 'Ahmedabad',
                'business_name' => $authorPeer['business_name'] ?? 'Enterprise Services',
                'company_name' => $authorPeer['company_name'] ?? 'Enterprise Services',
                'company' => $authorPeer['company_name'] ?? 'Enterprise Services',
                'category_level4' => $authorPeer['category_level4'] ?? 'Business Services',
                'level_4_category' => $authorPeer['category_level4'] ?? 'Business Services',
                'level4_category' => $authorPeer['category_level4'] ?? 'Business Services',
                'category' => $authorPeer['category_level4'] ?? 'Business Services',
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
            'circleMembers.level4Category',
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
                'name' => $peerDetail['name'] ?? 'Peer Member',
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
            'user.circleMembers.level4Category',
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
                'user_id' => $peer['id'] ?? null,
                'peer_id' => $peer['id'] ?? null,
                'peer_name' => $peer['name'] ?? 'Peer Member',
                'name' => $peer['name'] ?? 'Peer Member',
                'profile_image' => $peer['profile_image'] ?? null,
                'profile_photo_url' => $peer['profile_image'] ?? null,
                'avatar_url' => $peer['profile_image'] ?? null,
                'city' => $peer['city'] ?? 'Ahmedabad',
                'location' => $peer['city'] ?? 'Ahmedabad',
                'business_name' => $peer['business_name'] ?? 'Enterprise Services',
                'company_name' => $peer['company_name'] ?? 'Enterprise Services',
                'company' => $peer['company_name'] ?? 'Enterprise Services',
                'category_level4' => $peer['category_level4'] ?? 'Business Services',
                'level_4_category' => $peer['category_level4'] ?? 'Business Services',
                'level4_category' => $peer['category_level4'] ?? 'Business Services',
                'category' => $peer['category_level4'] ?? 'Business Services',
                'from_peer' => $peer,
                'peer' => $peer,
            ];
        })->values()->all();
    }

    /**
     * Create a life impact on behalf of an authenticated peer.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createImpact(User $user, array $data): array
    {
        $impactedPeerId = ! empty($data['impacted_peer_id']) ? (string) $data['impacted_peer_id'] : (! empty($data['to_peer_id']) ? (string) $data['to_peer_id'] : null);
        $impactedUser = null;
        if ($impactedPeerId) {
            $impactedUser = User::query()->where('id', $impactedPeerId)->first();
        }

        $impact = Impact::create([
            'user_id' => $user->id,
            'impacted_peer_id' => $impactedUser?->id ?? $impactedPeerId,
            'impact_date' => $data['impact_date'] ?? now()->toDateString(),
            'action' => $data['action'] ?? 'Provided Mentorship & Strategic Guidance',
            'story_to_share' => $data['story_to_share'] ?? ($data['story'] ?? ''),
            'life_impacted' => (int) ($data['life_impacted'] ?? ($data['lives_impacted'] ?? 1)),
            'additional_remarks' => $data['additional_remarks'] ?? null,
            'status' => 'approved',
        ]);

        return [
            'impact_id' => (string) $impact->id,
            'status' => 'Approved',
        ];
    }

    /**
     * Log a 1-on-1 P2P meeting.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createP2pMeeting(User $user, array $data): array
    {
        $targetPeerId = (string) ($data['peer_id'] ?? ($data['to_peer_id'] ?? ($data['peer_user_id'] ?? '')));
        $targetUser = User::query()->where('id', $targetPeerId)->first();
        $targetUserId = $targetUser ? (string) $targetUser->id : $targetPeerId;

        $meeting = P2pMeeting::create([
            'initiator_user_id' => $user->id,
            'peer_user_id' => $targetUserId,
            'meeting_date' => $data['meeting_date'] ?? now()->toDateString(),
            'meeting_place' => $data['meeting_place'] ?? ($data['location'] ?? 'Grand Hyatt, Ahmedabad'),
            'remarks' => $data['remarks'] ?? ($data['notes'] ?? '1-on-1 P2P Meeting'),
            'media' => (array) ($data['media'] ?? []),
            'is_deleted' => false,
        ]);

        return [
            'meeting_id' => (string) $meeting->id,
            'status' => 'Confirmed',
        ];
    }

    /**
     * Log a business deal closed between peers.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createBusinessDeal(User $user, array $data): array
    {
        $targetPeerId = (string) ($data['to_peer_id'] ?? ($data['peer_user_id'] ?? ($data['peer_id'] ?? '')));
        $targetUser = User::query()->where('id', $targetPeerId)->first();
        $targetUserId = $targetUser ? (string) $targetUser->id : $targetPeerId;

        $deal = BusinessDeal::create([
            'from_user_id' => $user->id,
            'to_user_id' => $targetUserId,
            'deal_date' => $data['deal_date'] ?? now()->toDateString(),
            'deal_amount' => (float) ($data['amount'] ?? ($data['deal_amount'] ?? 0)),
            'business_type' => $data['business_type'] ?? 'new_business',
            'comment' => $data['comment'] ?? ($data['notes'] ?? ''),
            'referral_id' => ! empty($data['referral_id']) ? (string) $data['referral_id'] : null,
        ]);

        return [
            'deal_id' => (string) $deal->id,
            'status' => 'Recorded',
        ];
    }

    /**
     * Submit a testimonial for a peer.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createTestimonial(User $user, array $data): array
    {
        $targetPeerId = (string) ($data['to_peer_id'] ?? ($data['peer_user_id'] ?? ($data['peer_id'] ?? '')));
        $targetUser = User::query()->where('id', $targetPeerId)->first();
        $targetUserId = $targetUser ? (string) $targetUser->id : $targetPeerId;

        $testimonial = Testimonial::create([
            'from_user_id' => $user->id,
            'to_user_id' => $targetUserId,
            'content' => (string) ($data['content'] ?? ''),
            'rating' => (int) ($data['rating'] ?? 5),
            'media' => (array) ($data['media'] ?? []),
            'referral_id' => ! empty($data['referral_id']) ? (string) $data['referral_id'] : null,
        ]);

        return [
            'testimonial_id' => (string) $testimonial->id,
            'status' => 'Published',
        ];
    }

    /**
     * Resolve scoped user IDs for activity queries.
     * Returns null when the leader has global scope and no specific circle filter is passed.
     *
     * @return array<int, string>|null
     */
    private function resolveActivityScopedUserIds(Request $request): ?array
    {
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $districtId = $request->query('district_id') ? (string) $request->query('district_id') : null;
        $user = $request->user();

        $dashboardService = app(LeaderDashboardService::class);
        $scope = $dashboardService->resolveLeaderScope($circleId, $districtId, $user);

        return $scope['scoped_member_user_ids'];
    }

    /**
     * Build a standardized activity feed item with peer context.
     *
     * @param  array<string, mixed>|null  $fromPeer
     * @param  array<string, mixed>|null  $toPeer
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function formatStandardActivityItem(
        string $id,
        string $type,
        string $title,
        string $subtitle,
        string $description,
        string $status,
        string $dateStr,
        string $createdAtIso,
        ?array $fromPeer = null,
        ?array $toPeer = null,
        ?string $badgeLabel = null,
        ?string $amount = null,
        array $extra = [],
    ): array {
        return array_merge([
            'id' => $id,
            'type' => $type,
            'title' => $title,
            'subtitle' => $subtitle,
            'description' => $description,
            'status' => $status,
            'date' => $dateStr,
            'created_at' => $createdAtIso,
            'badge_label' => $badgeLabel,
            'amount' => $amount,
            'peer_user_id' => $fromPeer['id'] ?? null,
            'user_id' => $fromPeer['id'] ?? null,
            'peer_id' => $fromPeer['id'] ?? null,
            'peer_name' => $fromPeer['name'] ?? ($extra['fallback_name'] ?? 'Peer Member'),
            'name' => $fromPeer['name'] ?? ($extra['fallback_name'] ?? 'Peer Member'),
            'profile_image' => $fromPeer['profile_image'] ?? null,
            'profile_photo_url' => $fromPeer['profile_image'] ?? null,
            'avatar_url' => $fromPeer['profile_image'] ?? null,
            'city' => $fromPeer['city'] ?? ($extra['fallback_city'] ?? 'Ahmedabad'),
            'location' => $fromPeer['city'] ?? ($extra['fallback_city'] ?? 'Ahmedabad'),
            'designation' => $fromPeer['designation'] ?? ($extra['fallback_designation'] ?? 'Member'),
            'business_name' => $fromPeer['business_name'] ?? ($extra['fallback_company'] ?? 'Enterprise Services'),
            'company_name' => $fromPeer['company_name'] ?? ($extra['fallback_company'] ?? 'Enterprise Services'),
            'company' => $fromPeer['company_name'] ?? ($extra['fallback_company'] ?? 'Enterprise Services'),
            'category_level4' => $fromPeer['category_level4'] ?? ($extra['fallback_category'] ?? 'Business Services'),
            'level_4_category' => $fromPeer['category_level4'] ?? ($extra['fallback_category'] ?? 'Business Services'),
            'level4_category' => $fromPeer['category_level4'] ?? ($extra['fallback_category'] ?? 'Business Services'),
            'category' => $fromPeer['category_level4'] ?? ($extra['fallback_category'] ?? 'Business Services'),
            'circle_name' => $fromPeer['circle_name'] ?? '',
            'from_peer' => $fromPeer,
            'peer' => $fromPeer,
            'to_peer' => $toPeer,
        ], $extra);
    }

    /**
     * Load a map of User models keyed by string ID for batch peer formatting.
     *
     * @param  array<int, mixed>  $userIds
     * @return array<string, array<string, mixed>>
     */
    private function loadFormattedPeersMap(array $userIds): array
    {
        $cleanIds = array_values(array_unique(array_filter(
            array_map(fn ($id) => trim((string) $id), $userIds),
            fn (string $id) => $id !== '' && Str::isUuid($id)
        )));

        if (empty($cleanIds)) {
            return [];
        }

        $users = User::query()
            ->whereIn('id', $cleanIds)
            ->with([
                'circleMembers.circle',
                'circleMembers.level4Category',
                'activeCircle',
                'businessCategory',
                'level4Category',
            ])
            ->get();

        $map = [];
        foreach ($users as $u) {
            $formatted = $this->formatPeerDetail($u);
            if ($formatted) {
                $map[(string) $u->id] = $formatted;
            }
        }

        return $map;
    }

    /**
     * Get all scoped peers ranked top-to-bottom (#1, #2, #3...) by their count/volume
     * for the requested activity type.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRankedPeersByActivityType(Request $request, string $type): array
    {
        $normalizedType = strtolower(trim($type));
        $search = $request->query('search') ? trim((string) $request->query('search')) : null;
        $scopedUserIds = $this->resolveActivityScopedUserIds($request);

        if (is_array($scopedUserIds) && empty($scopedUserIds)) {
            return [];
        }

        $userQuery = User::query()
            ->whereNull('deleted_at')
            ->with([
                'circleMembers.circle',
                'circleMembers.level4Category',
                'activeCircle',
                'businessCategory',
                'level4Category',
            ]);

        if ($scopedUserIds !== null) {
            $userQuery->whereIn('id', $scopedUserIds);
        }

        if ($search) {
            $userQuery->where(function ($q) use ($search): void {
                $q->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('display_name', 'ilike', "%{$search}%")
                    ->orWhere('company_name', 'ilike', "%{$search}%")
                    ->orWhere('city', 'ilike', "%{$search}%");
            });
        }

        $users = $userQuery->get();
        if ($users->isEmpty()) {
            return [];
        }

        $userIds = $users->pluck('id')->map(fn ($id) => (string) $id)->all();
        $countsByUser = [];
        $amountsByUser = [];
        $unitLabel = 'Activities';

        $addCount = function (?string $uid, int $delta = 1) use (&$countsByUser): void {
            if (! $uid || $uid === '') {
                return;
            }
            $countsByUser[$uid] = ($countsByUser[$uid] ?? 0) + $delta;
        };

        switch ($normalizedType) {
            case 'impacts':
            case 'impact':
            case 'life-impacts':
            case 'top-impacters':
                $unitLabel = 'Impacts';
                if (Schema::hasTable('impacts')) {
                    $rows = DB::table('impacts')
                        ->whereIn('user_id', $userIds)
                        ->selectRaw('user_id, COUNT(*) as cnt, COALESCE(SUM(life_impacted), COUNT(*)) as total_lives')
                        ->groupBy('user_id')
                        ->get();
                    foreach ($rows as $r) {
                        $countsByUser[(string) $r->user_id] = max((int) $r->cnt, (int) $r->total_lives);
                    }
                }
                foreach ($users as $u) {
                    $uid = (string) $u->id;
                    $colVal = (int) ($u->life_impacted_count ?? 0);
                    if ($colVal > ($countsByUser[$uid] ?? 0)) {
                        $countsByUser[$uid] = $colVal;
                    }
                }
                break;

            case 'business-deals':
            case 'deals':
                $unitLabel = 'Deals';
                if (Schema::hasTable('business_deals')) {
                    $dealRows = DB::table('business_deals')
                        ->whereNull('deleted_at')
                        ->where(function ($q) use ($userIds): void {
                            $q->whereIn('from_user_id', $userIds)
                                ->orWhereIn('to_user_id', $userIds);
                        })
                        ->get(['from_user_id', 'to_user_id', 'deal_amount']);
                    foreach ($dealRows as $dr) {
                        $amt = (float) ($dr->deal_amount ?? 0);
                        $fromId = (string) ($dr->from_user_id ?? '');
                        $toId = (string) ($dr->to_user_id ?? '');
                        if ($fromId !== '') {
                            $addCount($fromId, 1);
                            $amountsByUser[$fromId] = ($amountsByUser[$fromId] ?? 0.0) + $amt;
                        }
                        if ($toId !== '' && $toId !== $fromId) {
                            $addCount($toId, 1);
                            $amountsByUser[$toId] = ($amountsByUser[$toId] ?? 0.0) + $amt;
                        }
                    }
                }
                break;

            case 'p2p-meetings':
            case 'peer-meetings':
            case 'p2p':
                $unitLabel = 'Meetings';
                if (Schema::hasTable('p2p_meetings')) {
                    $mRows = DB::table('p2p_meetings')
                        ->whereNull('deleted_at')
                        ->where(function ($q) use ($userIds): void {
                            $q->whereIn('initiator_user_id', $userIds)
                                ->orWhereIn('peer_user_id', $userIds);
                        })
                        ->get(['initiator_user_id', 'peer_user_id']);
                    foreach ($mRows as $mr) {
                        $initId = (string) ($mr->initiator_user_id ?? '');
                        $peerId = (string) ($mr->peer_user_id ?? '');
                        if ($initId !== '') {
                            $addCount($initId, 1);
                        }
                        if ($peerId !== '' && $peerId !== $initId) {
                            $addCount($peerId, 1);
                        }
                    }
                }
                break;

            case 'referrals':
            case 'referral':
                $unitLabel = 'Referrals';
                if (Schema::hasTable('referrals')) {
                    $rRows = DB::table('referrals')
                        ->whereNull('deleted_at')
                        ->where(function ($q) use ($userIds): void {
                            $q->whereIn('from_user_id', $userIds)
                                ->orWhereIn('to_user_id', $userIds);
                        })
                        ->get(['from_user_id', 'to_user_id']);
                    foreach ($rRows as $rr) {
                        $addCount((string) ($rr->from_user_id ?? ''), 1);
                    }
                }
                break;

            case 'testimonials':
            case 'endorsements':
                $unitLabel = 'Endorsements';
                if (Schema::hasTable('testimonials')) {
                    $tRows = DB::table('testimonials')
                        ->whereNull('deleted_at')
                        ->where(function ($q) use ($userIds): void {
                            $q->whereIn('from_user_id', $userIds)
                                ->orWhereIn('to_user_id', $userIds);
                        })
                        ->get(['from_user_id', 'to_user_id']);
                    foreach ($tRows as $tr) {
                        $fromId = (string) ($tr->from_user_id ?? '');
                        $toId = (string) ($tr->to_user_id ?? '');
                        if ($fromId !== '') {
                            $addCount($fromId, 1);
                        }
                        if ($toId !== '' && $toId !== $fromId) {
                            $addCount($toId, 1);
                        }
                    }
                }
                break;

            case 'requirements':
            case 'requirement':
                $unitLabel = 'Requirements';
                if (Schema::hasTable('requirements')) {
                    $rows = DB::table('requirements')
                        ->whereIn('user_id', $userIds)
                        ->selectRaw('user_id, COUNT(*) as cnt')
                        ->groupBy('user_id')
                        ->get();
                    foreach ($rows as $r) {
                        $countsByUser[(string) $r->user_id] = (int) $r->cnt;
                    }
                }
                break;

            case 'coins':
            case 'peers-by-coins':
                $unitLabel = 'Coins';
                foreach ($users as $u) {
                    $countsByUser[(string) $u->id] = (int) ($u->coins_balance ?? 0);
                }
                break;

            case 'visitor-registrations':
            case 'visitors':
                $unitLabel = 'Visitors';
                if (Schema::hasTable('visitor_registrations')) {
                    $rows = DB::table('visitor_registrations')
                        ->whereIn('user_id', $userIds)
                        ->selectRaw('user_id, COUNT(*) as cnt')
                        ->groupBy('user_id')
                        ->get();
                    foreach ($rows as $r) {
                        $countsByUser[(string) $r->user_id] = (int) $r->cnt;
                    }
                }
                break;

            case 'peer-recommendations':
            case 'recommendations':
                $unitLabel = 'Recommended';
                if (Schema::hasTable('peer_recommendations')) {
                    $rows = DB::table('peer_recommendations')
                        ->whereIn('user_id', $userIds)
                        ->selectRaw('user_id, COUNT(*) as cnt')
                        ->groupBy('user_id')
                        ->get();
                    foreach ($rows as $r) {
                        $countsByUser[(string) $r->user_id] = (int) $r->cnt;
                    }
                }
                break;

            case 'introduced-peers':
            case 'community-builders':
                $unitLabel = 'Introduced';
                if (Schema::hasColumn('users', 'introduced_by')) {
                    $rows = DB::table('users')
                        ->whereNull('deleted_at')
                        ->whereIn('introduced_by', $userIds)
                        ->selectRaw('introduced_by, COUNT(*) as cnt')
                        ->groupBy('introduced_by')
                        ->get();
                    foreach ($rows as $r) {
                        $countsByUser[(string) $r->introduced_by] = (int) $r->cnt;
                    }
                }
                break;

            case 'collaboration-asks':
            case 'collaborations':
            case 'referral-asks':
            case 'help-asks':
            case 'need-help':
                $flowCode = str_starts_with($normalizedType, 'collab')
                    ? 'collaboration'
                    : (str_starts_with($normalizedType, 'referral') ? 'referral' : 'help');
                $unitLabel = ucfirst($flowCode).' Asks';
                if (Schema::hasTable('asks') && Schema::hasTable('ask_flows')) {
                    $flowId = DB::table('ask_flows')->where('code', $flowCode)->value('id');
                    if ($flowId) {
                        $rows = DB::table('asks')
                            ->where('flow_id', $flowId)
                            ->whereIn('creator_user_id', $userIds)
                            ->selectRaw('creator_user_id, COUNT(*) as cnt')
                            ->groupBy('creator_user_id')
                            ->get();
                        foreach ($rows as $r) {
                            $addCount((string) $r->creator_user_id, (int) $r->cnt);
                        }
                    }
                }
                if ($flowCode === 'collaboration' && Schema::hasTable('collaboration_posts')) {
                    $cpRows = DB::table('collaboration_posts')
                        ->whereIn('user_id', $userIds)
                        ->selectRaw('user_id, COUNT(*) as cnt')
                        ->groupBy('user_id')
                        ->get();
                    foreach ($cpRows as $r) {
                        $addCount((string) $r->user_id, (int) $r->cnt);
                    }
                }
                break;

            case 'posts':
            case 'timeline-posts':
                $unitLabel = 'Posts';
                if (Schema::hasTable('posts')) {
                    $rows = DB::table('posts')
                        ->whereIn('user_id', $userIds)
                        ->selectRaw('user_id, COUNT(*) as cnt')
                        ->groupBy('user_id')
                        ->get();
                    foreach ($rows as $r) {
                        $countsByUser[(string) $r->user_id] = (int) $r->cnt;
                    }
                }
                break;

            case 'creatives':
            case 'activity-creatives':
                $unitLabel = 'Creatives';
                if (Schema::hasTable('activity_creatives')) {
                    $rows = DB::table('activity_creatives')
                        ->whereIn('user_id', $userIds)
                        ->selectRaw('user_id, COUNT(*) as cnt')
                        ->groupBy('user_id')
                        ->get();
                    foreach ($rows as $r) {
                        $countsByUser[(string) $r->user_id] = (int) $r->cnt;
                    }
                }
                break;

            case 'coin-claims':
            case 'claim-coins':
                $unitLabel = 'Claims';
                if (Schema::hasTable('coin_claim_requests')) {
                    $rows = DB::table('coin_claim_requests')
                        ->whereIn('user_id', $userIds)
                        ->selectRaw('user_id, COUNT(*) as cnt')
                        ->groupBy('user_id')
                        ->get();
                    foreach ($rows as $r) {
                        $countsByUser[(string) $r->user_id] = (int) $r->cnt;
                    }
                }
                break;

            case 'stories':
            case 'business-stories':
            case 'vyapaar-jagat':
                $unitLabel = 'Stories';
                if (Schema::hasTable('sme_business_story_submissions')) {
                    $rows = DB::table('sme_business_story_submissions')
                        ->whereIn('user_id', $userIds)
                        ->selectRaw('user_id, COUNT(*) as cnt')
                        ->groupBy('user_id')
                        ->get();
                    foreach ($rows as $r) {
                        $countsByUser[(string) $r->user_id] = (int) $r->cnt;
                    }
                }
                break;

            case 'leadership-interests':
            case 'leader-interests':
                $unitLabel = 'Submissions';
                if (Schema::hasTable('leader_interest_submissions')) {
                    $rows = DB::table('leader_interest_submissions')
                        ->whereIn('user_id', $userIds)
                        ->selectRaw('user_id, COUNT(*) as cnt')
                        ->groupBy('user_id')
                        ->get();
                    foreach ($rows as $r) {
                        $countsByUser[(string) $r->user_id] = (int) $r->cnt;
                    }
                }
                break;

            case 'certifications':
            case 'certificates':
                $unitLabel = 'Certificates';
                if (Schema::hasTable('certification_submissions')) {
                    $rows = DB::table('certification_submissions')
                        ->whereIn('user_id', $userIds)
                        ->selectRaw('user_id, COUNT(*) as cnt')
                        ->groupBy('user_id')
                        ->get();
                    foreach ($rows as $r) {
                        $countsByUser[(string) $r->user_id] = (int) $r->cnt;
                    }
                }
                break;

            case 'event-registrations':
            case 'events':
            case 'event-attendance':
            case 'attendance':
                $checkedInOnly = in_array($normalizedType, ['event-attendance', 'attendance'], true);
                $unitLabel = $checkedInOnly ? 'Attended' : 'Events';
                if (Schema::hasTable('event_registrations')) {
                    $eq = DB::table('event_registrations')->whereIn('user_id', $userIds);
                    if ($checkedInOnly && Schema::hasColumn('event_registrations', 'checkin_status')) {
                        $eq->where('checkin_status', 'checked_in');
                    }
                    $rows = $eq->selectRaw('user_id, COUNT(*) as cnt')->groupBy('user_id')->get();
                    foreach ($rows as $r) {
                        $countsByUser[(string) $r->user_id] = (int) $r->cnt;
                    }
                }
                break;

            default:
                $unitLabel = 'Impacts';
                foreach ($users as $u) {
                    $countsByUser[(string) $u->id] = (int) ($u->life_impacted_count ?? 0);
                }
                break;
        }

        $formattedPeers = [];
        foreach ($users as $u) {
            $uid = (string) $u->id;
            $base = $this->formatPeerDetail($u);
            if (! $base) {
                continue;
            }
            $cnt = (int) ($countsByUser[$uid] ?? 0);
            $amt = (float) ($amountsByUser[$uid] ?? 0.0);
            $amtFormatted = $amt >= 10000000
                ? '₹'.round($amt / 10000000, 2).'Cr'
                : ($amt >= 100000 ? '₹'.round($amt / 100000, 1).'L' : ($amt > 0 ? '₹'.number_format($amt, 0) : ''));

            $formattedPeers[] = array_merge($base, [
                'metric_count' => $cnt,
                'metric_amount' => $amt,
                'metric_label' => $unitLabel,
                'metric_display' => $amtFormatted !== '' ? $amtFormatted : "{$cnt}",
                'coins' => (int) ($u->coins_balance ?? 0),
            ]);
        }

        usort($formattedPeers, function (array $a, array $b): int {
            $amtCmp = ($b['metric_amount'] ?? 0.0) <=> ($a['metric_amount'] ?? 0.0);
            if ($amtCmp !== 0) {
                return $amtCmp;
            }
            $cntCmp = ($b['metric_count'] ?? 0) <=> ($a['metric_count'] ?? 0);
            if ($cntCmp !== 0) {
                return $cntCmp;
            }
            $impCmp = ($b['life_impacted_count'] ?? 0) <=> ($a['life_impacted_count'] ?? 0);
            if ($impCmp !== 0) {
                return $impCmp;
            }

            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        foreach ($formattedPeers as $idx => &$peerRow) {
            $peerRow['rank'] = $idx + 1;
        }
        unset($peerRow);

        return $formattedPeers;
    }

    /**
     * Get unified list of user-input activities by type for Leader App.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActivitiesByType(Request $request, string $type): array
    {
        $normalizedType = strtolower(trim($type));
        $search = $request->query('search') ? trim((string) $request->query('search')) : null;
        $limit = min(max((int) ($request->query('limit', 50)), 1), 100);
        $scopedUserIds = $this->resolveActivityScopedUserIds($request);

        if (is_array($scopedUserIds) && empty($scopedUserIds)) {
            return [];
        }

        return match ($normalizedType) {
            'impacts', 'impact', 'life-impacts' => $this->getImpacts($request),
            'p2p-meetings', 'peer-meetings', 'p2p' => $this->getP2pMeetings($request),
            'business-deals', 'deals' => $this->getBusinessDeals($request),
            'referrals', 'referral' => $this->getReferrals($request),
            'testimonials', 'endorsements' => $this->getTestimonials($request),
            'requirements', 'requirement' => $this->getRequirements($request),
            'coins', 'peers-by-coins' => $this->getPeersByCoins($request),
            'visitor-registrations', 'visitors' => $this->getVisitorRegistrationsFeed($scopedUserIds, $search, $limit),
            'peer-recommendations', 'recommendations' => $this->getPeerRecommendationsFeed($scopedUserIds, $search, $limit),
            'introduced-peers', 'community-builders' => $this->getIntroducedPeersFeed($scopedUserIds, $search, $limit),
            'collaboration-asks', 'collaborations' => $this->getAskFlowFeed('collaboration', $scopedUserIds, $search, $limit),
            'referral-asks' => $this->getAskFlowFeed('referral', $scopedUserIds, $search, $limit),
            'help-asks', 'need-help' => $this->getAskFlowFeed('help', $scopedUserIds, $search, $limit),
            'posts', 'timeline-posts' => $this->getPostsFeed($scopedUserIds, $search, $limit),
            'creatives', 'activity-creatives' => $this->getCreativesFeed($scopedUserIds, $search, $limit),
            'coin-claims', 'claim-coins' => $this->getCoinClaimsFeed($scopedUserIds, $search, $limit),
            'stories', 'business-stories', 'vyapaar-jagat' => $this->getStoriesFeed($scopedUserIds, $search, $limit),
            'leadership-interests', 'leader-interests' => $this->getLeadershipInterestsFeed($scopedUserIds, $search, $limit),
            'mentor-applications', 'mentors' => $this->getMentorApplicationsFeed($scopedUserIds, $search, $limit),
            'speaker-applications', 'speakers' => $this->getSpeakerApplicationsFeed($scopedUserIds, $search, $limit),
            'partner-inquiries', 'partners' => $this->getPartnerInquiriesFeed($scopedUserIds, $search, $limit),
            'certifications', 'certificates' => $this->getCertificationsFeed($scopedUserIds, $search, $limit),
            'event-registrations', 'events' => $this->getEventRegistrationsFeed($scopedUserIds, $search, $limit, false),
            'event-attendance', 'attendance' => $this->getEventRegistrationsFeed($scopedUserIds, $search, $limit, true),
            default => [],
        };
    }

    /**
     * @param  array<int, string>|null  $scopedUserIds
     * @return array<int, array<string, mixed>>
     */
    private function getVisitorRegistrationsFeed(?array $scopedUserIds, ?string $search, int $limit): array
    {
        if (! Schema::hasTable('visitor_registrations')) {
            return [];
        }

        $query = DB::table('visitor_registrations');
        if ($scopedUserIds !== null) {
            $query->where(function ($q) use ($scopedUserIds): void {
                $q->whereIn('user_id', $scopedUserIds);
                if (Schema::hasColumn('visitor_registrations', 'invited_by_user_id')) {
                    $q->orWhereIn('invited_by_user_id', $scopedUserIds);
                }
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('visitor_full_name', 'ilike', "%{$search}%")
                    ->orWhere('visitor_business', 'ilike', "%{$search}%")
                    ->orWhere('visitor_city', 'ilike', "%{$search}%")
                    ->orWhere('event_name', 'ilike', "%{$search}%");
            });
        }

        $rows = $query->orderByDesc('created_at')->limit($limit)->get();
        $peersMap = $this->loadFormattedPeersMap($rows->pluck('user_id')->merge($rows->pluck('invited_by_user_id'))->all());

        return $rows->map(function ($row) use ($peersMap): array {
            $uid = (string) ($row->user_id ?? $row->invited_by_user_id ?? '');
            $peer = $peersMap[$uid] ?? null;
            $dateStr = ! empty($row->event_date)
                ? substr((string) $row->event_date, 0, 10)
                : (! empty($row->created_at) ? substr((string) $row->created_at, 0, 10) : date('Y-m-d'));
            $visitorName = (string) ($row->visitor_full_name ?? 'Guest Visitor');
            $visitorBusiness = (string) ($row->visitor_business ?? '');
            $visitorCity = (string) ($row->visitor_city ?? '');
            $eventName = (string) ($row->event_name ?? 'Circle Event');

            $subtitleParts = array_filter([$visitorBusiness, $visitorCity]);
            $subtitle = ! empty($subtitleParts) ? implode(' · ', $subtitleParts) : $eventName;
            $desc = trim((string) ($row->note ?? ''));
            if ($desc === '') {
                $desc = "Visitor {$visitorName} registered for {$eventName}.";
            }

            return $this->formatStandardActivityItem(
                id: (string) $row->id,
                type: 'visitor-registrations',
                title: "Visitor: {$visitorName}",
                subtitle: $subtitle,
                description: $desc,
                status: ucfirst((string) ($row->status ?? 'Registered')),
                dateStr: $dateStr,
                createdAtIso: (string) ($row->created_at ?? now()->toIso8601String()),
                fromPeer: $peer,
                badgeLabel: $eventName,
                extra: [
                    'visitor_name' => $visitorName,
                    'visitor_mobile' => (string) ($row->visitor_mobile ?? ''),
                    'visitor_email' => (string) ($row->visitor_email ?? ''),
                    'visitor_business' => $visitorBusiness,
                    'visitor_city' => $visitorCity,
                    'event_name' => $eventName,
                ],
            );
        })->values()->all();
    }

    /**
     * @param  array<int, string>|null  $scopedUserIds
     * @return array<int, array<string, mixed>>
     */
    private function getPeerRecommendationsFeed(?array $scopedUserIds, ?string $search, int $limit): array
    {
        if (! Schema::hasTable('peer_recommendations')) {
            return [];
        }

        $query = DB::table('peer_recommendations');
        if ($scopedUserIds !== null) {
            $query->whereIn('user_id', $scopedUserIds);
        }

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('peer_name', 'ilike', "%{$search}%")
                    ->orWhere('peer_business', 'ilike', "%{$search}%")
                    ->orWhere('peer_city', 'ilike', "%{$search}%")
                    ->orWhere('why_valuable', 'ilike', "%{$search}%");
            });
        }

        $rows = $query->orderByDesc('created_at')->limit($limit)->get();
        $peersMap = $this->loadFormattedPeersMap($rows->pluck('user_id')->all());

        return $rows->map(function ($row) use ($peersMap): array {
            $peer = $peersMap[(string) ($row->user_id ?? '')] ?? null;
            $dateStr = ! empty($row->created_at) ? substr((string) $row->created_at, 0, 10) : date('Y-m-d');
            $recName = (string) ($row->peer_name ?? 'Recommended Entrepreneur');
            $recBusiness = (string) ($row->peer_business ?? ($row->peer_industry ?? ''));
            $recCity = (string) ($row->peer_city ?? '');
            $subtitleParts = array_filter([$recBusiness, $recCity]);

            return $this->formatStandardActivityItem(
                id: (string) $row->id,
                type: 'peer-recommendations',
                title: "Recommended: {$recName}",
                subtitle: ! empty($subtitleParts) ? implode(' · ', $subtitleParts) : 'Peer Recommendation',
                description: (string) ($row->why_valuable ?? "Recommended {$recName} to join the Peers Global community."),
                status: ucfirst((string) ($row->status ?? 'Submitted')),
                dateStr: $dateStr,
                createdAtIso: (string) ($row->created_at ?? now()->toIso8601String()),
                fromPeer: $peer,
                badgeLabel: (string) ($row->circle_name ?? ($row->peer_industry ?? 'Recommendation')),
                extra: [
                    'recommended_peer_name' => $recName,
                    'recommended_peer_mobile' => (string) ($row->peer_mobile ?? ''),
                    'recommended_peer_email' => (string) ($row->peer_email ?? ''),
                    'recommended_peer_city' => $recCity,
                    'recommended_peer_business' => $recBusiness,
                ],
            );
        })->values()->all();
    }

    /**
     * @param  array<int, string>|null  $scopedUserIds
     * @return array<int, array<string, mixed>>
     */
    private function getIntroducedPeersFeed(?array $scopedUserIds, ?string $search, int $limit): array
    {
        if (! Schema::hasColumn('users', 'introduced_by')) {
            return [];
        }

        $query = User::query()
            ->whereNull('deleted_at')
            ->whereNotNull('introduced_by')
            ->with([
                'circleMembers.circle',
                'circleMembers.level4Category',
                'activeCircle',
                'businessCategory',
                'level4Category',
            ]);

        if ($scopedUserIds !== null) {
            $query->where(function ($q) use ($scopedUserIds): void {
                $q->whereIn('introduced_by', $scopedUserIds)
                    ->orWhereIn('id', $scopedUserIds);
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('display_name', 'ilike', "%{$search}%")
                    ->orWhere('company_name', 'ilike', "%{$search}%")
                    ->orWhere('city', 'ilike', "%{$search}%");
            });
        }

        $users = $query->orderByDesc('created_at')->limit($limit)->get();
        $introducersMap = $this->loadFormattedPeersMap($users->pluck('introduced_by')->all());

        return $users->map(function (User $newPeer) use ($introducersMap): array {
            $introducedPeer = $this->formatPeerDetail($newPeer);
            $introducer = $introducersMap[(string) ($newPeer->introduced_by ?? '')] ?? $introducedPeer;
            $dateStr = $newPeer->created_at ? $newPeer->created_at->format('Y-m-d') : date('Y-m-d');
            $newPeerName = $introducedPeer['name'] ?? 'New Peer Member';

            return $this->formatStandardActivityItem(
                id: (string) $newPeer->id,
                type: 'introduced-peers',
                title: "Introduced {$newPeerName}",
                subtitle: ($introducedPeer['company_name'] ?? 'Enterprise').' · '.($introducedPeer['city'] ?? 'Ahmedabad'),
                description: ($introducer['name'] ?? 'A Peer')." introduced {$newPeerName} to the community.",
                status: 'Joined',
                dateStr: $dateStr,
                createdAtIso: $newPeer->created_at ? $newPeer->created_at->toIso8601String() : now()->toIso8601String(),
                fromPeer: $introducer,
                toPeer: $introducedPeer,
                badgeLabel: $introducedPeer['circle_name'] ?: 'Community Builder',
            );
        })->values()->all();
    }

    /**
     * @param  array<int, string>|null  $scopedUserIds
     * @return array<int, array<string, mixed>>
     */
    private function getAskFlowFeed(string $flowCode, ?array $scopedUserIds, ?string $search, int $limit): array
    {
        $items = [];

        if (Schema::hasTable('asks') && Schema::hasTable('ask_flows')) {
            $flowId = DB::table('ask_flows')->where('code', $flowCode)->value('id');
            if ($flowId) {
                $query = DB::table('asks')->where('flow_id', $flowId);
                if (Schema::hasColumn('asks', 'deleted_at')) {
                    $query->whereNull('deleted_at');
                }
                if ($scopedUserIds !== null) {
                    $query->whereIn('creator_user_id', $scopedUserIds);
                }
                if ($search) {
                    $query->where(function ($q) use ($search): void {
                        $q->where('title', 'ilike', "%{$search}%")
                            ->orWhere('description', 'ilike', "%{$search}%");
                    });
                }

                $rows = $query->orderByDesc('created_at')->limit($limit)->get();
                $peersMap = $this->loadFormattedPeersMap($rows->pluck('creator_user_id')->all());

                foreach ($rows as $row) {
                    $peer = $peersMap[(string) ($row->creator_user_id ?? '')] ?? null;
                    $dateStr = ! empty($row->created_at) ? substr((string) $row->created_at, 0, 10) : date('Y-m-d');
                    $items[] = $this->formatStandardActivityItem(
                        id: (string) $row->id,
                        type: "{$flowCode}-asks",
                        title: (string) ($row->title ?? ucfirst($flowCode).' Ask'),
                        subtitle: (string) ($row->visibility ?? 'Global Community'),
                        description: (string) ($row->description ?? ''),
                        status: ucfirst((string) ($row->status ?? 'Open')),
                        dateStr: $dateStr,
                        createdAtIso: (string) ($row->created_at ?? now()->toIso8601String()),
                        fromPeer: $peer,
                        badgeLabel: ucfirst($flowCode).' Ask',
                    );
                }
            }
        }

        if ($flowCode === 'collaboration' && Schema::hasTable('collaboration_posts') && count($items) < $limit) {
            $cpQuery = DB::table('collaboration_posts');
            if (Schema::hasColumn('collaboration_posts', 'deleted_at')) {
                $cpQuery->whereNull('deleted_at');
            }
            if ($scopedUserIds !== null) {
                $cpQuery->whereIn('user_id', $scopedUserIds);
            }
            if ($search) {
                $cpQuery->where(function ($q) use ($search): void {
                    $q->where('title', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%")
                        ->orWhere('collaboration_type', 'ilike', "%{$search}%");
                });
            }

            $cpRows = $cpQuery->orderByDesc('created_at')->limit($limit - count($items))->get();
            $cpPeersMap = $this->loadFormattedPeersMap($cpRows->pluck('user_id')->all());

            foreach ($cpRows as $row) {
                $peer = $cpPeersMap[(string) ($row->user_id ?? '')] ?? null;
                $dateStr = ! empty($row->created_at) ? substr((string) $row->created_at, 0, 10) : date('Y-m-d');
                $items[] = $this->formatStandardActivityItem(
                    id: (string) $row->id,
                    type: 'collaboration-asks',
                    title: (string) ($row->title ?? 'Business Collaboration'),
                    subtitle: (string) ($row->collaboration_type ?? ($row->industry ?? 'Collaboration')),
                    description: (string) ($row->description ?? ''),
                    status: ucfirst((string) ($row->status ?? 'Active')),
                    dateStr: $dateStr,
                    createdAtIso: (string) ($row->created_at ?? now()->toIso8601String()),
                    fromPeer: $peer,
                    badgeLabel: (string) ($row->collaboration_type ?? 'Collaboration'),
                );
            }
        }

        return $items;
    }

    /**
     * @param  array<int, string>|null  $scopedUserIds
     * @return array<int, array<string, mixed>>
     */
    private function getPostsFeed(?array $scopedUserIds, ?string $search, int $limit): array
    {
        if (! Schema::hasTable('posts')) {
            return [];
        }

        $query = DB::table('posts');
        if (Schema::hasColumn('posts', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        if (Schema::hasColumn('posts', 'is_deleted')) {
            $query->where('is_deleted', false);
        }
        if ($scopedUserIds !== null) {
            $query->whereIn('user_id', $scopedUserIds);
        }
        if ($search) {
            $query->where(function ($q) use ($search): void {
                if (Schema::hasColumn('posts', 'title')) {
                    $q->where('title', 'ilike', "%{$search}%");
                }
                if (Schema::hasColumn('posts', 'content_text')) {
                    $q->orWhere('content_text', 'ilike', "%{$search}%");
                }
                if (Schema::hasColumn('posts', 'description')) {
                    $q->orWhere('description', 'ilike', "%{$search}%");
                }
            });
        }

        $rows = $query->orderByDesc('created_at')->limit($limit)->get();
        $peersMap = $this->loadFormattedPeersMap($rows->pluck('user_id')->all());

        return $rows->map(function ($row) use ($peersMap): array {
            $peer = $peersMap[(string) ($row->user_id ?? '')] ?? null;
            $dateStr = ! empty($row->created_at) ? substr((string) $row->created_at, 0, 10) : date('Y-m-d');
            $content = trim((string) ($row->content_text ?? ($row->description ?? ($row->content ?? ''))));
            $title = trim((string) ($row->title ?? ''));
            if ($title === '') {
                $title = Str::limit($content !== '' ? $content : 'Community Timeline Post', 64);
            }

            return $this->formatStandardActivityItem(
                id: (string) $row->id,
                type: 'posts',
                title: $title,
                subtitle: 'Timeline Post',
                description: $content !== '' ? $content : $title,
                status: ucfirst((string) ($row->status ?? ($row->moderation_status ?? 'Published'))),
                dateStr: $dateStr,
                createdAtIso: (string) ($row->created_at ?? now()->toIso8601String()),
                fromPeer: $peer,
                badgeLabel: 'Post',
            );
        })->values()->all();
    }

    /**
     * @param  array<int, string>|null  $scopedUserIds
     * @return array<int, array<string, mixed>>
     */
    private function getCreativesFeed(?array $scopedUserIds, ?string $search, int $limit): array
    {
        if (! Schema::hasTable('activity_creatives')) {
            return [];
        }

        $query = DB::table('activity_creatives');
        if (Schema::hasColumn('activity_creatives', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        if ($scopedUserIds !== null) {
            $query->whereIn('user_id', $scopedUserIds);
        }
        if ($search) {
            $query->where(function ($q) use ($search): void {
                if (Schema::hasColumn('activity_creatives', 'title')) {
                    $q->where('title', 'ilike', "%{$search}%");
                }
                if (Schema::hasColumn('activity_creatives', 'activity_type')) {
                    $q->orWhere('activity_type', 'ilike', "%{$search}%");
                }
            });
        }

        $rows = $query->orderByDesc('created_at')->limit($limit)->get();
        $peersMap = $this->loadFormattedPeersMap($rows->pluck('user_id')->all());

        return $rows->map(function ($row) use ($peersMap): array {
            $peer = $peersMap[(string) ($row->user_id ?? '')] ?? null;
            $dateStr = ! empty($row->created_at) ? substr((string) $row->created_at, 0, 10) : date('Y-m-d');
            $actType = ucwords(str_replace(['_', '-'], ' ', (string) ($row->activity_type ?? 'Activity Creative')));

            return $this->formatStandardActivityItem(
                id: (string) $row->id,
                type: 'creatives',
                title: (string) ($row->title ?? $actType),
                subtitle: $actType,
                description: (string) ($row->caption ?? ($row->description ?? "Generated creative for {$actType}.")),
                status: 'Published',
                dateStr: $dateStr,
                createdAtIso: (string) ($row->created_at ?? now()->toIso8601String()),
                fromPeer: $peer,
                badgeLabel: $actType,
            );
        })->values()->all();
    }

    /**
     * @param  array<int, string>|null  $scopedUserIds
     * @return array<int, array<string, mixed>>
     */
    private function getCoinClaimsFeed(?array $scopedUserIds, ?string $search, int $limit): array
    {
        if (! Schema::hasTable('coin_claim_requests')) {
            return [];
        }

        $query = DB::table('coin_claim_requests');
        if ($scopedUserIds !== null) {
            $query->whereIn('user_id', $scopedUserIds);
        }
        if ($search) {
            $query->where('activity_code', 'ilike', "%{$search}%");
        }

        $rows = $query->orderByDesc('created_at')->limit($limit)->get();
        $peersMap = $this->loadFormattedPeersMap($rows->pluck('user_id')->all());

        return $rows->map(function ($row) use ($peersMap): array {
            $peer = $peersMap[(string) ($row->user_id ?? '')] ?? null;
            $dateStr = ! empty($row->created_at) ? substr((string) $row->created_at, 0, 10) : date('Y-m-d');
            $activityLabel = ucwords(str_replace(['_', '-'], ' ', (string) ($row->activity_code ?? 'Activity Claim')));
            $coinsAwarded = (int) ($row->coins_awarded ?? 0);

            return $this->formatStandardActivityItem(
                id: (string) $row->id,
                type: 'coin-claims',
                title: "Coin Claim: {$activityLabel}",
                subtitle: $coinsAwarded > 0 ? "+{$coinsAwarded} Coins Awarded" : 'Coins Claim Request',
                description: (string) ($row->admin_notes ?? "Submitted coin claim for {$activityLabel}."),
                status: ucfirst((string) ($row->status ?? 'Pending')),
                dateStr: $dateStr,
                createdAtIso: (string) ($row->created_at ?? now()->toIso8601String()),
                fromPeer: $peer,
                badgeLabel: $coinsAwarded > 0 ? "+{$coinsAwarded} Coins" : $activityLabel,
                amount: $coinsAwarded > 0 ? "+{$coinsAwarded}" : null,
            );
        })->values()->all();
    }

    /**
     * @param  array<int, string>|null  $scopedUserIds
     * @return array<int, array<string, mixed>>
     */
    private function getStoriesFeed(?array $scopedUserIds, ?string $search, int $limit): array
    {
        if (! Schema::hasTable('sme_business_story_submissions')) {
            return [];
        }

        $query = DB::table('sme_business_story_submissions');
        if ($scopedUserIds !== null) {
            $query->whereIn('user_id', $scopedUserIds);
        }
        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('full_name', 'ilike', "%{$search}%")
                    ->orWhere('business_name', 'ilike', "%{$search}%")
                    ->orWhere('title', 'ilike', "%{$search}%")
                    ->orWhere('story', 'ilike', "%{$search}%");
            });
        }

        $rows = $query->orderByDesc('created_at')->limit($limit)->get();
        $peersMap = $this->loadFormattedPeersMap($rows->pluck('user_id')->all());

        return $rows->map(function ($row) use ($peersMap): array {
            $peer = $peersMap[(string) ($row->user_id ?? '')] ?? null;
            $dateStr = ! empty($row->created_at) ? substr((string) $row->created_at, 0, 10) : date('Y-m-d');
            $title = trim((string) ($row->title ?? ''));
            if ($title === '') {
                $title = (string) ($row->business_name ?: 'Business Growth Story');
            }
            $storyText = trim((string) ($row->story ?? ($row->company_introduction ?? ($row->Journey_highlights ?? ''))));

            return $this->formatStandardActivityItem(
                id: (string) $row->id,
                type: 'stories',
                title: $title,
                subtitle: (string) ($row->business_name ?? ($row->full_name ?? 'Vyapaar Jagat Story')),
                description: $storyText !== '' ? $storyText : "Business story submitted by ".($row->full_name ?? 'Peer Member').'.',
                status: ucfirst((string) ($row->status ?? 'Submitted')),
                dateStr: $dateStr,
                createdAtIso: (string) ($row->created_at ?? now()->toIso8601String()),
                fromPeer: $peer,
                badgeLabel: 'Business Story',
                extra: [
                    'fallback_name' => (string) ($row->full_name ?? 'Peer Member'),
                    'fallback_company' => (string) ($row->business_name ?? 'Enterprise'),
                ],
            );
        })->values()->all();
    }

    /**
     * @param  array<int, string>|null  $scopedUserIds
     * @return array<int, array<string, mixed>>
     */
    private function getLeadershipInterestsFeed(?array $scopedUserIds, ?string $search, int $limit): array
    {
        if (! Schema::hasTable('leader_interest_submissions')) {
            return [];
        }

        $query = DB::table('leader_interest_submissions');
        if ($scopedUserIds !== null) {
            $query->whereIn('user_id', $scopedUserIds);
        }
        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('contribute_city', 'ilike', "%{$search}%")
                    ->orWhere('primary_domain', 'ilike', "%{$search}%")
                    ->orWhere('why_interested', 'ilike', "%{$search}%");
            });
        }

        $rows = $query->orderByDesc('created_at')->limit($limit)->get();
        $peersMap = $this->loadFormattedPeersMap($rows->pluck('user_id')->all());

        return $rows->map(function ($row) use ($peersMap): array {
            $peer = $peersMap[(string) ($row->user_id ?? '')] ?? null;
            $dateStr = ! empty($row->created_at) ? substr((string) $row->created_at, 0, 10) : date('Y-m-d');
            $rolesRaw = $row->leadership_roles ?? '';
            if (is_string($rolesRaw) && str_starts_with(trim($rolesRaw), '[')) {
                $decoded = json_decode($rolesRaw, true);
                $rolesText = is_array($decoded) ? implode(', ', $decoded) : $rolesRaw;
            } else {
                $rolesText = (string) $rolesRaw;
            }

            return $this->formatStandardActivityItem(
                id: (string) $row->id,
                type: 'leadership-interests',
                title: $rolesText !== '' ? "Role Interest: {$rolesText}" : 'Leadership Role Interest',
                subtitle: trim(((string) ($row->contribute_city ?? '')).' · '.((string) ($row->primary_domain ?? '')), ' ·') ?: 'Leadership Application',
                description: (string) ($row->why_interested ?? ($row->message ?? 'Expressed interest in taking a leadership role.')),
                status: ucfirst((string) ($row->applying_for ?? 'Submitted')),
                dateStr: $dateStr,
                createdAtIso: (string) ($row->created_at ?? now()->toIso8601String()),
                fromPeer: $peer,
                badgeLabel: (string) ($row->contribute_city ?: 'Leadership'),
            );
        })->values()->all();
    }

    /**
     * @param  array<int, string>|null  $scopedUserIds
     * @return array<int, array<string, mixed>>
     */
    private function getMentorApplicationsFeed(?array $scopedUserIds, ?string $search, int $limit): array
    {
        if (! Schema::hasTable('become_mentor_submissions')) {
            return [];
        }

        $query = DB::table('become_mentor_submissions');
        if ($scopedUserIds !== null) {
            $scopedEmails = User::query()
                ->whereIn('id', $scopedUserIds)
                ->whereNotNull('email')
                ->pluck('email')
                ->map(fn ($e) => strtolower(trim((string) $e)))
                ->filter()
                ->all();
            if (empty($scopedEmails)) {
                return [];
            }
            $query->whereIn(DB::raw('LOWER(TRIM(email))'), $scopedEmails);
        }
        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('city', 'ilike', "%{$search}%");
            });
        }

        $rows = $query->orderByDesc('created_at')->limit($limit)->get();

        return $rows->map(function ($row): array {
            $fullName = trim(((string) ($row->first_name ?? '')).' '.((string) ($row->last_name ?? ''))) ?: 'Mentor Applicant';
            $dateStr = ! empty($row->created_at) ? substr((string) $row->created_at, 0, 10) : date('Y-m-d');

            return $this->formatStandardActivityItem(
                id: (string) $row->id,
                type: 'mentor-applications',
                title: "Mentor Application: {$fullName}",
                subtitle: (string) ($row->city ?? 'Community Mentor'),
                description: (string) ($row->notes ?? "Applied to become a mentor ({$row->email})."),
                status: ucfirst((string) ($row->status ?? 'Submitted')),
                dateStr: $dateStr,
                createdAtIso: (string) ($row->created_at ?? now()->toIso8601String()),
                badgeLabel: 'Mentor',
                extra: [
                    'fallback_name' => $fullName,
                    'fallback_city' => (string) ($row->city ?? 'Ahmedabad'),
                ],
            );
        })->values()->all();
    }

    /**
     * @param  array<int, string>|null  $scopedUserIds
     * @return array<int, array<string, mixed>>
     */
    private function getSpeakerApplicationsFeed(?array $scopedUserIds, ?string $search, int $limit): array
    {
        if (! Schema::hasTable('become_speaker_submissions')) {
            return [];
        }

        $query = DB::table('become_speaker_submissions');
        if ($scopedUserIds !== null) {
            $scopedEmails = User::query()
                ->whereIn('id', $scopedUserIds)
                ->whereNotNull('email')
                ->pluck('email')
                ->map(fn ($e) => strtolower(trim((string) $e)))
                ->filter()
                ->all();
            if (empty($scopedEmails)) {
                return [];
            }
            $query->whereIn(DB::raw('LOWER(TRIM(email))'), $scopedEmails);
        }
        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('company_name', 'ilike', "%{$search}%")
                    ->orWhere('topics_to_speak_on', 'ilike', "%{$search}%");
            });
        }

        $rows = $query->orderByDesc('created_at')->limit($limit)->get();

        return $rows->map(function ($row): array {
            $fullName = trim(((string) ($row->first_name ?? '')).' '.((string) ($row->last_name ?? ''))) ?: 'Speaker Applicant';
            $dateStr = ! empty($row->created_at) ? substr((string) $row->created_at, 0, 10) : date('Y-m-d');

            return $this->formatStandardActivityItem(
                id: (string) $row->id,
                type: 'speaker-applications',
                title: (string) ($row->topics_to_speak_on ?: "Speaker: {$fullName}"),
                subtitle: trim(((string) ($row->company_name ?? '')).' · '.((string) ($row->city ?? '')), ' ·') ?: $fullName,
                description: (string) ($row->brief_bio ?? ($row->topics_to_speak_on ?? "Speaker application by {$fullName}.")),
                status: ucfirst((string) ($row->status ?? 'Submitted')),
                dateStr: $dateStr,
                createdAtIso: (string) ($row->created_at ?? now()->toIso8601String()),
                badgeLabel: 'Speaker',
                extra: [
                    'fallback_name' => $fullName,
                    'fallback_city' => (string) ($row->city ?? 'Ahmedabad'),
                    'fallback_company' => (string) ($row->company_name ?? 'Enterprise'),
                    'fallback_designation' => (string) ($row->designation ?? 'Speaker'),
                ],
            );
        })->values()->all();
    }

    /**
     * @param  array<int, string>|null  $scopedUserIds
     * @return array<int, array<string, mixed>>
     */
    private function getPartnerInquiriesFeed(?array $scopedUserIds, ?string $search, int $limit): array
    {
        if (! Schema::hasTable('partner_with_us_submissions')) {
            return [];
        }

        $query = DB::table('partner_with_us_submissions');
        if ($scopedUserIds !== null) {
            $scopedEmails = User::query()
                ->whereIn('id', $scopedUserIds)
                ->whereNotNull('email')
                ->pluck('email')
                ->map(fn ($e) => strtolower(trim((string) $e)))
                ->filter()
                ->all();
            if (empty($scopedEmails)) {
                return [];
            }
            $query->whereIn(DB::raw('LOWER(TRIM(email_id))'), $scopedEmails);
        }
        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('brand_or_company_name', 'ilike', "%{$search}%")
                    ->orWhere('partnership_goal', 'ilike', "%{$search}%");
            });
        }

        $rows = $query->orderByDesc('created_at')->limit($limit)->get();

        return $rows->map(function ($row): array {
            $fullName = trim(((string) ($row->first_name ?? '')).' '.((string) ($row->last_name ?? ''))) ?: 'Partner Applicant';
            $dateStr = ! empty($row->created_at) ? substr((string) $row->created_at, 0, 10) : date('Y-m-d');
            $company = (string) ($row->brand_or_company_name ?? 'Partner Brand');

            return $this->formatStandardActivityItem(
                id: (string) $row->id,
                type: 'partner-inquiries',
                title: "Partnership: {$company}",
                subtitle: trim(((string) ($row->industry ?? '')).' · '.((string) ($row->city ?? '')), ' ·') ?: $fullName,
                description: (string) ($row->partnership_goal ?? ($row->Why_partner_with_peers_global ?? "Partner inquiry from {$fullName} ({$company}).")),
                status: ucfirst((string) ($row->status ?? 'Submitted')),
                dateStr: $dateStr,
                createdAtIso: (string) ($row->created_at ?? now()->toIso8601String()),
                badgeLabel: 'Partnership',
                extra: [
                    'fallback_name' => $fullName,
                    'fallback_city' => (string) ($row->city ?? 'Ahmedabad'),
                    'fallback_company' => $company,
                    'fallback_category' => (string) ($row->industry ?? 'Business Services'),
                ],
            );
        })->values()->all();
    }

    /**
     * @param  array<int, string>|null  $scopedUserIds
     * @return array<int, array<string, mixed>>
     */
    private function getCertificationsFeed(?array $scopedUserIds, ?string $search, int $limit): array
    {
        if (! Schema::hasTable('certification_submissions')) {
            return [];
        }

        $query = DB::table('certification_submissions');
        if ($scopedUserIds !== null) {
            $query->whereIn('user_id', $scopedUserIds);
        }
        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('full_name', 'ilike', "%{$search}%")
                    ->orWhere('business_name', 'ilike', "%{$search}%")
                    ->orWhere('certification_type', 'ilike', "%{$search}%")
                    ->orWhere('certification_level', 'ilike', "%{$search}%");
            });
        }

        $rows = $query->orderByDesc('created_at')->limit($limit)->get();
        $peersMap = $this->loadFormattedPeersMap($rows->pluck('user_id')->all());

        return $rows->map(function ($row) use ($peersMap): array {
            $peer = $peersMap[(string) ($row->user_id ?? '')] ?? null;
            $dateStr = ! empty($row->created_at) ? substr((string) $row->created_at, 0, 10) : date('Y-m-d');
            $certType = ucwords(str_replace(['_', '-'], ' ', (string) ($row->certification_type ?? 'Certification')));
            $level = (string) ($row->certification_level ?? 'Certified');
            $pct = isset($row->percentage) ? round((float) $row->percentage, 1).'%' : '';

            return $this->formatStandardActivityItem(
                id: (string) $row->id,
                type: 'certifications',
                title: "{$certType} Certification",
                subtitle: trim("{$level} · {$pct}", ' ·'),
                description: "Completed {$certType} assessment".($pct !== '' ? " with a score of {$pct}" : '').'.',
                status: ucfirst((string) ($row->status ?? 'Completed')),
                dateStr: $dateStr,
                createdAtIso: (string) ($row->created_at ?? now()->toIso8601String()),
                fromPeer: $peer,
                badgeLabel: $level ?: 'Certified',
                amount: $pct !== '' ? $pct : null,
                extra: [
                    'fallback_name' => (string) ($row->full_name ?? 'Peer Member'),
                    'fallback_company' => (string) ($row->business_name ?? 'Enterprise'),
                ],
            );
        })->values()->all();
    }

    /**
     * @param  array<int, string>|null  $scopedUserIds
     * @return array<int, array<string, mixed>>
     */
    private function getEventRegistrationsFeed(?array $scopedUserIds, ?string $search, int $limit, bool $checkedInOnly): array
    {
        if (! Schema::hasTable('event_registrations')) {
            return [];
        }

        $query = DB::table('event_registrations');
        if ($scopedUserIds !== null) {
            $query->whereIn('user_id', $scopedUserIds);
        }
        if ($checkedInOnly && Schema::hasColumn('event_registrations', 'checkin_status')) {
            $query->where('checkin_status', 'checked_in');
        }

        $rows = $query->orderByDesc('created_at')->limit($limit)->get();
        $peersMap = $this->loadFormattedPeersMap($rows->pluck('user_id')->all());

        $eventsMap = [];
        if (Schema::hasTable('events')) {
            $eventIds = $rows->pluck('event_id')->filter()->unique()->values()->all();
            if (! empty($eventIds)) {
                $eventsMap = DB::table('events')->whereIn('id', $eventIds)->get()->keyBy('id')->all();
            }
        }

        return $rows->map(function ($row) use ($peersMap, $eventsMap, $checkedInOnly): array {
            $peer = $peersMap[(string) ($row->user_id ?? '')] ?? null;
            $eventObj = $eventsMap[(string) ($row->event_id ?? '')] ?? null;
            $eventTitle = (string) ($eventObj->title ?? ($eventObj->name ?? 'Circle Event'));
            $eventLocation = (string) ($eventObj->location ?? ($eventObj->venue ?? ($peer['city'] ?? 'Ahmedabad')));
            $dateStr = ! empty($row->checkin_at)
                ? substr((string) $row->checkin_at, 0, 10)
                : (! empty($row->created_at) ? substr((string) $row->created_at, 0, 10) : date('Y-m-d'));

            $isCheckedIn = ((string) ($row->checkin_status ?? '')) === 'checked_in';

            return $this->formatStandardActivityItem(
                id: (string) $row->id,
                type: $checkedInOnly ? 'event-attendance' : 'event-registrations',
                title: $eventTitle,
                subtitle: $eventLocation,
                description: $isCheckedIn
                    ? "Checked in and attended {$eventTitle}."
                    : "Registered for {$eventTitle}.",
                status: $isCheckedIn ? 'Checked In' : ucfirst((string) ($row->status ?? 'Registered')),
                dateStr: $dateStr,
                createdAtIso: (string) ($row->created_at ?? now()->toIso8601String()),
                fromPeer: $peer,
                badgeLabel: $isCheckedIn ? 'Attended' : 'Registered',
            );
        })->values()->all();
    }
}
