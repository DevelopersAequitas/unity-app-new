<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\SpeedNetworking;

use App\Http\Controllers\Controller;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Services\SpeedNetworking\SpeedNetworkingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpeedNetworkingApiController extends Controller
{
    public function __construct(
        protected SpeedNetworkingService $speedNetworkingService
    ) {}

    /**
     * Join event lobby or active round.
     */
    public function join(Request $request, string $eventId): JsonResponse
    {
        $event = SpeedNetworkingEvent::findOrFail($eventId);
        $user = $request->user();

        $data = $this->speedNetworkingService->joinEvent($event, $user);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Request next match when round timer finishes or user taps Next Peer.
     */
    public function nextMatch(Request $request, string $eventId): JsonResponse
    {
        $event = SpeedNetworkingEvent::findOrFail($eventId);
        $user = $request->user();

        $data = $this->speedNetworkingService->getNextMatch($event, $user);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Leave event.
     */
    public function leave(Request $request, string $eventId): JsonResponse
    {
        $event = SpeedNetworkingEvent::findOrFail($eventId);
        $user = $request->user();

        $this->speedNetworkingService->leaveEvent($event, $user);

        return response()->json([
            'success' => true,
            'message' => 'Successfully left the speed networking event.',
        ]);
    }

    /**
     * Update participant presence heartbeat.
     */
    public function heartbeat(Request $request, string $eventId): JsonResponse
    {
        $event = SpeedNetworkingEvent::findOrFail($eventId);
        $user = $request->user();

        $this->speedNetworkingService->heartbeat($event, $user);

        return response()->json([
            'success' => true,
            'data' => [
                'last_heartbeat_at' => Carbon::now()->toIso8601String(),
            ],
        ]);
    }
}
