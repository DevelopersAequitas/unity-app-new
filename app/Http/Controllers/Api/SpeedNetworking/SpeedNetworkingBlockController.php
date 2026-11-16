<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\SpeedNetworking;

use App\Http\Controllers\Controller;
use App\Http\Requests\SpeedNetworking\StoreSpeedNetworkingBlockedPeerRequest;
use App\Services\SpeedNetworking\SpeedNetworkingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpeedNetworkingBlockController extends Controller
{
    public function __construct(
        protected SpeedNetworkingService $speedNetworkingService
    ) {}

    /**
     * List all peers blocked by the authenticated user in Speed Networking.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $peers = $this->speedNetworkingService->getBlockedPeersForUser((string) $user->id);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => [
                'blocked_peers' => $peers,
                'peers' => $peers,
                'items' => $peers,
            ],
        ]);
    }

    /**
     * Block a peer from Speed Networking matching across current and future events.
     */
    public function store(StoreSpeedNetworkingBlockedPeerRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $userId = (string) $user->id;
        $peerId = trim((string) $validated['peer_id']);

        if ($userId === $peerId) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Cannot block yourself',
            ], 422);
        }

        $eventId = isset($validated['event_id']) ? trim((string) $validated['event_id']) : null;
        $reason = isset($validated['reason']) && trim((string) $validated['reason']) !== ''
            ? trim((string) $validated['reason'])
            : 'Blocked during Speed Networking';

        $this->speedNetworkingService->blockPeerInSpeedNetworking($userId, $peerId, $eventId, $reason);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Peer blocked from Speed Networking events.',
        ]);
    }

    /**
     * Unblock a peer from Speed Networking matching.
     */
    public function destroy(Request $request, string $peerId): JsonResponse
    {
        $user = $request->user();
        $this->speedNetworkingService->unblockPeerInSpeedNetworking((string) $user->id, trim($peerId));

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Peer unblocked from Speed Networking events.',
        ]);
    }
}
