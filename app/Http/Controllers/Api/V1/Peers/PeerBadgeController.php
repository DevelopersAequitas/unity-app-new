<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Peers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Peers\GetPeerBadgesRequest;
use App\Services\Peers\PeerBadgeService;
use Illuminate\Http\JsonResponse;

class PeerBadgeController extends BaseApiController
{
    public function __construct(
        private readonly PeerBadgeService $peerBadgeService,
    ) {}

    /**
     * Get earned badges for a specific peer profile.
     */
    public function index(GetPeerBadgesRequest $request, string $peerId): JsonResponse
    {
        $viewer = $request->user();
        $filters = $request->validated();

        $result = $this->peerBadgeService->getPeerBadges($viewer, $peerId, $filters);

        if (! $result['peer_found']) {
            return response()->json([
                'success' => false,
                'message' => 'Peer with the specified ID was not found.',
                'data' => null,
            ], 404);
        }

        if ($result['restricted']) {
            return response()->json([
                'success' => false,
                'message' => 'Profile is restricted.',
                'data' => null,
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Peer badges retrieved successfully',
            'data' => $result['data'],
        ], 200);
    }
}
