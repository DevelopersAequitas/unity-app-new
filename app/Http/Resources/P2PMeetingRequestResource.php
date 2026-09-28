<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ActivityHistory\OtherUserDetailsResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class P2PMeetingRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['requester', 'invitee', 'rescheduleRequests']);

        $resolver = app(OtherUserDetailsResolver::class);
        $requesterProfile = $this->requester ? array_merge($this->requester->publicProfileArray(), $resolver->formatUser($this->requester) ?? []) : null;
        $inviteeProfile = $this->invitee ? array_merge($this->invitee->publicProfileArray(), $resolver->formatUser($this->invitee) ?? []) : null;

        $isLogged = (bool) $this->is_logged;
        $rawStatus = strtolower((string) $this->status);
        $canLogMeeting = ! $isLogged && in_array($rawStatus, ['accepted', 'scheduled'], true);

        return [
            'id' => (string) $this->id,
            'status' => (string) $this->status,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'place' => (string) $this->place,
            'message' => $this->message,
            'responded_at' => $this->responded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'is_logged' => $isLogged,
            'logged_at' => $this->logged_at?->toIso8601String(),
            'logged_by_user_id' => $this->logged_by_user_id ? (string) $this->logged_by_user_id : null,
            'p2p_meeting_id' => $this->p2p_meeting_id ? (string) $this->p2p_meeting_id : null,
            'can_log_meeting' => $canLogMeeting,
            'requester' => $requesterProfile,
            'invitee' => $inviteeProfile,
            'given_by' => $requesterProfile,
            'given_to' => $inviteeProfile,
            'reschedule_requests' => $this->rescheduleRequests,
        ];
    }
}
