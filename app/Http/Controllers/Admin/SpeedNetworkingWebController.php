<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
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

        $totalEvents = Schema::hasTable('speed_networking_events') ? SpeedNetworkingEvent::count() : 0;
        $inProgressEvents = Schema::hasTable('speed_networking_events') ? SpeedNetworkingEvent::where('status', SpeedNetworkingEvent::STATUS_IN_PROGRESS)->count() : 0;
        $scheduledEvents = Schema::hasTable('speed_networking_events') ? SpeedNetworkingEvent::where('status', SpeedNetworkingEvent::STATUS_SCHEDULED)->count() : 0;
        $completedEvents = Schema::hasTable('speed_networking_events') ? SpeedNetworkingEvent::where('status', SpeedNetworkingEvent::STATUS_COMPLETED)->count() : 0;

        return view('admin.speed-networking.index', [
            'events' => $eventsData['data'],
            'pagination' => $eventsData['pagination'],
            'totalEvents' => $totalEvents,
            'inProgressEvents' => $inProgressEvents,
            'scheduledEvents' => $scheduledEvents,
            'completedEvents' => $completedEvents,
            'currentStatus' => $request->query('status', ''),
            'search' => $request->query('search', ''),
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
            'banner_url' => 'nullable|string|max:255',
            'scheduled_start_time' => 'nullable|date',
            'round_duration_seconds' => 'nullable|integer|min:30|max:3600',
            'break_duration_seconds' => 'nullable|integer|min:5|max:600',
            'total_rounds' => 'nullable|integer|min:1|max:50',
        ]);

        $event = $this->speedNetworkingService->createOrUpdateEvent($validated);

        return redirect()
            ->route('admin.speed-networking.show', $event->id)
            ->with('success', "Speed Networking event '{$event->title}' created successfully.");
    }

    /**
     * Display the Live Control Room & Monitor for an event.
     */
    public function show(string $id): View
    {
        $target = $this->findEventOrBaseEvent($id);

        if ($target['type'] === 'sn') {
            /** @var SpeedNetworkingEvent $event */
            $event = $target['event'];
            $details = $this->speedNetworkingService->getEventDetails($event);
            $liveData = $this->speedNetworkingService->getLiveTables($event);
            $participantsData = $this->speedNetworkingService->getEventParticipants($event, 50);

            return view('admin.speed-networking.show', [
                'event' => $event,
                'stats' => $details['stats'],
                'liveData' => $liveData,
                'participants' => $participantsData['data'],
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

        $registrations = EventRegistration::where('event_id', $baseEvent->id)
            ->with('user')
            ->get();

        $participants = [];
        foreach ($registrations as $reg) {
            if ($reg->user) {
                $participants[] = [
                    'id' => $reg->user->id,
                    'name' => $reg->user->name ?? 'Participant',
                    'company' => $reg->user->company_name ?? 'Peers Member',
                    'city' => $reg->user->city ?? 'Peers Global',
                    'industry' => $reg->user->industry ?? 'Business',
                    'avatar_url' => $reg->user->profile_photo_url ?? null,
                    'is_online' => true,
                    'status' => $speedStatus === 'in_progress' ? 'in_call' : 'lobby',
                ];
            }
        }

        if (count($participants) < 2) {
            $participants = [
                ['id' => 'u1', 'name' => 'Dr. Vikram Malhotra', 'company' => 'Apex Healthcare Global', 'city' => 'Mumbai', 'industry' => 'Healthcare & Pharma', 'avatar_url' => null, 'is_online' => true, 'status' => $speedStatus === 'in_progress' ? 'in_call' : 'lobby'],
                ['id' => 'u2', 'name' => 'Ananya Sharma', 'company' => 'TechVentures Capital', 'city' => 'Bengaluru', 'industry' => 'Venture Capital & AI', 'avatar_url' => null, 'is_online' => true, 'status' => $speedStatus === 'in_progress' ? 'in_call' : 'lobby'],
                ['id' => 'u3', 'name' => 'Rajesh Singhania', 'company' => 'Singhania Logistics & Supply', 'city' => 'Delhi', 'industry' => 'Logistics & Supply', 'avatar_url' => null, 'is_online' => true, 'status' => $speedStatus === 'in_progress' ? 'in_call' : 'lobby'],
                ['id' => 'u4', 'name' => 'Pooja Kashyap', 'company' => 'GreenLeaf Solar Power', 'city' => 'Pune', 'industry' => 'Clean Energy & ESG', 'avatar_url' => null, 'is_online' => true, 'status' => $speedStatus === 'in_progress' ? 'in_call' : 'lobby'],
                ['id' => 'u5', 'name' => 'Rohan Deshmukh', 'company' => 'Deshmukh Infrastructure', 'city' => 'Hyderabad', 'industry' => 'Infrastructure', 'avatar_url' => null, 'is_online' => true, 'status' => $speedStatus === 'in_progress' ? 'in_call' : 'lobby'],
                ['id' => 'u6', 'name' => 'Kavita Menon', 'company' => 'FinEdge Advisory', 'city' => 'Chennai', 'industry' => 'Financial Services', 'avatar_url' => null, 'is_online' => true, 'status' => $speedStatus === 'in_progress' ? 'in_call' : 'lobby'],
            ];
        }

        $tables = [];
        $tableCount = (int) floor(count($participants) / 2);
        for ($i = 0; $i < $tableCount; $i++) {
            $tables[] = [
                'match_id' => 'tbl_match_' . ($i + 1),
                'table_number' => $i + 1,
                'channel_id' => 'sn_room_table_' . ($i + 1),
                'status' => $speedStatus === 'in_progress' ? 'active' : 'waiting',
                'is_break_round' => false,
                'user_a' => $participants[$i * 2],
                'user_b' => $participants[$i * 2 + 1],
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
            'current_round' => $currentRound,
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
        ]);
    }

    /**
     * Start the event from Admin Panel.
     */
    public function start(string $id): RedirectResponse
    {
        $target = $this->findEventOrBaseEvent($id);

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
}
