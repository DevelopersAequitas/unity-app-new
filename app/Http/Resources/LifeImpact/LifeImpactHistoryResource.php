<?php

namespace App\Http\Resources\LifeImpact;

use Illuminate\Http\Resources\Json\JsonResource;

class LifeImpactHistoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (string) $this->id,
            'activity_type' => (string) $this->activity_type,
            'activity_id' => $this->activity_id ? (string) $this->activity_id : null,
            'impact_value' => (int) $this->impact_value,
            'title' => (string) $this->title,
            'description' => $this->description,
            'triggered_by_user' => $this->whenLoaded('triggeredByUser', function () {
                return [
                    'id' => (string) $this->triggeredByUser->id,
                    'first_name' => $this->triggeredByUser->first_name,
                    'last_name' => $this->triggeredByUser->last_name,
                    'display_name' => $this->triggeredByUser->display_name,
                ];
            }),
            'created_at' => $this->created_at,
        ];
    }
}
