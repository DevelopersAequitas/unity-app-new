<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Ask\PeerResource;
use App\Models\EventRegistration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventRegistration
 */
class ReferralEventVisitorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->user;
        $event = $this->event;
        $occurrence = $this->occurrence;

        $name = (string) ($user?->display_name ?: ($this->visitor_name ?: trim((string) (($user?->first_name ?? '').' '.($user?->last_name ?? '')))));
        $email = (string) ($user?->email ?: ($this->visitor_email ?? ''));
        $phone = (string) ($user?->phone ?: ($this->visitor_phone ?? ''));
        $company = (string) ($user?->company_name ?: ($this->visitor_company ?? ''));
        $designation = (string) ($user?->designation ?: ($this->visitor_designation ?? ''));
        $city = (string) ($user?->city_of_residence ?: (is_string($user?->city) ? $user?->city : ($this->visitor_city ?? '')));

        return [
            'id' => (string) $this->id,
            'registration_id' => (string) $this->id,
            'attendee_type' => $this->user_id ? 'member' : 'visitor',
            'name' => $name !== '' ? $name : 'Visitor',
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
            'business_name' => $company !== '' ? $company : null,
            'company_name' => $company !== '' ? $company : null,
            'designation' => $designation !== '' ? $designation : null,
            'position' => $designation !== '' ? $designation : null,
            'city' => $city !== '' ? $city : null,
            'status' => (string) ($this->status ?? 'registered'),
            'checkin_status' => (string) ($this->checkin_status ?? 'pending'),
            'registered_at' => optional($this->registered_at ?? $this->created_at)->toISOString(),
            'event' => $event ? [
                'id' => (string) $event->id,
                'title' => (string) $event->title,
                'event_type' => (string) ($event->event_type ?? ''),
                'mode' => (string) ($event->mode ?? ''),
                'circle_name' => (string) ($event->circle?->name ?? ''),
                'start_at' => optional($occurrence?->start_at)->toISOString(),
                'end_at' => optional($occurrence?->end_at)->toISOString(),
            ] : null,
            'peer' => $user ? new PeerResource($user) : null,
        ];
    }
}
