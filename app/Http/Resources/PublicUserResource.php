<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PublicUserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'public_profile_slug' => $this->public_profile_slug,
            'display_name' => $this->display_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'designation' => $this->designation,
            'company_name' => $this->company_name,
            'profile_photo_url' => $this->profile_photo_url,
            'short_bio' => $this->short_bio,
            'life_impacted_count' => (int) ($this->life_impacted_count ?? 0),
            'city' => new CityResource($this->whenLoaded('city')),
        ];
    }
}
