<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Services\SpeedNetworking\SpeedNetworkingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpeedNetworkingWebController extends Controller
{
    public function __construct(
        protected SpeedNetworkingService $speedNetworkingService
    ) {}

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

        $totalEvents = SpeedNetworkingEvent::count();
        $inProgressEvents = SpeedNetworkingEvent::where('status', SpeedNetworkingEvent::STATUS_IN_PROGRESS)->count();
        $scheduledEvents = SpeedNetworkingEvent::where('status', SpeedNetworkingEvent::STATUS_SCHEDULED)->count();
        $completedEvents = SpeedNetworkingEvent::where('status', SpeedNetworkingEvent::STATUS_COMPLETED)->count();

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
        $event = SpeedNetworkingEvent::findOrFail($id);
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

    /**
     * Start the event from Admin Panel.
     */
    public function start(string $id): RedirectResponse
    {
        $event = SpeedNetworkingEvent::findOrFail($id);
        $this->speedNetworkingService->startEvent($event);

        return redirect()
            ->route('admin.speed-networking.show', $event->id)
            ->with('success', 'Event started! Round 1 matchups and table rooms have been generated.');
    }

    /**
     * Force next round from Admin Panel.
     */
    public function nextRound(string $id): RedirectResponse
    {
        $event = SpeedNetworkingEvent::findOrFail($id);
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

    /**
     * End event from Admin Panel.
     */
    public function end(string $id): RedirectResponse
    {
        $event = SpeedNetworkingEvent::findOrFail($id);
        $this->speedNetworkingService->endEvent($event);

        return redirect()
            ->route('admin.speed-networking.show', $event->id)
            ->with('success', 'Event ended successfully.');
    }

    /**
     * Delete an event.
     */
    public function destroy(string $id): RedirectResponse
    {
        $event = SpeedNetworkingEvent::findOrFail($id);
        $this->speedNetworkingService->deleteEvent($event);

        return redirect()
            ->route('admin.speed-networking.index')
            ->with('success', 'Speed Networking event deleted.');
    }
}
