<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlockedPeerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $blocked = $this->blocked;

        return [
            'id' => (string) ($blocked?->id ?? ''),
            'display_name' => $blocked?->display_name,
            'first_name' => $blocked?->first_name,
            'last_name' => $blocked?->last_name,
            'company_name' => $blocked?->company_name,
            'designation' => $blocked?->designation,
            'profile_photo_url' => $blocked?->profile_photo_url,
            'blocked_at' => optional($this->created_at)?->toISOString(),
            'reason' => $this->reason,
        ];
    }
}
