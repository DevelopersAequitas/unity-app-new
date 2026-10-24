<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\SpeedNetworking;

use App\Http\Controllers\Controller;
use App\Http\Requests\SpeedNetworking\CreateSpeedNetworkingEventRequest;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Services\SpeedNetworking\SpeedNetworkingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SpeedNetworkingAdminController extends Controller
{
    public function __construct(
        protected SpeedNetworkingService $speedNetworkingService
    ) {}

    /**
     * Find SpeedNetworkingEvent or fallback to base Event.
     *
     * @return array{type: 'sn'|'base', event: SpeedNetworkingEvent|Event}
     */
    protected function findEventOrBaseEvent(string $id): array
    {
        if (Schema::hasTable('speed_networking_events')) {
            $snEvent = SpeedNetworkingEvent::find($id);
            if ($snEvent) {
                return ['type' => 'sn', 'event' => $snEvent];
            }
        }

        $baseEvent = Event::find($id);
        if ($baseEvent) {
            return ['type' => 'base', 'event' => $baseEvent];
        }

        abort(404, 'Speed networking event not found.');
    }

    /**
     * List events with pagination and filters for the Admin panel.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'status' => $request->query('status'),
            'search' => $request->query('search'),
        ];
        $perPage = (int) $request->query('per_page', 15);

        $result = $this->speedNetworkingService->listEvents($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'pagination' => $result['pagination'],
        ]);
    }

    /**
     * Get single event detail with live stats.
     */
    public function show(string $id): JsonResponse
    {
        $target = $this->findEventOrBaseEvent($id);
        if ($target['type'] === 'sn') {
            $details = $this->speedNetworkingService->getEventDetails($target['event']);

            return response()->json([
                'success' => true,
                'data' => $details,
            ]);
        }

        /** @var Event $baseEvent */
        $baseEvent = $target['event'];
        $meta = $baseEvent->metadata ?? [];
        $regCount = $baseEvent->registrations()->count();
        $details = [
            'event' => [
                'id' => $baseEvent->id,
                'title' => $baseEvent->title,
                'description' => $baseEvent->description,
                'status' => $meta['speed_status'] ?? 'scheduled',
                'round_duration_seconds' => (int) ($meta['round_duration_seconds'] ?? 300),
                'break_duration_seconds' => (int) ($meta['break_duration_seconds'] ?? 20),
                'total_rounds' => (int) ($meta['total_rounds'] ?? 5),
                'current_round' => (int) ($meta['current_round'] ?? 0),
                'scheduled_start_time' => $baseEvent->start_at?->toIso8601String(),
                'actual_start_time' => $meta['actual_start_time'] ?? null,
                'total_participants_count' => $regCount,
                'active_tables_count' => (int) ceil($regCount / 2),
            ],
            'stats' => [
                'total_participants' => $regCount,
                'active_participants' => $regCount,
                'total_matches' => 0,
                'active_matches' => 0,
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $details,
        ]);
    }

    /**
     * Create a Speed Networking event.
     */
    public function store(CreateSpeedNetworkingEventRequest $request): JsonResponse
    {
        $event = $this->speedNetworkingService->createOrUpdateEvent($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Speed networking event created successfully.',
            'data' => $event,
        ], 201);
    }

    /**
     * Update an existing Speed Networking event.
     */
    public function update(CreateSpeedNetworkingEventRequest $request, string $id): JsonResponse
    {
        $data = $request->validated();
        $data['id'] = $id;

        $event = $this->speedNetworkingService->createOrUpdateEvent($data);

        return response()->json([
            'success' => true,
            'message' => 'Speed networking event updated successfully.',
            'data' => $event,
        ]);
    }

    /**
     * Delete an event.
     */
    public function destroy(string $id): JsonResponse
    {
        $target = $this->findEventOrBaseEvent($id);
        $title = null;
        if ($target) {
            $title = $target['event']->title ?? null;
            if ($target['type'] === 'sn') {
                $this->speedNetworkingService->deleteEvent($target['event']);
            } else {
                $target['event']->delete();
            }
        }

        if (Schema::hasTable('speed_networking_events')) {
            $q = SpeedNetworkingEvent::where('id', $id);
            if (! empty($title)) {
                $q->orWhere('title', $title);
            }
            $q->delete();
        }

        $baseQ = Event::where('id', $id);
        if (! empty($title)) {
            $baseQ->orWhere(function ($q2) use ($title): void {
                $q2->where('title', $title)->where('event_type', 'speed_networking');
            });
        }
        $baseQ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Speed networking event deleted successfully.',
        ]);
    }

    /**
     * List participants for a specific event with real-time status.
     */
    public function participants(Request $request, string $id): JsonResponse
    {
        $target = $this->findEventOrBaseEvent($id);
        if ($target['type'] === 'sn') {
            $perPage = (int) $request->query('per_page', 25);
            $result = $this->speedNetworkingService->getEventParticipants($target['event'], $perPage);

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination'],
            ]);
        }

        /** @var Event $baseEvent */
        $baseEvent = $target['event'];
        $participantsMap = [];

        if (Schema::hasTable('speed_networking_participants')) {
            $snList = \App\Models\SpeedNetworking\SpeedNetworkingParticipant::with('user')
                ->where('event_id', $baseEvent->id)
                ->get();
            foreach ($snList as $p) {
                $u = $p->user;
                if ($u) {
                    $uid = (string) $u->id;
                    $name = $u->display_name ?: trim(($u->first_name ?? '').' '.($u->last_name ?? '')) ?: $u->name ?: 'Participant';
                    $participantsMap[$uid] = [
                        'id' => (string) $p->id,
                        'event_id' => $baseEvent->id,
                        'user_id' => $uid,
                        'status' => $p->status ?? 'in_call',
                        'user' => [
                            'id' => $uid,
                            'name' => $name,
                            'company' => (string) ($u->company_name ?? 'Peers Member'),
                            'industry' => (string) ($u->business_sub_category ?? $u->industry ?? ''),
                            'avatar_url' => $u->profile_photo_url ?? null,
                        ],
                        'joined_at' => $p->joined_at?->toIso8601String() ?? Carbon::now()->toIso8601String(),
                    ];
                }
            }
        }

        $registrations = EventRegistration::where('event_id', $baseEvent->id)
            ->with('user')
            ->get();
        foreach ($registrations as $reg) {
            $u = $reg->user;
            if ($u && ! isset($participantsMap[(string) $u->id])) {
                $uid = (string) $u->id;
                $name = $u->display_name ?: trim(($u->first_name ?? '').' '.($u->last_name ?? '')) ?: $u->name ?: 'Participant';
                $participantsMap[$uid] = [
                    'id' => (string) $reg->id,
                    'event_id' => $baseEvent->id,
                    'user_id' => $uid,
                    'status' => 'in_call',
                    'user' => [
                        'id' => $uid,
                        'name' => $name,
                        'company' => (string) ($u->company_name ?? 'Peers Member'),
                        'industry' => (string) ($u->business_sub_category ?? $u->industry ?? ''),
                        'avatar_url' => $u->profile_photo_url ?? null,
                    ],
                    'joined_at' => $reg->created_at?->toIso8601String() ?? Carbon::now()->toIso8601String(),
                ];
            }
        }

        $list = array_values($participantsMap);

        return response()->json([
            'success' => true,
            'data' => $list,
            'pagination' => [
                'total' => count($list),
                'per_page' => max(50, count($list)),
                'current_page' => 1,
                'last_page' => 1,
            ],
        ]);
    }

    /**
     * Start event and trigger Round 1 matchmaking.
     */
    public function start(string $id): JsonResponse
    {
        $target = $this->findEventOrBaseEvent($id);
        if ($target['type'] === 'sn') {
            $updatedEvent = $this->speedNetworkingService->startEvent($target['event']);

            return response()->json([
                'success' => true,
                'message' => 'Speed networking event started and Round 1 generated.',
                'data' => $updatedEvent,
            ]);
        }

        /** @var Event $baseEvent */
        $baseEvent = $target['event'];
        $meta = $baseEvent->metadata ?? [];
        $meta['speed_status'] = 'in_progress';
        $meta['current_round'] = 1;
        $meta['round_started_at'] = Carbon::now()->toIso8601String();
        $meta['actual_start_time'] = Carbon::now()->toIso8601String();
        $baseEvent->metadata = $meta;
        $baseEvent->save();

        return response()->json([
            'success' => true,
            'message' => 'Speed networking event started and Round 1 generated.',
            'data' => [
                'id' => $baseEvent->id,
                'title' => $baseEvent->title,
                'description' => $baseEvent->description,
                'status' => 'in_progress',
                'round_duration_seconds' => (int) ($meta['round_duration_seconds'] ?? 300),
                'break_duration_seconds' => (int) ($meta['break_duration_seconds'] ?? 20),
                'total_rounds' => (int) ($meta['total_rounds'] ?? 5),
                'current_round' => 1,
                'scheduled_start_time' => $baseEvent->start_at?->toIso8601String(),
                'actual_start_time' => $meta['actual_start_time'],
                'total_participants_count' => $baseEvent->registrations()->count(),
            ],
        ]);
    }

    /**
     * Force advance to next round.
     */
    public function nextRound(string $id): JsonResponse
    {
        $target = $this->findEventOrBaseEvent($id);
        if ($target['type'] === 'sn') {
            $updatedEvent = $this->speedNetworkingService->nextRound($target['event']);

            return response()->json([
                'success' => true,
                'message' => 'Round advanced successfully.',
                'data' => $updatedEvent,
            ]);
        }

        /** @var Event $baseEvent */
        $baseEvent = $target['event'];
        $meta = $baseEvent->metadata ?? [];
        $current = (int) ($meta['current_round'] ?? 1);
        $total = (int) ($meta['total_rounds'] ?? 5);
        $next = min($total, $current + 1);
        $meta['current_round'] = $next;
        $meta['round_started_at'] = Carbon::now()->toIso8601String();
        if ($next >= $total) {
            $meta['speed_status'] = 'completed';
        }
        $baseEvent->metadata = $meta;
        $baseEvent->save();

        return response()->json([
            'success' => true,
            'message' => 'Round advanced successfully.',
            'data' => [
                'id' => $baseEvent->id,
                'title' => $baseEvent->title,
                'description' => $baseEvent->description,
                'status' => $meta['speed_status'] ?? 'in_progress',
                'round_duration_seconds' => (int) ($meta['round_duration_seconds'] ?? 300),
                'break_duration_seconds' => (int) ($meta['break_duration_seconds'] ?? 20),
                'total_rounds' => $total,
                'current_round' => $next,
                'scheduled_start_time' => $baseEvent->start_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * End the event.
     */
    public function end(string $id): JsonResponse
    {
        $target = $this->findEventOrBaseEvent($id);
        if ($target['type'] === 'sn') {
            $updatedEvent = $this->speedNetworkingService->endEvent($target['event']);

            return response()->json([
                'success' => true,
                'message' => 'Speed networking event ended successfully.',
                'data' => $updatedEvent,
            ]);
        }

        /** @var Event $baseEvent */
        $baseEvent = $target['event'];
        $meta = $baseEvent->metadata ?? [];
        $meta['speed_status'] = 'completed';
        $baseEvent->metadata = $meta;
        $baseEvent->save();

        return response()->json([
            'success' => true,
            'message' => 'Speed networking event ended successfully.',
            'data' => [
                'id' => $baseEvent->id,
                'title' => $baseEvent->title,
                'description' => $baseEvent->description,
                'status' => 'completed',
                'round_duration_seconds' => (int) ($meta['round_duration_seconds'] ?? 300),
                'break_duration_seconds' => (int) ($meta['break_duration_seconds'] ?? 20),
                'total_rounds' => (int) ($meta['total_rounds'] ?? 5),
                'current_round' => (int) ($meta['current_round'] ?? 1),
            ],
        ]);
    }

    /**
     * Get live tables monitor for real-time admin dashboard.
     */
    public function liveTables(string $id): JsonResponse
    {
        $target = $this->findEventOrBaseEvent($id);
        if ($target['type'] === 'sn') {
            $data = $this->speedNetworkingService->getLiveTables($target['event']);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        }

        /** @var Event $baseEvent */
        $baseEvent = $target['event'];
        $meta = $baseEvent->metadata ?? [];
        $roundDuration = (int) ($meta['round_duration_seconds'] ?? 300);
        $breakDuration = (int) ($meta['break_duration_seconds'] ?? 20);
        $totalRounds = (int) ($meta['total_rounds'] ?? 5);
        $currentRound = (int) ($meta['current_round'] ?? 0);
        $speedStatus = $meta['speed_status'] ?? 'scheduled';

        $remainingSeconds = $roundDuration;
        if ($speedStatus === 'in_progress' && !empty($meta['round_started_at'])) {
            $started = Carbon::parse($meta['round_started_at']);
            $elapsed = Carbon::now()->diffInSeconds($started);
            $remainingSeconds = max(0, $roundDuration - $elapsed);
        }

        // Fetch real registered users from both speed_networking_participants and registrations
        $participantsMap = [];
        if (Schema::hasTable('speed_networking_participants')) {
            $snList = \App\Models\SpeedNetworking\SpeedNetworkingParticipant::with('user')
                ->where('event_id', $baseEvent->id)
                ->get();
            foreach ($snList as $p) {
                $u = $p->user;
                if ($u) {
                    $uid = (string) $u->id;
                    $name = $u->display_name ?: trim(($u->first_name ?? '').' '.($u->last_name ?? '')) ?: $u->name ?: 'Participant';
                    $participantsMap[$uid] = [
                        'id' => $uid,
                        'name' => $name,
                        'company' => (string) ($u->company_name ?? 'Peers Member'),
                        'industry' => (string) ($u->business_sub_category ?? $u->industry ?? 'Business'),
                        'avatar_url' => $u->profile_photo_url ?? null,
                    ];
                }
            }
        }

        $registrations = EventRegistration::where('event_id', $baseEvent->id)
            ->with('user')
            ->get();
        foreach ($registrations as $reg) {
            $u = $reg->user;
            if ($u && ! isset($participantsMap[(string) $u->id])) {
                $uid = (string) $u->id;
                $name = $u->display_name ?: trim(($u->first_name ?? '').' '.($u->last_name ?? '')) ?: $u->name ?: 'Participant';
                $participantsMap[$uid] = [
                    'id' => $uid,
                    'name' => $name,
                    'company' => (string) ($u->company_name ?? 'Peers Member'),
                    'industry' => (string) ($u->business_sub_category ?? $u->industry ?? 'Business'),
                    'avatar_url' => $u->profile_photo_url ?? null,
                ];
            }
        }

        $participants = array_values($participantsMap);

        // Generate round pairings exclusively with real participants
        $tableMatches = [];
        if (count($participants) >= 2) {
            $tableCount = (int) ceil(count($participants) / 2);
            for ($i = 0; $i < $tableCount; $i++) {
                $tableMatches[] = [
                    'match_id' => 'tbl_match_' . ($i + 1),
                    'table_number' => $i + 1,
                    'channel_id' => 'sn_room_table_' . ($i + 1),
                    'status' => $speedStatus === 'in_progress' ? 'active' : 'waiting',
                    'is_break_round' => false,
                    'user_a' => $participants[$i * 2] ?? null,
                    'user_b' => $participants[$i * 2 + 1] ?? null,
                    'started_at' => $meta['round_started_at'] ?? Carbon::now()->toIso8601String(),
                ];
            }
        } elseif (count($participants) === 1) {
            $tableMatches[] = [
                'match_id' => 'tbl_match_1',
                'table_number' => 1,
                'channel_id' => 'sn_room_table_1',
                'status' => 'waiting',
                'is_break_round' => false,
                'user_a' => $participants[0],
                'user_b' => null,
                'started_at' => Carbon::now()->toIso8601String(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'event' => [
                    'id' => $baseEvent->id,
                    'title' => $baseEvent->title,
                    'description' => $baseEvent->description,
                    'status' => $speedStatus,
                    'round_duration_seconds' => $roundDuration,
                    'break_duration_seconds' => $breakDuration,
                    'total_rounds' => $totalRounds,
                    'current_round' => $currentRound,
                    'scheduled_start_time' => $baseEvent->start_at?->toIso8601String(),
                    'actual_start_time' => $meta['actual_start_time'] ?? null,
                    'total_participants_count' => count($participants),
                    'active_tables_count' => count($tableMatches),
                ],
                'active_participants_count' => count($participants),
                'total_participants_count' => count($participants),
                'remaining_seconds' => $remainingSeconds,
                'is_paused' => false,
                'tables' => $tableMatches,
            ],
        ]);
    }
}
