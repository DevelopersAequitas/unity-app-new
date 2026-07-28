<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Event;
use App\Models\EventFeedback;
use App\Models\EventRegistration;
use App\Models\EventRsvp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventFeedbackController extends BaseApiController
{
    /**
     * Check if the authenticated user has any pending event feedbacks.
     * Checks for events ended within the last 7 days that the user attended and hasn't reviewed.
     */
    public function checkPending(Request $request): JsonResponse
    {
        $userId = auth()->id();

        // Get all event IDs the user registered and checked into
        $registeredEventIds = EventRegistration::where('user_id', $userId)
            ->where(function ($q) {
                $q->whereNotNull('checked_in_at')
                    ->orWhere('checkin_status', 'checked_in');
            })
            ->pluck('event_id');

        $rsvpEventIds = EventRsvp::where('user_id', $userId)
            ->where('checked_in', true)
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
            ->whereNotExists(function ($query) use ($userId) {
                $query->select(DB::raw(1))
                    ->from('event_feedback')
                    ->whereColumn('event_feedback.event_id', 'events.id')
                    ->where('event_feedback.respondent_user_id', $userId);
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
     * Submit feedback for an event.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'event_id' => ['required', 'uuid', 'exists:events,id'],
            'overall_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'content_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'venue_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'networking_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'would_recommend' => ['nullable', 'boolean'],
            'what_worked' => ['nullable', 'string', 'max:2000'],
            'what_to_improve' => ['nullable', 'string', 'max:2000'],
            'additional_comments' => ['nullable', 'string', 'max:2000'],
        ]);

        $userId = auth()->id();
        $eventId = $request->event_id;

        // 1. Verify user attendance (must be registered and checked in)
        $attended = EventRegistration::where('user_id', $userId)
            ->where('event_id', $eventId)
            ->where(function ($q) {
                $q->whereNotNull('checked_in_at')
                    ->orWhere('checkin_status', 'checked_in');
            })
            ->exists()
            || EventRsvp::where('user_id', $userId)
                ->where('event_id', $eventId)
                ->where('checked_in', true)
                ->exists();

        if (! $attended) {
            return $this->error('You must attend the event to submit feedback.', 403);
        }

        // 2. Prevent duplicate feedback
        $alreadySubmitted = EventFeedback::where('event_id', $eventId)
            ->where('respondent_user_id', $userId)
            ->exists();

        if ($alreadySubmitted) {
            return $this->error('You have already submitted feedback for this event.', 400);
        }

        $user = auth()->user();
        $displayName = $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
        if (empty($displayName)) {
            $displayName = 'Peer Member';
        }

        $feedback = EventFeedback::create([
            'event_id' => $eventId,
            'respondent_user_id' => $userId,
            'respondent_name' => $displayName,
            'overall_rating' => $request->overall_rating,
            'content_rating' => $request->content_rating,
            'venue_rating' => $request->venue_rating,
            'networking_rating' => $request->networking_rating,
            'would_recommend' => $request->would_recommend,
            'what_worked' => $request->what_worked,
            'what_to_improve' => $request->what_to_improve,
            'additional_comments' => $request->additional_comments,
            'submitted_at' => now(),
        ]);

        return $this->success($feedback, 'Feedback submitted successfully.', 201);
    }

    /**
     * Get all feedbacks submitted by the current user.
     */
    public function myFeedbacks(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);

        $feedbacks = EventFeedback::query()
            ->with(['event:id,title,start_at,end_at,location_text'])
            ->where('respondent_user_id', auth()->id())
            ->orderByDesc('submitted_at')
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
     * Get all feedbacks for a specific event (like product reviews).
     */
    public function eventFeedbacks(Request $request, string $eventId): JsonResponse
    {
        $event = Event::query()->find($eventId);
        if (! $event) {
            return $this->error('Event not found.', 404);
        }

        $perPage = (int) $request->input('per_page', 20);

        // Fetch paginated reviews
        $feedbacks = EventFeedback::query()
            ->where('event_id', $eventId)
            ->orderByDesc('submitted_at')
            ->paginate($perPage);

        // Calculate averages and stats
        $stats = EventFeedback::query()
            ->where('event_id', $eventId)
            ->selectRaw('
                COUNT(*) as total_reviews,
                AVG(overall_rating) as avg_overall,
                AVG(content_rating) as avg_content,
                AVG(venue_rating) as avg_venue,
                AVG(networking_rating) as avg_networking,
                SUM(CASE WHEN would_recommend = true THEN 1 ELSE 0 END) as recommend_count
            ')
            ->first();

        $recommendPercentage = 0;
        if ($stats && $stats->total_reviews > 0) {
            $recommendPercentage = round(($stats->recommend_count / $stats->total_reviews) * 100);
        }

        return $this->success([
            'stats' => [
                'total_reviews' => $stats ? (int) $stats->total_reviews : 0,
                'avg_overall' => $stats && $stats->avg_overall !== null ? round((float) $stats->avg_overall, 1) : 0.0,
                'avg_content' => $stats && $stats->avg_content !== null ? round((float) $stats->avg_content, 1) : null,
                'avg_venue' => $stats && $stats->avg_venue !== null ? round((float) $stats->avg_venue, 1) : null,
                'avg_networking' => $stats && $stats->avg_networking !== null ? round((float) $stats->avg_networking, 1) : null,
                'recommend_percentage' => $recommendPercentage,
            ],
            'pagination' => [
                'total' => $feedbacks->total(),
                'current_page' => $feedbacks->currentPage(),
                'per_page' => $feedbacks->perPage(),
                'last_page' => $feedbacks->lastPage(),
            ],
            'items' => $feedbacks->items(),
        ], 'Event feedbacks fetched successfully.');
    }
}
