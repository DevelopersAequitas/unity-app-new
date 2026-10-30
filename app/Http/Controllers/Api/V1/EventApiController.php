<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Circle;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventQrScanLog;
use App\Models\EventRegistration;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class EventApiController extends BaseApiController
{
    public function allEvents(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'circle_id' => ['nullable', 'uuid'],
            'status' => ['nullable', 'string', Rule::in(['all', 'today', 'live', 'upcoming', 'past', 'completed'])],
        ]);

        $circleId = $validated['circle_id'] ?? null;
        $status = $validated['status'] ?? 'all';
        $timezone = config('app.timezone') ?: 'UTC';
        $now = now($timezone);
        $circle = null;

        if ($circleId) {
            $circle = Circle::query()->find($circleId);

            if (! $circle) {
                return response()->json([
                    'success' => false,
                    'message' => 'Circle not found.',
                    'data' => null,
                ], 404);
            }
        }

        $events = Event::query()
            ->with([
                'circle',
                'circles.cityRef',
                'occurrences' => fn ($query) => $query->where(function ($q): void {
                    $q->whereNull('status')
                        ->orWhereNotIn('status', ['cancelled', 'canceled', 'rejected', 'deleted', 'archived', 'inactive']);
                })->orderBy('start_at'),
            ])
            ->when($circleId, fn ($query) => $query->where(function ($circleQuery) use ($circleId): void {
                $circleQuery->where('circle_id', $circleId)->orWhereHas('circles', fn ($multiCircleQuery) => $multiCircleQuery->where('circles.id', $circleId));
            }));

        $this->applyActiveEventScope($events);

        $eventItems = $events->get()
            ->flatMap(fn (Event $event) => $this->expandEventItems($event, $timezone))
            ->sortBy('_start_at')
            ->values();

        $grouped = $this->groupEventItems($eventItems, $now);

        if ($status !== 'all') {
            $isPast = in_array($status, ['past', 'completed'], true);
            $grouped = [
                'today_events' => $status === 'today' ? $grouped['today_events'] : [],
                'live_events' => $status === 'live' ? $grouped['live_events'] : [],
                'upcoming_events' => $status === 'upcoming' ? $grouped['upcoming_events'] : [],
                'past_events' => $isPast ? $grouped['past_events'] : [],
            ];
        }

        $total = count($grouped['today_events'])
            + count($grouped['live_events'])
            + count($grouped['upcoming_events'])
            + count($grouped['past_events']);

        return $this->success([
            'circle' => $circle ? $this->circlePayload($circle) : null,
            'server_now' => $now->toIso8601String(),
            'app_timezone' => $timezone,
            'total' => $total,
            'today_events' => $grouped['today_events'],
            'live_events' => $grouped['live_events'],
            'upcoming_events' => $grouped['upcoming_events'],
            'past_events' => $grouped['past_events'],
            'completed_events' => $grouped['past_events'],
        ], 'Circle events fetched successfully.');
    }

    private function applyActiveEventScope($query): void
    {
        if (Schema::hasColumn('events', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('events', 'status')) {
            $query->whereNotIn('status', ['cancelled', 'canceled', 'rejected', 'deleted', 'archived', 'inactive']);
        }
    }

    private function expandEventItems(Event $event, string $timezone): Collection
    {
        if (! (bool) ($event->is_active ?? true)) {
            return collect();
        }

        if ($event->occurrences->isNotEmpty()) {
            $now = Carbon::now($timezone);
            $eventStartUtc = $event->start_at ? Carbon::parse($event->start_at) : null;
            $isEventFuture = $eventStartUtc && $eventStartUtc->greaterThan($now);

            $validOccurrences = $event->occurrences->filter(function (EventOccurrence $occ): bool {
                $status = strtolower((string) ($occ->status ?? ''));

                return ! in_array($status, ['cancelled', 'canceled', 'rejected', 'deleted', 'archived', 'inactive'], true);
            });

            $upcomingOccurrences = $validOccurrences->filter(function (EventOccurrence $occ) use ($now, $isEventFuture, $eventStartUtc): bool {
                if ($isEventFuture && $occ->start_at) {
                    $occStart = Carbon::parse($occ->start_at);
                    if ($occStart->lessThan($eventStartUtc->copy()->subMinutes(15))) {
                        return false;
                    }
                }

                $end = $occ->end_at ?? $occ->start_at;

                return $end ? Carbon::parse($end)->greaterThanOrEqualTo($now->copy()->startOfDay()) : true;
            });

            $items = collect();

            $selectedUpcoming = $upcomingOccurrences->first();
            if ($selectedUpcoming) {
                $items->push($this->eventItemPayload($event, $selectedUpcoming, $selectedUpcoming->start_at, $selectedUpcoming->end_at, $timezone));
            }

            $pastOccurrences = $validOccurrences->filter(function (EventOccurrence $occ) use ($now, $timezone, $selectedUpcoming): bool {
                if ($selectedUpcoming && $occ->id === $selectedUpcoming->id) {
                    return false;
                }

                $status = strtolower((string) ($occ->status ?? ''));
                if ($status === 'completed' || $status === 'complete') {
                    return true;
                }

                $end = $occ->end_at ?? $occ->start_at;
                if (! $end) {
                    return false;
                }

                return Carbon::parse($end)->setTimezone($timezone)->lessThan($now);
            });

            foreach ($pastOccurrences as $pastOcc) {
                $items->push($this->eventItemPayload($event, $pastOcc, $pastOcc->start_at, $pastOcc->end_at, $timezone));
            }

            return $items;
        }

        return collect([
            $this->eventItemPayload($event, null, $event->start_at, $event->end_at, $timezone),
        ]);
    }

    private function groupEventItems(Collection $eventItems, Carbon $now): array
    {
        $today = $now->toDateString();

        $liveEvents = [];
        $todayEvents = [];
        $upcomingEvents = [];
        $pastEvents = [];

        foreach ($eventItems as $item) {
            $startAt = $item['_start_at'];
            $endAt = $item['_end_at'];

            if (! $startAt instanceof Carbon) {
                continue;
            }

            $payload = $item['payload'];

            $isLive = $endAt instanceof Carbon && $startAt->lessThanOrEqualTo($now) && $endAt->greaterThanOrEqualTo($now);
            if ($isLive) {
                $livePayload = $payload;
                $livePayload['group_status'] = 'live';
                $liveEvents[] = $livePayload;

                continue;
            }

            $isPast = ($endAt instanceof Carbon ? $endAt->lessThan($now) : $startAt->lessThan($now))
                || strtolower((string) ($payload['status'] ?? '')) === 'completed';

            if ($isPast) {
                $pastPayload = $payload;
                $pastPayload['group_status'] = 'past';
                if (! in_array(strtolower((string) ($pastPayload['status'] ?? '')), ['cancelled', 'canceled', 'rejected'], true)) {
                    $pastPayload['status'] = 'completed';
                }
                $pastEvents[] = $pastPayload;

                if ($startAt->toDateString() === $today) {
                    $todayPayload = $payload;
                    $todayPayload['group_status'] = 'today';
                    if (! in_array(strtolower((string) ($todayPayload['status'] ?? '')), ['cancelled', 'canceled', 'rejected'], true)) {
                        $todayPayload['status'] = 'completed';
                    }
                    $todayEvents[] = $todayPayload;
                }

                continue;
            }

            if ($startAt->toDateString() === $today) {
                $todayPayload = $payload;
                $todayPayload['group_status'] = 'today';
                $todayEvents[] = $todayPayload;

                continue;
            }

            if ($startAt->greaterThan($now)) {
                $upcomingPayload = $payload;
                $upcomingPayload['group_status'] = 'upcoming';
                $upcomingEvents[] = $upcomingPayload;
            }
        }

        usort($pastEvents, function (array $a, array $b): int {
            $aTime = $a['start_at'] ?? '';
            $bTime = $b['start_at'] ?? '';

            return strcmp($bTime, $aTime);
        });

        return [
            'today_events' => $todayEvents,
            'live_events' => $liveEvents,
            'upcoming_events' => $upcomingEvents,
            'past_events' => $pastEvents,
        ];
    }

    private function eventItemPayload(Event $event, ?EventOccurrence $occurrence, mixed $startAt, mixed $endAt, string $timezone): array
    {
        $occurrenceId = $occurrence?->id;
        $circlePayload = $event->circle ? $this->circlePayload($event->circle) : null;
        $circles = $event->relationLoaded('circles') ? $event->circles->map(fn (Circle $circle) => $this->circlePayload($circle))->values()->all() : [];
        if ($circles === [] && $circlePayload) {
            $circles = [$circlePayload];
        }
        $groupingStartAt = $this->dateTimeForGrouping($startAt, $timezone);
        $groupingEndAt = $this->dateTimeForGrouping($endAt, $timezone);
        $responseStartAt = $this->dateTimeForResponse($startAt);
        $responseEndAt = $this->dateTimeForResponse($endAt);

        $rawStatus = strtolower((string) ($occurrence?->status ?? $event->status ?? 'scheduled'));
        $status = $rawStatus;
        if (! in_array($rawStatus, ['cancelled', 'canceled', 'rejected'], true)) {
            $compareEnd = $groupingEndAt ?? $groupingStartAt;
            if ($compareEnd && $compareEnd->lessThan(now($timezone))) {
                $status = 'completed';
            }
        }

        $durationMinutes = null;
        $durationSeconds = null;
        $scheduledEndIso = null;

        $isSpeedNetworking = $event->isSpeedNetworking() || in_array($event->event_type, ['speed_networking', 'virtual_networking'], true);

        if ($isSpeedNetworking) {
            $snEvent = null;
            if (Schema::hasTable('speed_networking_events')) {
                try {
                    $snEvent = SpeedNetworkingEvent::find($event->id);
                } catch (\Throwable) {
                }
            }

            if ($snEvent) {
                $durationSeconds = $snEvent->duration_seconds;
                $durationMinutes = $snEvent->duration_minutes;
                $scheduledEndIso = $snEvent->scheduled_end_time?->copy()->utc()->toISOString();
            } else {
                $meta = is_array($event->metadata) ? $event->metadata : (json_decode((string) $event->metadata, true) ?: []);
                $roundDuration = (int) ($meta['round_duration_seconds'] ?? 180);
                $breakDuration = (int) ($meta['break_duration_seconds'] ?? 20);
                $totalRounds = (int) ($meta['total_rounds'] ?? 0);

                if (! empty($meta['duration_seconds'])) {
                    $durationSeconds = (int) $meta['duration_seconds'];
                } elseif (! empty($meta['duration_minutes'])) {
                    $durationSeconds = (int) $meta['duration_minutes'] * 60;
                } elseif (! empty($meta['duration'])) {
                    $durationSeconds = (int) ($meta['duration'] * 60);
                } elseif ($totalRounds > 0) {
                    $durationSeconds = ($totalRounds * $roundDuration) + (max(0, $totalRounds - 1) * $breakDuration);
                } elseif ($endAt && $startAt) {
                    $durationSeconds = max(0, Carbon::parse($endAt)->diffInSeconds(Carbon::parse($startAt)));
                } else {
                    $durationSeconds = (5 * $roundDuration) + (4 * $breakDuration);
                }

                $durationMinutes = (int) ceil($durationSeconds / 60);
                $calculatedEnd = $endAt ? Carbon::parse($endAt) : ($startAt ? Carbon::parse($startAt)->addSeconds($durationSeconds) : null);
                $scheduledEndIso = $calculatedEnd?->copy()->utc()->toISOString();
            }
        } elseif ($endAt && $startAt) {
            $durationSeconds = max(0, Carbon::parse($endAt)->diffInSeconds(Carbon::parse($startAt)));
            $durationMinutes = (int) ceil($durationSeconds / 60);
            $scheduledEndIso = Carbon::parse($endAt)->copy()->utc()->toISOString();
        }

        return [
            '_start_at' => $groupingStartAt,
            '_end_at' => $groupingEndAt,
            'payload' => [
                'event_id' => $event->id,
                'occurrence_id' => $occurrenceId,
                'title' => $event->title,
                'description' => $event->description,
                'event_type' => $event->event_type,
                'event_category' => $event->event_category,
                'mode' => $event->mode,
                'start_at' => $responseStartAt?->format('Y-m-d\TH:i:s'),
                'end_at' => $responseEndAt?->format('Y-m-d\TH:i:s'),
                'scheduled_start_time' => $responseStartAt?->copy()->utc()->toISOString(),
                'scheduled_end_time' => $scheduledEndIso,
                'duration_minutes' => $durationMinutes,
                'duration_seconds' => $durationSeconds,
                'formatted_start_at' => $responseStartAt?->format('d M Y h:i A'),
                'recurrence' => $event->recurrence_type,
                'status' => $status,
                'registered_count' => $this->registeredCount($event->id, $occurrenceId),
                'checked_in_count' => $this->checkedInCount($event->id, $occurrenceId),
                'image_url' => $this->imageUrl($event),
                'location' => $event->location_text,
                'meeting_link' => $event->online_meeting_url,
                'circle_id' => $event->circle_id,
                'circle_ids' => collect($circles)->pluck('id')->values()->all(),
                'circles' => $circles,
                'circle' => $circlePayload,
            ],
        ];
    }

    private function dateTimeForGrouping(mixed $dateTime, string $timezone): ?Carbon
    {
        if (! $dateTime) {
            return null;
        }

        return Carbon::parse($dateTime)->setTimezone($timezone);
    }

    private function dateTimeForResponse(mixed $dateTime): ?Carbon
    {
        if (! $dateTime) {
            return null;
        }

        return Carbon::parse($dateTime);
    }

    private function registeredCount(string $eventId, ?string $occurrenceId): int
    {
        $query = EventRegistration::query()
            ->where('event_id', $eventId)
            ->whereNotIn('status', ['cancelled', 'canceled', 'rejected', 'payment_failed']);

        if ($occurrenceId) {
            $query->where('occurrence_id', $occurrenceId);
        }

        return $query->count();
    }

    private function checkedInCount(string $eventId, ?string $occurrenceId): int
    {
        if (! Schema::hasTable('event_qr_scan_logs')) {
            return EventRegistration::query()
                ->where('event_id', $eventId)
                ->when($occurrenceId, fn ($query) => $query->where('occurrence_id', $occurrenceId))
                ->where('checkin_status', 'checked_in')
                ->count();
        }

        $query = EventQrScanLog::query()
            ->where('event_id', $eventId);

        if (Schema::hasColumn('event_qr_scan_logs', 'scan_status')) {
            $query->whereIn('scan_status', ['success', 'already_checked_in']);
        }

        if ($occurrenceId && Schema::hasColumn('event_qr_scan_logs', 'occurrence_id')) {
            $query->where('occurrence_id', $occurrenceId);
        }

        return $query->count();
    }

    private function imageUrl(Event $event): ?string
    {
        $bannerUrl = $event->banner_url;

        if (! is_string($bannerUrl) || trim($bannerUrl) === '') {
            return null;
        }

        $bannerUrl = trim($bannerUrl);

        if (str_starts_with($bannerUrl, 'http://') || str_starts_with($bannerUrl, 'https://') || str_starts_with($bannerUrl, '/')) {
            return $bannerUrl;
        }

        return url('/api/v1/files/'.$bannerUrl);
    }

    private function circlePayload(Circle $circle): array
    {
        return [
            'id' => $circle->id,
            'name' => $circle->name,
            'slug' => $circle->slug,
            'state_name' => $circle->state_name ?? $circle->state ?? $circle->cityRef?->state_name ?? $circle->cityRef?->state ?? null,
        ];
    }
}
