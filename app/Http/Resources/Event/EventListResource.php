<?php

namespace App\Http\Resources\Event;

use App\Models\EventOccurrence;
use App\Services\Events\EventService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $eventService = app(EventService::class);
        $isEligible = $eventService->isEligible($this->resource, $request->user());
        $canRegister = $eventService->canRegister($this->resource, $request->user());
        $occurrences = $this->occurrences ?? collect();
        $upcoming = $occurrences->filter(fn (EventOccurrence $occurrence) => $occurrence->start_at && $occurrence->start_at->greaterThanOrEqualTo(now()))->values();

        $fallbackOccurrence = [
            'id' => null,
            'start_at' => optional($this->start_at)->toISOString(),
            'end_at' => optional($this->end_at)->toISOString(),
        ];

        if ($occurrences->isEmpty()) {
            $occurrencePayload = collect([$fallbackOccurrence]);
            $upcomingPayload = collect([$fallbackOccurrence]);
        } else {
            $occurrencePayload = $occurrences->map(fn (EventOccurrence $occurrence) => [
                'id' => $occurrence->id,
                'start_at' => optional($occurrence->start_at)->toISOString(),
                'end_at' => optional($occurrence->end_at)->toISOString(),
            ])->values();
            $upcomingPayload = $upcoming->isNotEmpty()
                ? $upcoming->map(fn (EventOccurrence $occurrence) => [
                    'id' => $occurrence->id,
                    'start_at' => optional($occurrence->start_at)->toISOString(),
                    'end_at' => optional($occurrence->end_at)->toISOString(),
                ])->values()
                : collect([$fallbackOccurrence]);
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'event_type' => $this->event_type ?? null,
            'circle' => $this->circle ? ['id' => $this->circle->id, 'name' => $this->circle->name, 'slug' => $this->circle->slug ?? null] : null,
            'mode' => $this->mode,
            'location_text' => $this->location_text,
            'start_at' => optional($this->start_at)->toISOString(),
            'end_at' => optional($this->end_at)->toISOString(),
            'is_paid' => (bool) $this->is_paid,
            'ticket_price' => (string) ($this->ticket_price ?? '0.00'),
            'visitor_registration_enabled' => $eventService->visitorRegistrationEnabled($this->resource),
            'member_registration_enabled' => $eventService->memberRegistrationEnabled($this->resource),
            'recurrence' => [
                'type' => $this->recurrence_type,
                'interval' => $this->recurrence_interval,
                'day_of_week' => $this->recurrence_day_of_week,
                'week_of_month' => $this->recurrence_week_of_month,
                'day_of_month' => $this->recurrence_day_of_month,
                'month' => $this->recurrence_month,
                'ends_at' => optional($this->recurrence_ends_at)->toISOString(),
            ],
            'occurrences' => $occurrencePayload,
            'upcoming_occurrences' => $upcomingPayload,
            'can_register' => $canRegister['can_register'],
            'eligibility' => [
                'is_eligible' => $isEligible,
                'reason' => $isEligible ? null : 'User is not eligible for this event.',
            ],
        ];
    }
}
