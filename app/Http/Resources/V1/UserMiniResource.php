<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class UserMiniResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'profile_photo_url' => $this->profile_photo_url,
            'life_impacted_count' => (int) ($this->life_impacted_count ?? 0),
        ];
    }
}
