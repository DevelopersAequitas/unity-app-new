<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\SpeedNetworking;

use App\Http\Controllers\Controller;
use App\Http\Requests\SpeedNetworking\CreateSpeedNetworkingEventRequest;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Services\SpeedNetworking\SpeedNetworkingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpeedNetworkingAdminController extends Controller
{
    public function __construct(
        protected SpeedNetworkingService $speedNetworkingService
    ) {}

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
        $event = SpeedNetworkingEvent::findOrFail($id);
        $details = $this->speedNetworkingService->getEventDetails($event);

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
        $event = SpeedNetworkingEvent::findOrFail($id);
        $this->speedNetworkingService->deleteEvent($event);

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
        $event = SpeedNetworkingEvent::findOrFail($id);
        $perPage = (int) $request->query('per_page', 25);

        $result = $this->speedNetworkingService->getEventParticipants($event, $perPage);

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'pagination' => $result['pagination'],
        ]);
    }

    /**
     * Start event and trigger Round 1 matchmaking.
     */
    public function start(string $id): JsonResponse
    {
        $event = SpeedNetworkingEvent::findOrFail($id);
        $updatedEvent = $this->speedNetworkingService->startEvent($event);

        return response()->json([
            'success' => true,
            'message' => 'Speed networking event started and Round 1 generated.',
            'data' => $updatedEvent,
        ]);
    }

    /**
     * Force advance to next round.
     */
    public function nextRound(string $id): JsonResponse
    {
        $event = SpeedNetworkingEvent::findOrFail($id);
        $updatedEvent = $this->speedNetworkingService->nextRound($event);

        return response()->json([
            'success' => true,
            'message' => 'Round advanced successfully.',
            'data' => $updatedEvent,
        ]);
    }

    /**
     * End the event.
     */
    public function end(string $id): JsonResponse
    {
        $event = SpeedNetworkingEvent::findOrFail($id);
        $updatedEvent = $this->speedNetworkingService->endEvent($event);

        return response()->json([
            'success' => true,
            'message' => 'Speed networking event ended successfully.',
            'data' => $updatedEvent,
        ]);
    }

    /**
     * Get live tables monitor for real-time admin dashboard.
     */
    public function liveTables(string $id): JsonResponse
    {
        $event = SpeedNetworkingEvent::findOrFail($id);
        $data = $this->speedNetworkingService->getLiveTables($event);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
