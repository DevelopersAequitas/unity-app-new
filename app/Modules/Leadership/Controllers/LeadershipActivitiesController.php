<?php

declare(strict_types=1);

namespace App\Modules\Leadership\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\User;
use App\Shared\OrganizationalScope\OrganizationalScopeService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LeadershipActivitiesController extends BaseApiController
{
    public function __construct(
        private readonly OrganizationalScopeService $scopeService,
    ) {}

    /**
     * API 48: Unified Scoped Leadership Activities Feed.
     * GET /api/v1/leadership/activities
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $type = strtolower((string) $request->query('type', 'all'));
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $peerId = $request->query('peer_id') ? (string) $request->query('peer_id') : null;

        if ($circleId && Str::isUuid($circleId) && ! $this->scopeService->canAccessCircle($user, $circleId)) {
            return $this->error('You do not have organizational scope access to view activities for this circle.', 403);
        }

        if ($peerId && Str::isUuid($peerId) && ! $this->scopeService->canAccessPeer($user, $peerId)) {
            return $this->error('You do not have organizational scope access to view activities for this peer.', 403);
        }

        $scopedPeerIds = $this->scopeService->resolveScopedPeerIds($user, 'activities', $circleId);
        if ($peerId && Str::isUuid($peerId)) {
            $scopedPeerIds = [$peerId];
        }

        if (is_array($scopedPeerIds) && empty($scopedPeerIds)) {
            return $this->success([
                'type' => $type,
                'summary' => ['referrals' => 0, 'p2p_meetings' => 0, 'business_deals' => 0, 'testimonials' => 0],
                'items' => [],
            ], 'Scoped activities fetched successfully.');
        }

        $referralsCount = $this->countScoped('referrals', ['from_user_id', 'to_user_id'], $scopedPeerIds);
        $p2pCount = $this->countScoped('p2p_meetings', ['initiator_user_id', 'peer_user_id'], $scopedPeerIds);
        $dealsCount = $this->countScoped('business_deals', ['from_user_id', 'to_user_id'], $scopedPeerIds);
        $testimonialsCount = $this->countScoped('testimonials', ['from_user_id', 'to_user_id'], $scopedPeerIds);

        $items = [];
        $limit = min(50, max(5, (int) $request->query('per_page', 20)));

        if (in_array($type, ['all', 'referrals', 'referral'], true) && Schema::hasTable('referrals')) {
            $q = DB::table('referrals')->where('is_deleted', false);
            $this->applyPeerFilter($q, ['from_user_id', 'to_user_id'], $scopedPeerIds);
            foreach ($q->orderByDesc('created_at')->limit($limit)->get() as $r) {
                $items[] = [
                    'id' => (string) $r->id,
                    'activity_type' => 'referral',
                    'from_user_id' => $r->from_user_id,
                    'to_user_id' => $r->to_user_id,
                    'title' => $r->referral_of ?? 'Business Referral',
                    'remarks' => $r->remarks,
                    'created_at' => $r->created_at,
                ];
            }
        }

        if (in_array($type, ['all', 'p2p_meetings', 'p2p'], true) && Schema::hasTable('p2p_meetings')) {
            $q = DB::table('p2p_meetings')->where('is_deleted', false);
            $this->applyPeerFilter($q, ['initiator_user_id', 'peer_user_id'], $scopedPeerIds);
            foreach ($q->orderByDesc('created_at')->limit($limit)->get() as $m) {
                $items[] = [
                    'id' => (string) $m->id,
                    'activity_type' => 'p2p_meeting',
                    'from_user_id' => $m->initiator_user_id,
                    'to_user_id' => $m->peer_user_id,
                    'title' => $m->meeting_place ?? '1-on-1 P2P Meeting',
                    'remarks' => $m->remarks,
                    'created_at' => $m->created_at,
                ];
            }
        }

        if (in_array($type, ['all', 'business_deals', 'deals'], true) && Schema::hasTable('business_deals')) {
            $q = DB::table('business_deals')->where('is_deleted', false);
            $this->applyPeerFilter($q, ['from_user_id', 'to_user_id'], $scopedPeerIds);
            foreach ($q->orderByDesc('created_at')->limit($limit)->get() as $d) {
                $items[] = [
                    'id' => (string) $d->id,
                    'activity_type' => 'business_deal',
                    'from_user_id' => $d->from_user_id,
                    'to_user_id' => $d->to_user_id,
                    'amount' => (float) ($d->deal_amount ?? 0),
                    'title' => $d->business_type ?? 'Business Deal',
                    'remarks' => $d->comment,
                    'created_at' => $d->created_at,
                ];
            }
        }

        return $this->success([
            'type' => $type,
            'summary' => [
                'referrals' => $referralsCount,
                'p2p_meetings' => $p2pCount,
                'business_deals' => $dealsCount,
                'testimonials' => $testimonialsCount,
            ],
            'total_items' => count($items),
            'items' => $items,
        ], 'Scoped activities fetched successfully.');
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<int, string>|null  $scopedPeerIds
     */
    private function countScoped(string $table, array $columns, ?array $scopedPeerIds): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $q = DB::table($table);
        if (Schema::hasColumn($table, 'is_deleted')) {
            $q->where('is_deleted', false);
        }
        $this->applyPeerFilter($q, $columns, $scopedPeerIds);

        return $q->count();
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<int, string>|null  $scopedPeerIds
     */
    private function applyPeerFilter(Builder $query, array $columns, ?array $scopedPeerIds): void
    {
        if ($scopedPeerIds === null) {
            return;
        }

        $query->where(function ($sub) use ($columns, $scopedPeerIds): void {
            foreach ($columns as $col) {
                $sub->orWhereIn($col, $scopedPeerIds);
            }
        });
    }
}
