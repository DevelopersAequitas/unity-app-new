<?php

declare(strict_types=1);

namespace App\Services\Events;

use App\Models\Event;
use App\Models\EventFeedback;
use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use App\Models\EventRsvp;
use App\Models\Notifications\AppNotification;
use App\Models\Notifications\NotificationCampaign;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EventFeedbackService
{
    public function __construct(
        private readonly EventService $eventService,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Submit feedback for an event.
     *
     * @param array<string, mixed> $data
     */
    public function submitFeedback(Event $event, User $user, array $data): EventFeedback
    {
        // 1. Verify user attendance (registered/attended/confirmed or checked in)
        if (! $this->isUserEligibleForFeedback($event, $user)) {
            throw new HttpException(403, 'You must attend or register for the event to submit feedback.');
        }

        // 2. Prevent duplicate feedback
        $alreadySubmitted = EventFeedback::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadySubmitted) {
            throw new HttpException(400, 'You have already submitted feedback for this event.');
        }

        $occurrenceId = $data['occurrence_id'] ?? null;
        if (empty($occurrenceId) && ! empty($event->occurrence_id)) {
            $occurrenceId = $event->occurrence_id;
        }

        $tags = isset($data['tags']) && is_array($data['tags']) ? array_values($data['tags']) : [];
        $wouldRecommend = array_key_exists('would_recommend', $data) && $data['would_recommend'] !== null
            ? (bool) $data['would_recommend']
            : true;

        return EventFeedback::create([
            'event_id' => $event->id,
            'occurrence_id' => $occurrenceId,
            'user_id' => $user->id,
            'rating' => (int) $data['rating'],
            'feedback' => $data['feedback'] ?? null,
            'tags' => $tags,
            'would_recommend' => $wouldRecommend,
            'notification_id' => $data['notification_id'] ?? null,
        ]);
    }

    /**
     * Get the authenticated user's feedback for a specific event.
     */
    public function getMyFeedback(string $eventId, string $userId): ?EventFeedback
    {
        return EventFeedback::query()
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Get comprehensive summary and stats for event feedback.
     *
     * @return array<string, mixed>
     */
    public function getFeedbackSummary(Event $event): array
    {
        $feedbacks = EventFeedback::query()
            ->with(['user'])
            ->where('event_id', $event->id)
            ->orderByDesc('created_at')
            ->get();

        $totalResponses = $feedbacks->count();

        $avgRating = 0.0;
        $recommendationRate = 0.0;

        if ($totalResponses > 0) {
            $avgRating = round((float) $feedbacks->avg('rating'), 2);
            $recommendedCount = $feedbacks->filter(fn (EventFeedback $f): bool => $f->would_recommend !== false)->count();
            $recommendationRate = round(($recommendedCount / $totalResponses) * 100, 1);
        }

        $ratingBreakdown = [
            '5_star' => $feedbacks->where('rating', 5)->count(),
            '4_star' => $feedbacks->where('rating', 4)->count(),
            '3_star' => $feedbacks->where('rating', 3)->count(),
            '2_star' => $feedbacks->where('rating', 2)->count(),
            '1_star' => $feedbacks->where('rating', 1)->count(),
        ];

        // Aggregate tags frequency
        $tagCounts = [];
        foreach ($feedbacks as $fb) {
            if (is_array($fb->tags)) {
                foreach ($fb->tags as $tag) {
                    if (is_string($tag) && trim($tag) !== '') {
                        $cleanTag = trim($tag);
                        $tagCounts[$cleanTag] = ($tagCounts[$cleanTag] ?? 0) + 1;
                    }
                }
            }
        }
        arsort($tagCounts);

        $topTags = [];
        foreach ($tagCounts as $tag => $count) {
            $topTags[] = [
                'tag' => $tag,
                'count' => $count,
            ];
        }

        $formattedFeedbacks = $feedbacks->map(function (EventFeedback $item): array {
            $user = $item->user;
            $userName = $user
                ? ($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')))
                : 'Peer Member';

            if ($userName === '') {
                $userName = 'Peer Member';
            }

            return [
                'id' => (string) $item->id,
                'user_name' => $userName,
                'user_avatar' => $this->resolveUserAvatar($user),
                'rating' => (int) $item->rating,
                'feedback' => $item->feedback,
                'tags' => (array) ($item->tags ?? []),
                'would_recommend' => (bool) ($item->would_recommend ?? true),
                'created_at' => $item->created_at?->format('Y-m-d\TH:i:s\Z') ?? now()->format('Y-m-d\TH:i:s\Z'),
            ];
        })->values()->all();

        return [
            'event_id' => (string) $event->id,
            'total_responses' => $totalResponses,
            'average_rating' => $avgRating,
            'recommendation_rate' => $recommendationRate,
            'rating_breakdown' => $ratingBreakdown,
            'top_tags' => $topTags,
            'feedbacks' => $formattedFeedbacks,
        ];
    }

    /**
     * Determine if a user can view the feedback summary (Admin or Circle Organizer).
     */
    public function canViewFeedbackSummary(Event $event, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->eventService->canViewAttendance($event, $user);
    }

    /**
     * Check if a user is eligible to submit feedback for an event.
     */
    public function isUserEligibleForFeedback(Event $event, User $user): bool
    {
        $hasRegistration = false;
        if (Schema::hasTable('event_registrations')) {
            $hasRegistration = EventRegistration::query()
                ->where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->where(function ($q): void {
                    $q->whereIn('status', ['attended', 'confirmed'])
                        ->orWhere('checkin_status', 'checked_in')
                        ->orWhereNotNull('checked_in_at');
                })
                ->exists();
        }

        $hasRsvp = false;
        if (! $hasRegistration && Schema::hasTable('event_rsvps')) {
            $hasRsvp = EventRsvp::query()
                ->where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->where(function ($q): void {
                    $q->where('checked_in', true)
                        ->orWhere('status', 'yes');
                })
                ->exists();
        }

        return $hasRegistration || $hasRsvp;
    }

    /**
     * Automatically send push notifications requesting feedback for completed events.
     * Window: 1 to 2 hours after event end_at timestamp.
     *
     * @return array{events_processed: int, notifications_sent: int, skipped: int}
     */
    public function sendPostEventFeedbackNotifications(): array
    {
        $now = now();
        $windowStart = $now->copy()->subHours(2);
        $windowEnd = $now->copy()->subHour();

        $campaign = NotificationCampaign::query()->where('code', 'post_event_feedback')->first();
        $campaignId = $campaign?->id ? (string) $campaign->id : '019ee3ca-54f4-7060-88b1-0efbc002c82b';

        $eventsProcessed = 0;
        $notificationsSent = 0;
        $skipped = 0;

        // 1. Process recurring event occurrences ended 1-2 hours ago
        if (Schema::hasTable('event_occurrences')) {
            $occurrences = EventOccurrence::query()
                ->with('event')
                ->where('end_at', '>=', $windowStart)
                ->where('end_at', '<=', $windowEnd)
                ->get();

            foreach ($occurrences as $occurrence) {
                $event = $occurrence->event;
                if (! $event) {
                    continue;
                }
                $eventsProcessed++;
                $result = $this->sendFeedbackNotificationForEventOccurrence($event, (string) $occurrence->id, $campaignId);
                $notificationsSent += $result['sent'];
                $skipped += $result['skipped'];
            }
        }

        // 2. Process standalone events ended 1-2 hours ago
        $standaloneEvents = Event::query()
            ->where('end_at', '>=', $windowStart)
            ->where('end_at', '<=', $windowEnd)
            ->when(Schema::hasTable('event_occurrences'), function ($q): void {
                $q->whereDoesntHave('occurrences');
            })
            ->get();

        foreach ($standaloneEvents as $event) {
            $eventsProcessed++;
            $result = $this->sendFeedbackNotificationForEventOccurrence($event, null, $campaignId);
            $notificationsSent += $result['sent'];
            $skipped += $result['skipped'];
        }

        return [
            'events_processed' => $eventsProcessed,
            'notifications_sent' => $notificationsSent,
            'skipped' => $skipped,
        ];
    }

    /**
     * Send feedback notifications to eligible attendees of an event/occurrence.
     *
     * @return array{sent: int, skipped: int}
     */
    public function sendFeedbackNotificationForEventOccurrence(Event $event, ?string $occurrenceId, string $campaignId): array
    {
        $attendeeUserIds = collect();

        // 1. Collect from registrations
        if (Schema::hasTable('event_registrations')) {
            $regUserIds = EventRegistration::query()
                ->where('event_id', $event->id)
                ->when($occurrenceId, fn ($q) => $q->where('occurrence_id', $occurrenceId))
                ->whereNotNull('user_id')
                ->where(function ($q): void {
                    $q->whereIn('status', ['attended', 'confirmed'])
                        ->orWhere('checkin_status', 'checked_in')
                        ->orWhereNotNull('checked_in_at');
                })
                ->pluck('user_id');

            $attendeeUserIds = $attendeeUserIds->merge($regUserIds);
        }

        // 2. Collect from RSVPs
        if (Schema::hasTable('event_rsvps')) {
            $rsvpUserIds = EventRsvp::query()
                ->where('event_id', $event->id)
                ->whereNotNull('user_id')
                ->where(function ($q): void {
                    $q->where('checked_in', true)
                        ->orWhere('status', 'yes');
                })
                ->pluck('user_id');

            $attendeeUserIds = $attendeeUserIds->merge($rsvpUserIds);
        }

        $uniqueUserIds = $attendeeUserIds->unique()->values();

        if ($uniqueUserIds->isEmpty()) {
            return ['sent' => 0, 'skipped' => 0];
        }

        $users = User::query()->whereIn('id', $uniqueUserIds)->get();

        $sentCount = 0;
        $skippedCount = 0;

        $title = "Share feedback for {$event->title}";
        $body = 'How was your event experience?';

        foreach ($users as $user) {
            // Deduplication: Has user already submitted feedback?
            $hasSubmitted = EventFeedback::query()
                ->where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->exists();

            if ($hasSubmitted) {
                $skippedCount++;
                continue;
            }

            // Deduplication: One feedback request per event occurrence
            $dedupeKey = 'post_event_feedback:'.$event->id.':'.($occurrenceId ?? 'single').':'.$user->id;

            $alreadyNotified = AppNotification::query()
                ->where('user_id', $user->id)
                ->where('type', 'post_event_feedback')
                ->where('reference_type', 'event')
                ->where('reference_id', (string) $event->id)
                ->when($occurrenceId, fn ($q) => $q->where('data->occurrence_id', (string) $occurrenceId))
                ->exists();

            if ($alreadyNotified) {
                $skippedCount++;
                continue;
            }

            $payloadData = [
                'type' => 'post_event_feedback',
                'activity_type' => 'post_event_feedback',
                'category' => 'post_event_feedback',
                'screen' => 'event_feedback',
                'navigation_screen' => 'event_feedback',
                'tap_destination' => 'event_feedback',
                'reference_type' => 'event',
                'reference_id' => (string) $event->id,
                'event_id' => (string) $event->id,
                'occurrence_id' => $occurrenceId ? (string) $occurrenceId : null,
                'user_id' => (string) $user->id,
                'member_id' => (string) $user->id,
                'campaign_id' => $campaignId,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'dedupe_key' => $dedupeKey,
            ];

            $notification = $this->notificationService->sendToUser(
                $user,
                'post_event_feedback',
                $title,
                $body,
                $payloadData,
                [
                    'campaign_id' => $campaignId,
                    'channel' => 'push',
                    'priority' => 'medium',
                    'screen' => 'event_feedback',
                    'reference_type' => 'event',
                    'reference_id' => (string) $event->id,
                    'dedupe_key' => $dedupeKey,
                ]
            );

            if ($notification) {
                $sentCount++;
            } else {
                $skippedCount++;
            }
        }

        return ['sent' => $sentCount, 'skipped' => $skippedCount];
    }

    /**
     * Resolve avatar URL for a user.
     */
    private function resolveUserAvatar(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        if (! empty($user->profile_photo_url)) {
            return (string) $user->profile_photo_url;
        }

        if (! empty($user->profile_photo_id)) {
            return url('/api/v1/files/'.$user->profile_photo_id);
        }

        return null;
    }
}
