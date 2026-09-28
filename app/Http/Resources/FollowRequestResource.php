<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\V1\LimitedUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FollowRequestResource extends JsonResource
{
    /**
     * @param  Request|null  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'follow_id' => $this->id,
            'status' => $this->status,
            'requested_at' => optional($this->requested_at)?->toIso8601String(),
            'from_user' => $this->whenLoaded('follower', fn () => new LimitedUserResource($this->follower)),
        ];
    }
}

