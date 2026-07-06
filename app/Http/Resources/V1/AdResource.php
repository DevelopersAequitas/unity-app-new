<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class AdResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'type' => 'ad',
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'description' => $this->description,
            'image_path' => $this->image_path,
            'image_url' => $this->image_url,
            'redirect_url' => $this->redirect_url,
            'button_text' => $this->button_text,
            'page_name' => $this->page_name,
            'is_active' => (bool) $this->is_active,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'created_at' => $this->created_at,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
