<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\V1\LimitedUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FollowResource extends JsonResource
{
    /**
     * @param  Request|null  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'requested_at' => optional($this->requested_at)?->toIso8601String(),
            'accepted_at' => optional($this->accepted_at)?->toIso8601String(),
            'follower' => $this->whenLoaded('follower', fn () => new LimitedUserResource($this->follower)),
            'following' => $this->whenLoaded('following', fn () => new LimitedUserResource($this->following)),
        ];
    }
}

