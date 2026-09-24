<?php

declare(strict_types=1);

namespace App\Http\Resources\Ask;

use App\Models\Ask\AskType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property AskType $resource
 */
class AskTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'flow_id' => (string) $this->resource->flow_id,
            'parent_id' => $this->resource->parent_id ? (string) $this->resource->parent_id : null,
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'level' => (int) $this->resource->level,
            'sort_order' => (int) $this->resource->sort_order,
            'is_active' => (bool) $this->resource->is_active,
            'children' => $this->whenLoaded('children', function () {
                return AskTypeResource::collection($this->resource->children);
            }),
            'metadata' => $this->resource->metadata ?? (object) [],
        ];
    }
}
