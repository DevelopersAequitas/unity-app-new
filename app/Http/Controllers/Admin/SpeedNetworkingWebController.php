<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Models\SpeedNetworking\SpeedNetworkingParticipant;
use App\Services\SpeedNetworking\SpeedNetworkingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SpeedNetworkingWebController extends Controller
{
    public function __construct(
        protected SpeedNetworkingService $speedNetworkingService
    ) {}

    /**
     * Find SpeedNetworkingEvent or fallback to base Event.
     *
     * @return array{type: 'sn'|'base', event: SpeedNetworkingEvent|Event}|null
     */
    protected function findEventOrBaseEvent(string $id): ?array
    {
        try {
            if (Schema::hasTable('speed_networking_events')) {
                $snEvent = SpeedNetworkingEvent::find($id);
                if ($snEvent) {
                    return ['type' => 'sn', 'event' => $snEvent];
                }
            }
        } catch (\Throwable $e) {
            // Ignore DB table error
        }

        try {
            $baseEvent = Event::find($id) ?? Event::withTrashed()->find($id);
            if ($baseEvent) {
                return ['type' => 'base', 'event' => $baseEvent];
            }
        } catch (\Throwable $e) {
            // Ignore error
        }

        return null;
    }

    /**
     * Display listing of Speed Networking events in the Admin Panel.
     */
    public function index(Request $request): View
    {
        $filters = [
            'status' => $request->query('status'),
            'search' => $request->query('search'),
        ];
        $perPage = (int) $request->query('per_page', 12);

        $eventsData = $this->speedNetworkingService->listEvents($filters, $perPage);

        $totalEvents = 0;
        $inProgressEvents = 0;
        $scheduledEvents = 0;
        $completedEvents = 0;

        try {
            if (Schema::hasTable('speed_networking_events')) {
                $totalEvents = SpeedNetworkingEvent::count();
                $inProgressEvents = SpeedNetworkingEvent::where('status', SpeedNetworkingEvent::STATUS_IN_PROGRESS)->count();
                $scheduledEvents = SpeedNetworkingEvent::where('status', SpeedNetworkingEvent::STATUS_SCHEDULED)->count();
                $completedEvents = SpeedNetworkingEvent::where('status', SpeedNetworkingEvent::STATUS_COMPLETED)->count();
            }
        } catch (\Throwable $e) {
            // Table doesn't exist
        }

        if ($totalEvents === 0) {
            try {
                $baseQuery = Event::where(function ($q): void {
                    $q->where('event_type', 'speed_networking')
                        ->orWhere('title', 'like', '%Speed%')
                        ->orWhere('title', 'like', '%speed%');
                });
                $totalEvents = (clone $baseQuery)->count();
                $inProgressEvents = (clone $baseQuery)->where('event_type', 'speed_networking')->count();
                $scheduledEvents = (clone $baseQuery)->where('event_type', 'speed_networking')->count();
                $completedEvents = 0;
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        $events = array_map(function ($event): object {
            return is_array($event) ? (object) $event : $event;
        }, (array) ($eventsData['data'] ?? []));

        return view('admin.speed-networking.index', [
            'events' => $events,
            'pagination' => $eventsData['pagination'] ?? [],
            'totalEvents' => $totalEvents,
            'inProgressEvents' => $inProgressEvents,
            'scheduledEvents' => $scheduledEvents,
            'completedEvents' => $completedEvents,
            'currentStatus' => (string) $request->query('status', ''),
            'search' => (string) $request->query('search', ''),
        ]);
    }

    /**
     * Store newly created Speed Networking event from Admin Panel.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'banner_url' => 'nullable|string|max:500',
            'scheduled_start_time' => 'nullable|date',
            'scheduled_end_time' => 'nullable|date',
            'round_duration_seconds' => 'nullable|integer|min:10|max:3600',
            'break_duration_seconds' => 'nullable|integer|min:5|max:1800',
            'total_rounds' => 'nullable|integer|min:0|max:100',
            'ticket_price' => 'nullable|numeric|min:0',
            'is_paid' => 'nullable|boolean',
            'is_free' => 'nullable|boolean',
            'currency' => 'nullable|string|max:10',
            'circle_id' => 'nullable|uuid',
            'circle_restricted' => 'nullable|boolean',
        ]);

        $validated['total_rounds'] = ! empty($validated['total_rounds']) ? (int) $validated['total_rounds'] : 0;

        $event = $this->speedNetworkingService->createOrUpdateEvent($validated);

        return redirect()
            ->route('admin.speed-networking.show', $event->id)
            ->with('success', "Speed Networking event '{$event->title}' created successfully.");
    }

    /**
     * Update Speed Networking event parameters / round configuration.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $target = $this->findEventOrBaseEvent($id);

        if (! $target) {
            return redirect()
                ->route('admin.speed-networking.index')
                ->with('error', 'Speed Networking event not found.');
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'banner_url' => 'nullable|string|max:500',
            'scheduled_start_time' => 'nullable|date',
            'scheduled_end_time' => 'nullable|date',
            'round_duration_seconds' => 'nullable|integer|min:10|max:3600',
            'break_duration_seconds' => 'nullable|integer|min:5|max:1800',
            'total_rounds' => 'nullable|integer|min:0|max:100',
            'ticket_price' => 'nullable|numeric|min:0',
            'is_paid' => 'nullable|boolean',
            'is_free' => 'nullable|boolean',
            'currency' => 'nullable|string|max:10',
            'circle_id' => 'nullable|uuid',
            'circle_restricted' => 'nullable|boolean',
        ]);

        if (array_key_exists('total_rounds', $validated)) {
            $validated['total_rounds'] = ! empty($validated['total_rounds']) ? (int) $validated['total_rounds'] : 0;
        }

        $this->speedNetworkingService->createOrUpdateEvent($validated, $id);

        return redirect()
            ->route('admin.speed-networking.show', $id)
            ->with('success', 'Event settings updated successfully.');
    }

    /**
     * Display the Live Control Room & Monitor for an event.
     */
    public function show(string $id): View|RedirectResponse
    {
        $target = $this->findEventOrBaseEvent($id);

        if (! $target) {
            return redirect()
                ->route('admin.speed-networking.index')
                ->with('error', 'Speed Networking session not found or has been removed.');
        }

        if ($target['type'] === 'sn') {
            /** @var SpeedNetworkingEvent $event */
            $event = $target['event'];
            $details = $this->speedNetworkingService->getEventDetails($event);
            $liveData = $this->speedNetworkingService->getLiveTables($event);
            $participantsData = $this->speedNetworkingService->getEventParticipants($event, 100);
            $matchesData = $this->speedNetworkingService->getEventMatches($event, [], 100);

            $roundDuration = (int) ($event->round_duration_seconds ?: 300);
            $remainingSeconds = (int) ($liveData['remaining_seconds'] ?? $roundDuration);

            return view('admin.speed-networking.show', [
                'event' => $event,
                'stats' => $details['stats'],
                'liveData' => $liveData,
                'participants' => $participantsData['data'],
                'matchesData' => $matchesData,
                'remainingSeconds' => $remainingSeconds,
            ]);
        }

        /** @var Event $baseEvent */
        $baseEvent = $target['event'];
        $meta = $baseEvent->metadata ?? [];
        $roundDuration = (int) ($meta['round_duration_seconds'] ?? 300);
        $breakDuration = (int) ($meta['break_duration_seconds'] ?? 20);
        $totalRounds = (int) ($meta['total_rounds'] ?? 5);
        $currentRound = (int) ($meta['current_round'] ?? 1);
        $speedStatus = (string) ($meta['speed_status'] ?? 'lobby_open');

        $remainingSeconds = $roundDuration;
        if ($speedStatus === 'in_progress' && ! empty($meta['round_started_at'])) {
            $started = Carbon::parse($meta['round_started_at']);
            $elapsed = Carbon::now()->diffInSeconds($started);
            $remainingSeconds = max(0, $roundDuration - $elapsed);
        }

        // Fetch real registered users from both speed_networking_participants and registrations
        $participantsMap = [];
        try {
            if (Schema::hasTable('speed_networking_participants')) {
                $snList = SpeedNetworkingParticipant::with('user')
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
                            'city' => (string) ($u->city ?? 'Peers Global'),
                            'industry' => (string) ($u->business_sub_category ?? $u->industry ?? 'Business'),
                            'avatar_url' => $u->profile_photo_url ?? null,
                            'is_online' => true,
                            'status' => $p->status ?? 'in_call',
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        try {
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
                        'city' => (string) ($u->city ?? 'Peers Global'),
                        'industry' => (string) ($u->business_sub_category ?? $u->industry ?? 'Business'),
                        'avatar_url' => $u->profile_photo_url ?? null,
                        'is_online' => true,
                        'status' => $speedStatus === 'in_progress' ? 'in_call' : 'lobby',
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        $participants = array_values($participantsMap);

        // Generate tables with real participants
        $tables = [];
        $tableCount = (int) ceil(count($participants) / 2);
        for ($i = 0; $i < $tableCount; $i++) {
            $userA = $participants[$i * 2] ?? null;
            $userB = $participants[$i * 2 + 1] ?? null;
            $roomHash = substr(md5($baseEvent->id.($i + 1).$currentRound), 0, 6);
            $roomCode = 'sn_'.substr($baseEvent->id, 0, 8).'_r'.max(1, $currentRound).'_t'.($i + 1).'_'.$roomHash;

            $tables[] = [
                'match_id' => 'tbl_match_'.($i + 1),
                'table_number' => $i + 1,
                'channel_id' => $roomCode,
                'status' => $speedStatus === 'in_progress' ? 'active' : 'waiting',
                'is_break_round' => false,
                'user_a' => $userA,
                'user_b' => $userB,
                'started_at' => $meta['round_started_at'] ?? Carbon::now()->toIso8601String(),
            ];
        }

        $eventObj = (object) [
            'id' => $baseEvent->id,
            'title' => $baseEvent->title,
            'description' => $baseEvent->description,
            'status' => $speedStatus,
            'round_duration_seconds' => $roundDuration,
            'break_duration_seconds' => $breakDuration,
            'total_rounds' => $totalRounds,
            'current_round' => max(1, $currentRound),
            'scheduled_start_time' => $baseEvent->start_at,
        ];

        return view('admin.speed-networking.show', [
            'event' => $eventObj,
            'stats' => [
                'total_participants' => count($participants),
                'active_participants' => count($participants),
                'total_matches' => count($tables),
                'active_matches' => $speedStatus === 'in_progress' ? count($tables) : 0,
            ],
            'liveData' => [
                'tables' => $tables,
            ],
            'participants' => $participants,
            'remainingSeconds' => $remainingSeconds,
        ]);
    }

    /**
     * Start the event from Admin Panel.
     */
    public function start(string $id): RedirectResponse
    {
        $target = $this->findEventOrBaseEvent($id);

        if (! $target) {
            return redirect()
                ->route('admin.speed-networking.index')
                ->with('error', 'Speed Networking event not found.');
        }

        if ($target['type'] === 'sn') {
            /** @var SpeedNetworkingEvent $event */
            $event = $target['event'];
            $this->speedNetworkingService->startEvent($event);

            return redirect()
                ->route('admin.speed-networking.show', $event->id)
                ->with('success', 'Event started! Round 1 matchups and table rooms have been generated.');
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

        return redirect()
            ->route('admin.speed-networking.show', $baseEvent->id)
            ->with('success', 'Event started! Round 1 matchups and table rooms have been generated.');
    }

    /**
     * Force next round from Admin Panel.
     */
    public function nextRound(string $id): RedirectResponse
    {
        $target = $this->findEventOrBaseEvent($id);

        if (! $target) {
            return redirect()
                ->route('admin.speed-networking.index')
                ->with('error', 'Speed Networking event not found.');
        }

        if ($target['type'] === 'sn') {
            /** @var SpeedNetworkingEvent $event */
            $event = $target['event'];
            $updated = $this->speedNetworkingService->nextRound($event);

            if ($updated->status === SpeedNetworkingEvent::STATUS_COMPLETED) {
                return redirect()
                    ->route('admin.speed-networking.show', $event->id)
                    ->with('success', 'Event has completed all rounds!');
            }

            return redirect()
                ->route('admin.speed-networking.show', $event->id)
                ->with('success', "Advanced to Round {$updated->current_round}!");
        }

        /** @var Event $baseEvent */
        $baseEvent = $target['event'];
        $meta = $baseEvent->metadata ?? [];
        $total = (int) ($meta['total_rounds'] ?? 5);
        $current = (int) ($meta['current_round'] ?? 1);
        $next = $current + 1;

        if ($next > $total) {
            $meta['speed_status'] = 'completed';
            $baseEvent->metadata = $meta;
            $baseEvent->save();

            return redirect()
                ->route('admin.speed-networking.show', $baseEvent->id)
                ->with('success', 'Event has completed all rounds!');
        }

        $meta['current_round'] = $next;
        $meta['round_started_at'] = Carbon::now()->toIso8601String();
        $baseEvent->metadata = $meta;
        $baseEvent->save();

        return redirect()
            ->route('admin.speed-networking.show', $baseEvent->id)
            ->with('success', "Advanced to Round {$next}!");
    }

    /**
     * End event from Admin Panel.
     */
    public function end(string $id): RedirectResponse
    {
        $target = $this->findEventOrBaseEvent($id);

        if (! $target) {
            return redirect()
                ->route('admin.speed-networking.index')
                ->with('error', 'Speed Networking event not found.');
        }

        if ($target['type'] === 'sn') {
            /** @var SpeedNetworkingEvent $event */
            $event = $target['event'];
            $this->speedNetworkingService->endEvent($event);

            return redirect()
                ->route('admin.speed-networking.show', $event->id)
                ->with('success', 'Event ended successfully.');
        }

        /** @var Event $baseEvent */
        $baseEvent = $target['event'];
        $meta = $baseEvent->metadata ?? [];
        $meta['speed_status'] = 'completed';
        $baseEvent->metadata = $meta;
        $baseEvent->save();

        return redirect()
            ->route('admin.speed-networking.show', $baseEvent->id)
            ->with('success', 'Event ended successfully.');
    }

    /**
     * Delete an event.
     */
    public function destroy(string $id): RedirectResponse
    {
        $target = $this->findEventOrBaseEvent($id);

        if (! $target) {
            return redirect()
                ->route('admin.speed-networking.index')
                ->with('error', 'Speed Networking event not found.');
        }

        if ($target['type'] === 'sn') {
            /** @var SpeedNetworkingEvent $event */
            $event = $target['event'];
            $this->speedNetworkingService->deleteEvent($event);
        } else {
            /** @var Event $baseEvent */
            $baseEvent = $target['event'];
            $baseEvent->delete();
        }

        return redirect()
            ->route('admin.speed-networking.index')
            ->with('success', 'Speed Networking event deleted.');
    }

    /**
     * Pause event from Admin Panel.
     */
    public function pause(string $id): RedirectResponse
    {
        $target = $this->findEventOrBaseEvent($id);
        if (! $target) {
            return redirect()->route('admin.speed-networking.index')->with('error', 'Speed Networking event not found.');
        }

        if ($target['type'] === 'sn') {
            $this->speedNetworkingService->pauseEvent($target['event']);
        } else {
            $baseEvent = $target['event'];
            $meta = $baseEvent->metadata ?? [];
            $meta['speed_status'] = 'paused';
            $baseEvent->metadata = $meta;
            $baseEvent->save();
        }

        return redirect()->route('admin.speed-networking.show', $id)->with('success', 'Event timers paused.');
    }

    /**
     * Resume event from Admin Panel.
     */
    public function resume(string $id): RedirectResponse
    {
        $target = $this->findEventOrBaseEvent($id);
        if (! $target) {
            return redirect()->route('admin.speed-networking.index')->with('error', 'Speed Networking event not found.');
        }

        if ($target['type'] === 'sn') {
            $this->speedNetworkingService->resumeEvent($target['event']);
        } else {
            $baseEvent = $target['event'];
            $meta = $baseEvent->metadata ?? [];
            $meta['speed_status'] = 'in_progress';
            $baseEvent->metadata = $meta;
            $baseEvent->save();
        }

        return redirect()->route('admin.speed-networking.show', $id)->with('success', 'Event timers resumed.');
    }

    /**
     * Rematch waiting attendees from Admin Panel.
     */
    public function rematch(string $id): RedirectResponse
    {
        $target = $this->findEventOrBaseEvent($id);
        if (! $target) {
            return redirect()->route('admin.speed-networking.index')->with('error', 'Speed Networking event not found.');
        }

        if ($target['type'] === 'sn') {
            $this->speedNetworkingService->rematchEvent($target['event']);
        }

        return redirect()->route('admin.speed-networking.show', $id)->with('success', 'Rematch generated for active round.');
    }
}
