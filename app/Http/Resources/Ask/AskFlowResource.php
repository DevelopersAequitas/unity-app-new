<?php

declare(strict_types=1);

namespace App\Http\Resources\Ask;

use App\Models\Ask\AskFlow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property AskFlow $resource
 */
class AskFlowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'icon' => $this->resource->icon,
            'sort_order' => $this->resource->sort_order,
            'is_active' => (bool) $this->resource->is_active,
            'metadata' => $this->resource->metadata ?? (object) [],
        ];
    }
}
