<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EventRsvpResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'checked_in' => (bool) $this->checked_in,
            'checkin_at' => $this->checkin_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'display_name' => $this->user->display_name,
                    'first_name' => $this->user->first_name,
                    'last_name' => $this->user->last_name,
                    'profile_photo_url' => $this->user->profile_photo_url,
                    'is_online' => (bool) ($this->user->is_online ?? false),
                ];
            }),
        ];
    }
}
