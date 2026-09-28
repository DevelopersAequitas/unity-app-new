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
        $introducedPeer = null;
        if ($this->resource->introducedUser) {
            $peer = $this->resource->introducedUser;
            $introducedPeer = [
                'id' => (string) $peer->id,
                'display_name' => (string) ($peer->display_name ?: trim(($peer->first_name ?? '').' '.($peer->last_name ?? ''))),
                'company_name' => (string) ($peer->company_name ?? ''),
                'profile_photo_url' => (string) ($peer->profile_photo_file_id ? url('/api/v1/files/'.$peer->profile_photo_file_id) : ($peer->profile_photo_url ?? '')),
            ];
        }

        return [
            'id' => (string) $this->resource->id,
            'response_id' => (string) $this->resource->id,
            'ask_id' => (string) $this->resource->ask_id,
            'responder_id' => (string) $this->resource->responder_user_id,
            'responder_user_id' => (string) $this->resource->responder_user_id,
            'response_type' => $this->resource->response_type,
            'message' => $this->resource->message,
            'timeline' => (string) ($this->resource->timeline ?? 'immediate'),
            'status' => $this->resource->status,
            'responder' => $this->resource->relationLoaded('responder') && $this->resource->responder
                ? new PeerResource($this->resource->responder)
                : null,
            'introduced_user_id' => $this->resource->introduced_user_id ? (string) $this->resource->introduced_user_id : null,
            'introduced_peer' => $introducedPeer,
            'contact' => $this->resource->relationLoaded('contact') && $this->resource->contact
                ? new AskResponseContactResource($this->resource->contact)
                : null,
            'responded_at' => $this->resource->responded_at?->toISOString(),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
