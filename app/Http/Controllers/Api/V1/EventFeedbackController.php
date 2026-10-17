<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Event\StoreEventFeedbackRequest;
use App\Models\Event;
use App\Models\EventFeedback;
use App\Models\EventRegistration;
use App\Models\EventRsvp;
use App\Services\Events\EventFeedbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EventFeedbackController extends BaseApiController
{
    public function __construct(
        private readonly EventFeedbackService $feedbackService,
    ) {}

    /**
     * Submit feedback for an event (POST /api/v1/events/{eventId}/feedback).
     */
    public function storeFeedback(StoreEventFeedbackRequest $request, string $eventId): JsonResponse
    {
        $event = Event::query()->find($eventId);
        if (! $event) {
            return $this->error('Event not found.', 404);
        }

        $validated = $request->validated();
        if (! empty($validated['event_id']) && $validated['event_id'] !== $eventId) {
            return $this->error('Event ID in request body does not match the URL.', 400);
        }
        $validated['event_id'] = $eventId;

        try {
            $feedback = $this->feedbackService->submitFeedback($event, $request->user(), $validated);

            $data = [
                'id' => (string) $feedback->id,
                'event_id' => (string) $feedback->event_id,
                'occurrence_id' => $feedback->occurrence_id ? (string) $feedback->occurrence_id : null,
                'user_id' => (string) $feedback->user_id,
                'rating' => (int) $feedback->rating,
                'feedback' => $feedback->feedback,
                'tags' => (array) ($feedback->tags ?? []),
                'would_recommend' => (bool) ($feedback->would_recommend ?? true),
                'created_at' => $feedback->created_at?->format('Y-m-d\TH:i:s\Z') ?? now()->format('Y-m-d\TH:i:s\Z'),
            ];

            return $this->success($data, 'Feedback submitted successfully.', 201);
        } catch (HttpException $e) {
            return $this->error($e->getMessage(), $e->getStatusCode());
        }
    }

    /**
     * Get the authenticated user's feedback for an event (GET /api/v1/events/{eventId}/my-feedback).
     */
    public function myFeedback(Request $request, string $eventId): JsonResponse
    {
        $event = Event::query()->find($eventId);
        if (! $event) {
            return $this->error('Event not found.', 404);
        }

        $feedback = $this->feedbackService->getMyFeedback($eventId, (string) $request->user()->id);

        if (! $feedback) {
            return $this->success(null, 'No feedback submitted yet.');
        }

        $data = [
            'id' => (string) $feedback->id,
            'event_id' => (string) $feedback->event_id,
            'rating' => (int) $feedback->rating,
            'feedback' => $feedback->feedback,
            'tags' => (array) ($feedback->tags ?? []),
            'would_recommend' => (bool) ($feedback->would_recommend ?? true),
            'created_at' => $feedback->created_at?->format('Y-m-d\TH:i:s\Z') ?? now()->format('Y-m-d\TH:i:s\Z'),
        ];

        return $this->success($data, 'Feedback retrieved successfully.');
    }

    /**
     * Get feedback summary for organizers / circle leadership (GET /api/v1/events/{eventId}/feedback-summary).
     */
    public function feedbackSummary(Request $request, string $eventId): JsonResponse
    {
        $event = Event::query()->find($eventId);
        if (! $event) {
            return $this->error('Event not found.', 404);
        }

        if (! $this->feedbackService->canViewFeedbackSummary($event, $request->user())) {
            return $this->error('Unauthorized to view feedback summary.', 403);
        }

        $summary = $this->feedbackService->getFeedbackSummary($event);

        return $this->success($summary, 'Feedback summary fetched.');
    }

    /**
     * Check if the authenticated user has any pending event feedbacks.
     * Checks for events ended within the last 7 days that the user attended and hasn't reviewed.
     */
    public function checkPending(Request $request): JsonResponse
    {
        $userId = auth()->id();
        if (! $userId) {
            return $this->error('Unauthenticated.', 401);
        }

        // Get all event IDs the user registered and checked into
        $registeredEventIds = EventRegistration::where('user_id', $userId)
            ->where(function ($q): void {
                $q->whereNotNull('checked_in_at')
                    ->orWhere('checkin_status', 'checked_in')
                    ->orWhereIn('status', ['attended', 'confirmed']);
            })
            ->pluck('event_id');

        $rsvpEventIds = EventRsvp::where('user_id', $userId)
            ->where(function ($q): void {
                $q->where('checked_in', true)
                    ->orWhere('status', 'yes');
            })
            ->pluck('event_id');

        $attendedEventIds = $registeredEventIds->merge($rsvpEventIds)->unique();

        if ($attendedEventIds->isEmpty()) {
            return $this->success(null, 'No pending feedbacks.');
        }

        // Find the most recently ended event within the last 7 days that doesn't have feedback yet
        $pendingEvent = Event::query()
            ->whereIn('id', $attendedEventIds)
            ->where('end_at', '<', now())
            ->where('end_at', '>=', now()->subDays(7))
            ->whereNotExists(function ($query) use ($userId): void {
                $query->select(DB::raw(1))
                    ->from('event_feedbacks')
                    ->whereColumn('event_feedbacks.event_id', 'events.id')
                    ->where('event_feedbacks.user_id', $userId);
            })
            ->orderByDesc('end_at')
            ->first(['id', 'title', 'start_at', 'end_at', 'location_text']);

        if (! $pendingEvent) {
            return $this->success(null, 'No pending feedbacks.');
        }

        return $this->success([
            'event' => [
                'id' => $pendingEvent->id,
                'title' => $pendingEvent->title,
                'start_at' => $pendingEvent->start_at,
                'end_at' => $pendingEvent->end_at,
                'location_text' => $pendingEvent->location_text,
            ],
        ], 'Pending event feedback found.');
    }

    /**
     * Legacy submit feedback endpoint (POST /api/v1/event-feedbacks).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'event_id' => ['required', 'uuid', 'exists:events,id'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'overall_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'feedback' => ['nullable', 'string', 'max:2000'],
            'what_worked' => ['nullable', 'string', 'max:2000'],
            'tags' => ['nullable', 'array'],
            'would_recommend' => ['nullable', 'boolean'],
        ]);

        $eventId = (string) $request->input('event_id');
        $event = Event::query()->find($eventId);
        if (! $event) {
            return $this->error('Event not found.', 404);
        }

        $rating = (int) ($request->input('rating') ?? $request->input('overall_rating', 5));
        $feedbackText = $request->input('feedback') ?? $request->input('what_worked');

        try {
            $feedback = $this->feedbackService->submitFeedback($event, $request->user(), [
                'event_id' => $eventId,
                'occurrence_id' => $request->input('occurrence_id'),
                'rating' => $rating,
                'feedback' => $feedbackText,
                'tags' => $request->input('tags', []),
                'would_recommend' => $request->input('would_recommend', true),
                'notification_id' => $request->input('notification_id'),
            ]);

            return $this->success($feedback, 'Feedback submitted successfully.', 201);
        } catch (HttpException $e) {
            return $this->error($e->getMessage(), $e->getStatusCode());
        }
    }

    /**
     * Legacy get all feedbacks submitted by current user (GET /api/v1/event-feedbacks/my).
     */
    public function myFeedbacks(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);

        $feedbacks = EventFeedback::query()
            ->with(['event:id,title,start_at,end_at,location_text'])
            ->where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return $this->success([
            'total' => $feedbacks->total(),
            'current_page' => $feedbacks->currentPage(),
            'per_page' => $feedbacks->perPage(),
            'last_page' => $feedbacks->lastPage(),
            'items' => $feedbacks->items(),
        ], 'My feedbacks fetched successfully.');
    }

    /**
     * Legacy event feedbacks review list (GET /api/v1/event-feedbacks/event/{eventId}).
     */
    public function eventFeedbacks(Request $request, string $eventId): JsonResponse
    {
        $event = Event::query()->find($eventId);
        if (! $event) {
            return $this->error('Event not found.', 404);
        }

        $summary = $this->feedbackService->getFeedbackSummary($event);

        return $this->success($summary, 'Event feedbacks fetched successfully.');
    }
}
