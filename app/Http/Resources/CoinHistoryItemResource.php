<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CoinHistoryItemResource extends JsonResource
{
    public function toArray($request)
    {
        $relatedUser = $this->resource['related_user'] ?? null;

        return [
            'id' => $this->resource['id'] ?? null,
            'coins_delta' => (int) ($this->resource['coins_delta'] ?? 0),
            'reason_label' => $this->resource['reason_label'] ?? null,
            'activity_type' => $this->resource['activity_type'] ?? null,
            'activity_id' => $this->resource['activity_id'] ?? null,
            'activity_title' => $this->resource['activity_title'] ?? null,
            'related_user' => $relatedUser ? (new UserMiniResource($relatedUser))->toArray($request) : null,
            'created_at' => $this->resource['created_at'] ?? null,
        ];
    }
}
