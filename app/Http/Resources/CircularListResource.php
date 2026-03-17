<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CircularListResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (string) $this->id,
            'title' => $this->title,
            'summary' => $this->summary,
            'category' => $this->category,
            'priority' => $this->priority,
            'featured_image_url' => $this->featured_image_url,
            'publish_date' => optional($this->publish_date)?->toISOString(),
            'expiry_date' => optional($this->expiry_date)?->toISOString(),
            'cta_label' => $this->cta_label,
            'cta_url' => $this->cta_url,
            'video_url' => $this->video_url,
            'allow_comments' => (bool) $this->allow_comments,
            'is_pinned' => (bool) $this->is_pinned,
            'audience_type' => $this->audience_type,
            'city' => $this->city ? ['id' => (string) $this->city->id, 'name' => $this->city->name] : null,
            'circle' => $this->circle ? ['id' => (string) $this->circle->id, 'name' => $this->circle->name] : null,
            'read_more_available' => true,
        ];
    }
}
