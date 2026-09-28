<?php

declare(strict_types=1);

namespace App\Http\Resources\Ask;

use App\Models\Ask\AskStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property AskStatusHistory $resource
 */
class AskStatusHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'ask_id' => (string) $this->resource->ask_id,
            'changed_by_user_id' => (string) $this->resource->changed_by_user_id,
            'changed_by' => $this->resource->relationLoaded('changedBy') && $this->resource->changedBy
                ? new PeerResource($this->resource->changedBy)
                : null,
            'old_status' => $this->resource->old_status,
            'new_status' => $this->resource->new_status,
            'reason' => $this->resource->reason,
            'created_at' => $this->resource->created_at?->toISOString(),
        ];
    }
}
