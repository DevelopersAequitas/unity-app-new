<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Event\UpsertAdminEventRequest;
use App\Http\Resources\Event\EventDetailResource;
use App\Models\Event;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Services\Events\EventService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class EventAdminController extends BaseApiController
{
    public function __construct(private readonly EventService $events) {}

    public function index(Request $request): JsonResponse
    {
        // Auto-mirror any SpeedNetworkingEvents not yet in events table so they show in Events section
        if (Schema::hasTable('speed_networking_events')) {
            try {
                $missingSnEvents = SpeedNetworkingEvent::whereNotIn('id', Event::pluck('id'))->get();
                foreach ($missingSnEvents as $sn) {
                    Event::create([
                        'id' => $sn->id,
                        'title' => $sn->title,
                        'description' => $sn->description,
                        'banner_url' => $sn->banner_url,
                        'event_type' => 'speed_networking',
                        'mode' => 'online',
                        'is_virtual' => true,
                        'start_at' => $sn->scheduled_start_time ?? Carbon::now(),
                        'end_at' => $sn->scheduled_start_time ? Carbon::parse($sn->scheduled_start_time)->addHours(2) : Carbon::now()->addHours(2),
                        'metadata' => [
                            'round_duration_seconds' => $sn->round_duration_seconds,
                            'break_duration_seconds' => $sn->break_duration_seconds,
                            'total_rounds' => $sn->total_rounds,
                            'current_round' => $sn->current_round,
                            'speed_status' => $sn->status,
                        ],
                    ]);
                }
            } catch (\Throwable $e) {
                // Ignore sync errors
            }
        }

        $items = Event::query()
            ->with(['circle', 'circles.cityRef', 'occurrences'])
            ->withCount(['registrations', 'occurrences'])
            ->when($request->input('event_type'), fn ($q, $v) => $q->where('event_type', $v))
            ->when($request->input('circle_id'), fn ($q, $v) => $q->where('circle_id', $v))
            ->latest('start_at')
            ->paginate(max(1, min((int) $request->input('per_page', 20), 100)));

        return $this->success($items, 'Admin events fetched successfully.');
    }

    public function store(UpsertAdminEventRequest $request): JsonResponse
    {
        $event = $this->events->create($request->validated(), $request->user());

        // When created in events section with type speed_networking, sync to speed_networking_events table
        if (($event->event_type === 'speed_networking' || $request->input('event_type') === 'speed_networking') && Schema::hasTable('speed_networking_events')) {
            try {
                $meta = $event->metadata ?? [];
                SpeedNetworkingEvent::updateOrCreate(
                    ['id' => $event->id],
                    [
                        'title' => $event->title,
                        'description' => $event->description,
                        'banner_url' => $event->banner_url,
                        'status' => SpeedNetworkingEvent::STATUS_SCHEDULED,
                        'round_duration_seconds' => (int) ($event->round_duration_seconds ?? $meta['round_duration_seconds'] ?? 300),
                        'break_duration_seconds' => (int) ($meta['break_duration_seconds'] ?? 20),
                        'total_rounds' => (int) ($meta['total_rounds'] ?? 5),
                        'current_round' => 0,
                        'scheduled_start_time' => $event->start_at,
                    ]
                );
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        return $this->success(new EventDetailResource($event), 'Event created successfully.', 201);
    }

    public function show(string $id): JsonResponse
    {
        $event = Event::query()
            ->with(['circle', 'circles.cityRef', 'occurrences' => fn ($q) => $q->withCount(['registrations as registered_count' => fn ($r) => $r->where('status', '!=', 'cancelled')])->orderBy('start_at')])
            ->withCount(['registrations', 'occurrences'])
            ->findOrFail($id);

        return $this->success(new EventDetailResource($event), 'Event fetched successfully.');
    }

    public function update(UpsertAdminEventRequest $request, string $id): JsonResponse
    {
        $event = $this->events->update(Event::query()->findOrFail($id), $request->validated());

        if (($event->event_type === 'speed_networking' || $request->input('event_type') === 'speed_networking') && Schema::hasTable('speed_networking_events')) {
            try {
                $meta = $event->metadata ?? [];
                SpeedNetworkingEvent::updateOrCreate(
                    ['id' => $event->id],
                    [
                        'title' => $event->title,
                        'description' => $event->description,
                        'banner_url' => $event->banner_url,
                        'round_duration_seconds' => (int) ($event->round_duration_seconds ?? $meta['round_duration_seconds'] ?? 300),
                        'break_duration_seconds' => (int) ($meta['break_duration_seconds'] ?? 20),
                        'total_rounds' => (int) ($meta['total_rounds'] ?? 5),
                        'scheduled_start_time' => $event->start_at,
                    ]
                );
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        return $this->success(new EventDetailResource($event), 'Event updated successfully.');
    }

    public function destroy(string $id): JsonResponse
    {
        Event::query()->findOrFail($id)->delete();
        if (Schema::hasTable('speed_networking_events')) {
            SpeedNetworkingEvent::where('id', $id)->delete();
        }

        return $this->success(['deleted' => true], 'Event deleted successfully.');
    }
}
