<?php

declare(strict_types=1);

namespace App\Http\Resources\Ask;

use App\Models\Ask\AskMatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property AskMatch $resource
 */
class AskMatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'match_id' => (string) $this->resource->id,
            'ask_id' => (string) $this->resource->ask_id,
            'match_status' => $this->resource->match_status,
            'peer' => $this->resource->matchedUser ? new PeerResource($this->resource->matchedUser) : null,
            'matches' => $this->resource->matched_dimensions ?? (object) [],
            'match_score' => $this->resource->match_score !== null ? (float) $this->resource->match_score : null,
            'match_reason' => $this->resource->match_reason,
            'algorithm_version' => $this->resource->algorithm_version,
            'viewed_at' => $this->resource->viewed_at?->toISOString(),
            'created_at' => $this->resource->created_at?->toISOString(),
        ];
    }
}
