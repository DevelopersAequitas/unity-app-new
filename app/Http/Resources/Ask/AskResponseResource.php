<?php

declare(strict_types=1);

namespace App\Http\Resources\Ask;

use App\Models\Ask\AskResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property AskResponse $resource
 */
class AskResponseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'ask_id' => (string) $this->resource->ask_id,
            'response_type' => $this->resource->response_type,
            'message' => $this->resource->message,
            'status' => $this->resource->status,
            'responder_user_id' => (string) $this->resource->responder_user_id,
            'responder' => $this->resource->relationLoaded('responder') && $this->resource->responder
                ? new PeerResource($this->resource->responder)
                : null,
            'introduced_user_id' => $this->resource->introduced_user_id ? (string) $this->resource->introduced_user_id : null,
            'introduced_peer' => $this->resource->relationLoaded('introducedUser') && $this->resource->introducedUser
                ? new PeerResource($this->resource->introducedUser)
                : null,
            'contact' => $this->resource->relationLoaded('contact') && $this->resource->contact
                ? new AskResponseContactResource($this->resource->contact)
                : null,
            'responded_at' => $this->resource->responded_at?->toISOString(),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
