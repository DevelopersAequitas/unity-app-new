<?php

namespace App\Http\Resources\Leadership;

use Illuminate\Http\Resources\Json\JsonResource;

class LeadershipMemberResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'leader_role' => $this->leader_role,
            'title' => $this->title,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'display_name' => $this->user->display_name,
                    'first_name' => $this->user->first_name,
                    'last_name' => $this->user->last_name,
                    'email' => $this->user->email,
                    'phone' => $this->user->phone,
                    'company_name' => $this->user->company_name,
                    'designation' => $this->user->designation,
                    'profile_photo_url' => $this->user->profile_photo_url,
                ];
            }),
        ];
    }
}
