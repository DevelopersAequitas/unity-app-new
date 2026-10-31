<?php

declare(strict_types=1);

namespace App\Modules\Leadership\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Leader\Services\LeaderPeersService;
use App\Models\User;
use App\Shared\Authorization\FieldAccessService;
use App\Shared\OrganizationalScope\OrganizationalScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LeadershipPeersController extends BaseApiController
{
    public function __construct(
        private readonly LeaderPeersService $peersService,
        private readonly OrganizationalScopeService $scopeService,
        private readonly FieldAccessService $fieldAccessService,
    ) {}

    /**
     * API 46: Scoped Peers List (with dynamic field-access filtering & masking).
     * GET /api/v1/leadership/peers
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $districtId = $request->query('district_id') ? (string) $request->query('district_id') : null;

        if ($circleId && Str::isUuid($circleId) && ! $this->scopeService->canAccessCircle($user, $circleId)) {
            return $this->error('You do not have organizational scope access to view peers in this circle.', 403);
        }

        $scopedPeerIds = $this->scopeService->resolveScopedPeerIds($user, 'peers', $circleId, $districtId);

        $query = User::query()->whereNull('deleted_at');
        if ($scopedPeerIds !== null) {
            if (empty($scopedPeerIds)) {
                return $this->success([
                    'total' => 0,
                    'items' => [],
                ], 'Scoped peers fetched successfully.');
            }
            $query->whereIn('id', $scopedPeerIds);
        }

        if ($request->filled('membership_status')) {
            $query->where('membership_status', (string) $request->query('membership_status'));
        }
        if ($request->filled('search')) {
            $s = strtolower(trim((string) $request->query('search')));
            $query->where(function ($q) use ($s): void {
                $q->whereRaw('LOWER(display_name) LIKE ?', ['%'.$s.'%'])
                    ->orWhereRaw('LOWER(first_name) LIKE ?', ['%'.$s.'%'])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', ['%'.$s.'%'])
                    ->orWhereRaw('LOWER(email) LIKE ?', ['%'.$s.'%'])
                    ->orWhereRaw('LOWER(company_name) LIKE ?', ['%'.$s.'%']);
            });
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));
        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        $rawItems = collect($paginator->items())->map(fn (User $p): array => [
            'id' => (string) $p->id,
            'first_name' => (string) ($p->first_name ?? ''),
            'last_name' => (string) ($p->last_name ?? ''),
            'display_name' => (string) ($p->display_name ?: trim(($p->first_name ?? '').' '.($p->last_name ?? ''))),
            'email' => (string) ($p->email ?? ''),
            'phone' => (string) ($p->phone ?? ''),
            'company_name' => (string) ($p->company_name ?? ''),
            'designation' => (string) ($p->designation ?? ''),
            'city' => (string) ($p->city ?? ''),
            'membership_status' => (string) ($p->membership_status ?? 'free_peer'),
            'coins_balance' => (int) ($p->coins_balance ?? 0),
            'influencer_stars' => (int) ($p->influencer_stars ?? 0),
            'profile_photo_url' => $p->profile_photo_url,
            'created_at' => $p->created_at?->toIso8601String(),
        ])->all();

        $filteredItems = $this->fieldAccessService->filterCollection($user, 'peers', $rawItems);

        return $this->success([
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'items' => $filteredItems,
        ], 'Scoped peers fetched successfully.');
    }

    /**
     * API 47: Scoped Peer Detail (with dynamic field-access filtering & masking).
     * GET /api/v1/leadership/peers/{peerId}
     */
    public function show(Request $request, string $peerId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->scopeService->canAccessPeer($user, $peerId)) {
            return $this->error('You do not have organizational scope access to view this peer.', 403);
        }

        $peer = User::query()->whereNull('deleted_at')->find($peerId);
        if (! $peer) {
            return $this->error('Peer not found.', 404);
        }

        $record = [
            'id' => (string) $peer->id,
            'first_name' => (string) ($peer->first_name ?? ''),
            'last_name' => (string) ($peer->last_name ?? ''),
            'display_name' => (string) ($peer->display_name ?: trim(($peer->first_name ?? '').' '.($peer->last_name ?? ''))),
            'email' => (string) ($peer->email ?? ''),
            'phone' => (string) ($peer->phone ?? ''),
            'company_name' => (string) ($peer->company_name ?? ''),
            'designation' => (string) ($peer->designation ?? ''),
            'business_type' => $peer->business_type,
            'turnover_range' => $peer->turnover_range,
            'city' => $peer->city,
            'membership_status' => (string) ($peer->membership_status ?? 'free_peer'),
            'membership_starts_at' => $peer->membership_starts_at?->toIso8601String(),
            'membership_ends_at' => $peer->membership_ends_at?->toIso8601String(),
            'coins_balance' => (int) ($peer->coins_balance ?? 0),
            'influencer_stars' => (int) ($peer->influencer_stars ?? 0),
            'short_bio' => $peer->short_bio,
            'industry_tags' => $peer->industry_tags ?? [],
            'skills' => $peer->skills ?? [],
            'interests' => $peer->interests ?? [],
            'profile_photo_url' => $peer->profile_photo_url,
            'created_at' => $peer->created_at?->toIso8601String(),
        ];

        $filtered = $this->fieldAccessService->filterRecord($user, 'peers', $record);

        return $this->success($filtered, 'Peer details fetched successfully.');
    }
}

