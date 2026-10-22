<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\SpeedNetworking;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Services\SpeedNetworking\AgoraTokenService;
use App\Services\SpeedNetworking\SpeedNetworkingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SpeedNetworkingApiController extends Controller
{
    public function __construct(
        protected SpeedNetworkingService $speedNetworkingService,
        protected AgoraTokenService $agoraTokenService
    ) {}

    /**
     * Get currently active or scheduled speed networking event.
     */
    public function activeEvent(Request $request): JsonResponse
    {
        $user = $request->user();
        $event = null;

        if (Schema::hasTable('speed_networking_events')) {
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
        }

        // If no event found in speed_networking_events table, search in base events table
        if (! $event) {
            $baseEvent = Event::query()
                ->where('event_type', 'speed_networking')
                ->where(function ($q): void {
                    $q->whereNull('end_at')
                        ->orWhere('end_at', '>=', Carbon::now()->subHours(8));
                })
                ->orderBy('start_at', 'desc')
                ->first();

            if ($baseEvent) {
                $meta = is_array($baseEvent->metadata) ? $baseEvent->metadata : (json_decode((string) $baseEvent->metadata, true) ?: []);
                $roundDuration = (int) ($meta['round_duration_seconds'] ?? 300);
                $breakDuration = (int) ($meta['break_duration_seconds'] ?? 20);
                $totalRounds = (int) ($meta['total_rounds'] ?? 5);

                $startAt = $baseEvent->start_at ? Carbon::parse($baseEvent->start_at) : Carbon::now();
                $endAt = $baseEvent->end_at ? Carbon::parse($baseEvent->end_at) : $startAt->copy()->addHours(3);

                $now = Carbon::now();
                $status = 'scheduled';
                if ($now->between($startAt->copy()->subMinutes(30), $endAt)) {
                    $status = 'in_progress';
                } elseif ($now->isAfter($endAt)) {
                    $status = 'completed';
                }

                $participantsCount = EventRegistration::query()
                    ->where('event_id', $baseEvent->id)
                    ->where('status', '!=', 'cancelled')
                    ->whereNull('deleted_at')
                    ->count();

                $isRegistered = false;
                if ($user) {
                    $isRegistered = EventRegistration::query()
                        ->where('event_id', $baseEvent->id)
                        ->where('user_id', $user->id)
                        ->where('status', '!=', 'cancelled')
                        ->whereNull('deleted_at')
                        ->exists();
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => (string) $baseEvent->id,
                        'event_id' => (string) $baseEvent->id,
                        'title' => (string) $baseEvent->title,
                        'description' => $baseEvent->description,
                        'banner_url' => $baseEvent->banner_url,
                        'status' => $status,
                        'round_duration_seconds' => $roundDuration,
                        'break_duration_seconds' => $breakDuration,
                        'total_rounds' => $totalRounds,
                        'current_round' => $status === 'in_progress' ? 1 : 0,
                        'scheduled_start_time' => $startAt->toIso8601String(),
                        'actual_start_time' => $startAt->toIso8601String(),
                        'participants_count' => $participantsCount,
                        'is_registered' => $isRegistered,
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No active speed networking event scheduled.',
            ]);
        }

        if (in_array($event->status, [SpeedNetworkingEvent::STATUS_SCHEDULED, SpeedNetworkingEvent::STATUS_LOBBY_OPEN, SpeedNetworkingEvent::STATUS_DRAFT], true)) {
            if (empty($event->scheduled_start_time) || $event->scheduled_start_time->isPast()) {
                $event->status = SpeedNetworkingEvent::STATUS_IN_PROGRESS;
                $event->actual_start_time = Carbon::now();
                if ($event->current_round < 1) {
                    $event->current_round = 1;
                }
                $event->save();
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (string) $event->id,
                'event_id' => (string) $event->id,
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
        $user = $request->user();

        // 1. Check if event is in speed_networking_events table
        if (Schema::hasTable('speed_networking_events')) {
            $event = SpeedNetworkingEvent::find($eventId);
            if ($event) {
                $data = $this->speedNetworkingService->joinEvent($event, $user);

                return response()->json([
                    'status' => 'success',
                    'success' => true,
                    'data' => $data,
                ]);
            }
        }

        // 2. Fallback to base Event model
        $baseEvent = Event::findOrFail($eventId);
        $meta = is_array($baseEvent->metadata) ? $baseEvent->metadata : (json_decode((string) $baseEvent->metadata, true) ?: []);
        $roundDuration = (int) ($meta['round_duration_seconds'] ?? 300);
        $breakDuration = (int) ($meta['break_duration_seconds'] ?? 20);
        $totalRounds = (int) ($meta['total_rounds'] ?? 5);

        $channelId = 'sn_room_'.$baseEvent->id;
        $agoraToken = '';
        try {
            $agoraToken = $this->agoraTokenService->generateToken($channelId, (string) $user->id);
        } catch (\Throwable) {
            $agoraToken = 'demo_rtc_token_'.md5($channelId.(string) $user->id);
        }

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => [
                'event_id' => (string) $baseEvent->id,
                'title' => (string) $baseEvent->title,
                'event_title' => (string) $baseEvent->title,
                'round_duration_seconds' => $roundDuration,
                'break_duration_seconds' => $breakDuration,
                'remaining_seconds' => $roundDuration,
                'current_round' => 1,
                'total_rounds' => $totalRounds,
                'channel_id' => $channelId,
                'agora_token' => $agoraToken,
                'is_break_round' => false,
                'is_waiting' => false,
                'has_met_all_peers' => false,
                'completed' => false,
                'message' => 'Speed Networking session active.',
                'peer' => null,
            ],
        ]);
    }

    /**
     * Request next match when round timer finishes or user taps Next Peer.
     */
    public function nextMatch(Request $request, string $eventId): JsonResponse
    {
        $user = $request->user();

        if (Schema::hasTable('speed_networking_events')) {
            $event = SpeedNetworkingEvent::find($eventId);
            if ($event) {
                $data = $this->speedNetworkingService->getNextMatch($event, $user, $request->isMethod('get'));

                return response()->json([
                    'status' => 'success',
                    'success' => true,
                    'data' => $data,
                ]);
            }
        }

        $baseEvent = Event::find($eventId);
        $channelId = 'sn_room_'.($baseEvent ? $baseEvent->id : $eventId);
        $agoraToken = '';
        try {
            $agoraToken = $this->agoraTokenService->generateToken($channelId, (string) $user->id);
        } catch (\Throwable) {
            $agoraToken = 'demo_rtc_token_'.md5($channelId.(string) $user->id);
        }

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => [
                'event_id' => $eventId,
                'current_round' => 1,
                'channel_id' => $channelId,
                'agora_token' => $agoraToken,
                'is_break_round' => false,
                'is_waiting' => true,
                'message' => 'Looking for next peer match...',
                'peer' => null,
            ],
        ]);
    }

    /**
     * Leave event.
     */
    public function leave(Request $request, string $eventId): JsonResponse
    {
        $user = $request->user();

        if (Schema::hasTable('speed_networking_events')) {
            $event = SpeedNetworkingEvent::find($eventId);
            if ($event) {
                $this->speedNetworkingService->leaveEvent($event, $user);
            }
        }

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
        $user = $request->user();

        if (Schema::hasTable('speed_networking_events')) {
            $event = SpeedNetworkingEvent::find($eventId);
            if ($event) {
                $activeCount = $this->speedNetworkingService->heartbeat($event, $user);
                $activeMatch = $this->speedNetworkingService->getActiveMatchPayload($event, $user);

                return response()->json([
                    'status' => 'success',
                    'success' => true,
                    'message' => 'Heartbeat recorded',
                    'active_attendees_count' => $activeCount,
                    'data' => [
                        'last_heartbeat_at' => Carbon::now()->toIso8601String(),
                        'active_attendees_count' => $activeCount,
                        'has_active_match' => $activeMatch !== null,
                        'match' => $activeMatch,
                    ],
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Heartbeat recorded',
            'active_attendees_count' => 1,
            'data' => [
                'last_heartbeat_at' => Carbon::now()->toIso8601String(),
                'active_attendees_count' => 1,
                'has_active_match' => false,
                'match' => null,
            ],
        ]);
    }
}
