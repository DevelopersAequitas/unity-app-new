<?php

namespace App\Http\Resources\Leadership;

use Illuminate\Http\Resources\Json\JsonResource;

class LeadershipMemberResource extends JsonResource
{
    public function toArray($request): array
    {
        $member = $this->resource;
        $user = data_get($member, 'user');

        return [
            'id' => data_get($member, 'id'),
            'leader_role' => data_get($member, 'leader_role'),
            'title' => data_get($member, 'title'),
            'user' => $user ? [
                'id' => data_get($user, 'id'),
                'display_name' => data_get($user, 'display_name'),
                'first_name' => data_get($user, 'first_name'),
                'last_name' => data_get($user, 'last_name'),
                'email' => data_get($user, 'email'),
                'phone' => data_get($user, 'phone'),
                'company_name' => data_get($user, 'company_name'),
                'designation' => data_get($user, 'designation'),
                'profile_photo_url' => data_get($user, 'profile_photo_url'),
            ] : null,
        ];
    }
}
