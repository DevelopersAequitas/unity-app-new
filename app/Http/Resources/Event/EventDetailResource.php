<?php

namespace App\Http\Resources\Event;

use App\Models\EventRegistration;
use App\Models\User;
use App\Services\Events\EventService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;

class EventDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $metadata = $this->normalizedMetadata($this->metadata);
        $zohoFormUrl = $this->zoho_form_url ?? data_get($metadata, 'zoho_form_url');
        $eventService = app(EventService::class);
        $visitorRegistrationEnabled = $eventService->visitorRegistrationEnabled($this->resource);

        $timezone = $request->header('X-Timezone')
            ?: $request->query('timezone')
            ?: data_get($metadata, 'timezone')
            ?: (config('app.timezone') ?: 'Asia/Kolkata');
        if ($timezone === 'UTC' && ! $request->hasHeader('X-Timezone') && ! $request->has('timezone') && empty(data_get($metadata, 'timezone'))) {
            $timezone = 'Asia/Kolkata';
        }

        $now = Carbon::now($timezone);

        $allOccurrences = $this->relationLoaded('occurrences') ? $this->occurrences : collect();

        $upcomingOccurrences = $allOccurrences->filter(function ($occ) use ($now): bool {
            $end = $occ->end_at ?? $occ->start_at;

            return $end ? Carbon::parse($end)->greaterThanOrEqualTo($now->copy()->startOfDay()) : true;
        })->values();

        $requestedOccurrenceId = $request->query('occurrence_id');
        $activeOccurrence = null;
        if ($requestedOccurrenceId) {
            $activeOccurrence = $allOccurrences->firstWhere('id', $requestedOccurrenceId);
        }
        if (! $activeOccurrence) {
            $activeOccurrence = $upcomingOccurrences->first() ?? $allOccurrences->last();
        }

        $startAtParsed = null;
        $endAtParsed = null;
        $status = $this->status ?? 'scheduled';

        if ($activeOccurrence) {
            $startAtParsed = $activeOccurrence->start_at ? Carbon::parse($activeOccurrence->start_at) : null;
            $endAtParsed = ($activeOccurrence->end_at ?? $activeOccurrence->start_at) ? Carbon::parse($activeOccurrence->end_at ?? $activeOccurrence->start_at) : null;
            $status = $activeOccurrence->status ?? $status;
        } elseif ($this->start_at) {
            $startAtParsed = Carbon::parse($this->start_at);
            $endAtParsed = $this->end_at ? Carbon::parse($this->end_at) : null;
        }

        $startLocal = $startAtParsed;
        $endLocal = $endAtParsed;

        $groupStatus = 'upcoming';
        if ($startLocal && $endLocal && $startLocal->lessThanOrEqualTo($now) && $endLocal->greaterThanOrEqualTo($now)) {
            $groupStatus = 'live';
        } elseif ($startLocal && $startLocal->toDateString() === $now->toDateString()) {
            $groupStatus = 'today';
        } elseif ($endLocal && $endLocal->lessThan($now)) {
            $groupStatus = 'past';
        }

        $circles = [];
        if (Schema::hasTable('event_circles') && $this->relationLoaded('circles')) {
            try {
                $circles = $this->circles->map(fn ($circle) => [
                    'id' => $circle->id,
                    'name' => $circle->name,
                    'slug' => $circle->slug ?? null,
                    'state_name' => $circle->state_name ?? $circle->state ?? $circle->cityRef?->state_name ?? $circle->cityRef?->state ?? null,
                ])->values()->all();
            } catch (\Throwable) {
                $circles = [];
            }
        }
        if ($circles === [] && $this->circle) {
            $circles = [[
                'id' => $this->circle->id,
                'name' => $this->circle->name,
                'slug' => $this->circle->slug ?? null,
                'state_name' => $this->circle->state_name ?? $this->circle->state ?? $this->circle->cityRef?->state_name ?? $this->circle->cityRef?->state ?? null,
            ]];
        }

        $unityUser = $request->user() instanceof User ? $request->user() : null;
        $canRegister = $eventService->canRegister($this->resource, $unityUser);
        $isEligible = $eventService->isEligible($this->resource, $unityUser);

        $registeredCount = (int) ($activeOccurrence?->registered_count ?? $this->registered_count ?? 0);
        $checkedInCount = (int) ($activeOccurrence?->checked_in_count ?? $this->checked_in_count ?? 0);
        $registrationLimit = $activeOccurrence?->registration_limit ?? $this->registration_limit;

        $eventData = [
            'id' => $this->id,
            'occurrence_id' => $activeOccurrence?->id,
            'title' => $this->title,
            'description' => $this->description,
            'event_type' => $this->event_type,
            'event_category' => $this->event_category,
            'state_name' => $this->state_name,
            'mode' => $this->mode,
            'circle_id' => $this->circle_id,
            'circle_ids' => collect($circles)->pluck('id')->values()->all(),
            'circles' => $circles,
            'circle' => $this->circle ? ['id' => $this->circle->id, 'name' => $this->circle->name, 'slug' => $this->circle->slug ?? null, 'state_name' => $this->circle->state_name ?? $this->circle->state ?? $this->circle->cityRef?->state_name ?? $this->circle->cityRef?->state ?? null] : null,
            'start_at' => optional($startAtParsed)->format('Y-m-d\TH:i:s'),
            'start_date' => optional($startLocal)->toDateString(),
            'start_time' => optional($startLocal)->format('H:i:s'),
            'end_at' => optional($endAtParsed)->format('Y-m-d\TH:i:s'),
            'formatted_start_at' => optional($startLocal)->format('d M Y h:i A'),
            'status' => $status,
            'group_status' => $groupStatus,
            'display_date' => optional($startLocal)->format('M d, Y'),
            'display_time' => trim(optional($startLocal)->format('h:i A').' - '.optional($endLocal)->format('h:i A'), ' -'),
            'location_text' => $this->location_text,
            'location' => [
                'text' => $this->location_text,
                'venue_name' => $metadata['venue_name'] ?? null,
                'address_line' => $metadata['address_line'] ?? null,
                'city' => $metadata['city'] ?? null,
                'state' => $metadata['state'] ?? null,
                'google_maps_url' => $metadata['google_maps_url'] ?? null,
            ],
            'online_meeting_url' => $this->online_meeting_url ?? null,
            'agenda' => $this->agenda,
            'speakers' => $this->speakers,
            'banner_url' => $this->banner_url,
            'image_url' => $this->banner_url,
            'what_youll_gain' => array_values((array) data_get($metadata, 'what_youll_gain', [])),
            'organizer' => data_get($metadata, 'organizer'),
            'visibility' => $this->visibility,
            'is_paid' => (bool) $this->is_paid,
            'ticket_price' => $this->ticket_price !== null ? (string) $this->ticket_price : null,
            'registration_limit' => $registrationLimit,
            'registered_count' => $registeredCount,
            'checked_in_count' => $checkedInCount,
            'available_seats' => $registrationLimit ? max(0, $registrationLimit - $registeredCount) : null,
            'qr_checkin_enabled' => (bool) $this->qr_checkin_enabled,
            'is_public' => (bool) $this->is_public,
            'visitor_registration_enabled' => $visitorRegistrationEnabled,
            'zoho_form_url' => $zohoFormUrl,
            'visitor_registration_url' => $visitorRegistrationEnabled ? ($activeOccurrence ? url('/events/'.$this->id.'/occurrences/'.$activeOccurrence->id.'/visitor-register') : $zohoFormUrl) : null,
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
        ];

        return $eventData + [
            'event' => $eventData,
            'can_register' => $canRegister['can_register'],
            'can_register_reason' => $canRegister['reason'],
            'eligibility' => [
                'is_eligible' => $isEligible,
                'reason' => $isEligible ? null : 'User is not eligible for this event.',
            ],
            'occurrences' => EventOccurrenceListResource::collection($allOccurrences),
            'upcoming_occurrences' => EventOccurrenceListResource::collection($upcomingOccurrences),
        ];
    }

    private function normalizedMetadata(mixed $metadata): array
    {
        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);

            return is_array($decoded) ? $decoded : [];
        }

        if (is_object($metadata)) {
            $metadata = (array) $metadata;
        }

        return is_array($metadata) ? $metadata : [];
    }
}

