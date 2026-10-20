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
     * Get currently active or scheduled speed networking event.
     */
    public function activeEvent(Request $request): JsonResponse
    {
        $user = $request->user();
        $event = SpeedNetworkingEvent::query()
            ->whereIn('status', [
                SpeedNetworkingEvent::STATUS_IN_PROGRESS,
                SpeedNetworkingEvent::STATUS_LOBBY_OPEN,
                SpeedNetworkingEvent::STATUS_SCHEDULED,
            ])
            ->orderByRaw("CASE 
                WHEN status = 'in_progress' THEN 1 
                WHEN status = 'lobby_open' THEN 2 
                WHEN status = 'scheduled' THEN 3 
                ELSE 4 END")
            ->orderBy('scheduled_start_time', 'asc')
            ->first();

        if (! $event) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No active speed networking event scheduled.',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'banner_url' => $event->banner_url,
                'status' => $event->status,
                'round_duration_seconds' => (int) $event->round_duration_seconds,
                'break_duration_seconds' => (int) $event->break_duration_seconds,
                'total_rounds' => (int) $event->total_rounds,
                'current_round' => (int) $event->current_round,
                'scheduled_start_time' => $event->scheduled_start_time?->toIso8601String(),
                'actual_start_time' => $event->actual_start_time?->toIso8601String(),
                'participants_count' => $event->participants()->count(),
                'is_registered' => $user ? $event->participants()->where('user_id', $user->id)->exists() : false,
            ],
        ]);
    }

    /**
     * Join event lobby or active round.
     */
    public function join(Request $request, string $eventId): JsonResponse
    {
        $event = SpeedNetworkingEvent::findOrFail($eventId);
        $user = $request->user();

        $data = $this->speedNetworkingService->joinEvent($event, $user);

        return response()->json([
            'status' => 'success',
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

        $data = $this->speedNetworkingService->getNextMatch($event, $user, $request->isMethod('get'));

        return response()->json([
            'status' => 'success',
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
            'status' => 'success',
            'success' => true,
            'message' => 'Successfully left speed networking session.',
        ]);
    }

    /**
     * Update participant presence heartbeat.
     */
    public function heartbeat(Request $request, string $eventId): JsonResponse
    {
        $event = SpeedNetworkingEvent::findOrFail($eventId);
        $user = $request->user();

        $activeCount = $this->speedNetworkingService->heartbeat($event, $user);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Heartbeat recorded',
            'active_attendees_count' => $activeCount,
            'data' => [
                'last_heartbeat_at' => Carbon::now()->toIso8601String(),
                'active_attendees_count' => $activeCount,
            ],
        ]);
    }
}
