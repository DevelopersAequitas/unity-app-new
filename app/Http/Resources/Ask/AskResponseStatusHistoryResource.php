<?php

declare(strict_types=1);

namespace App\Http\Resources\Ask;

use App\Models\Ask\AskResponseStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property AskResponseStatusHistory $resource
 */
class AskResponseStatusHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'response_id' => (string) $this->resource->response_id,
            'changed_by_user_id' => (string) $this->resource->changed_by_user_id,
            'changed_by' => $this->resource->relationLoaded('changedBy') && $this->resource->changedBy
                ? new PeerResource($this->resource->changedBy)
                : null,
            'old_status' => $this->resource->old_status,
            'new_status' => $this->resource->new_status,
            'note' => $this->resource->note,
            'created_at' => $this->resource->created_at?->toISOString(),
        ];
    }
}
