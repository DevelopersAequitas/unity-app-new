<?php

declare(strict_types=1);

namespace App\Http\Resources\Ask;

use App\Models\Ask\AskOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property AskOption $resource
 */
class AskOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'option_group_id' => (string) $this->resource->option_group_id,
            'flow_id' => $this->resource->flow_id ? (string) $this->resource->flow_id : null,
            'ask_type_id' => $this->resource->ask_type_id ? (string) $this->resource->ask_type_id : null,
            'parent_option_id' => $this->resource->parent_option_id ? (string) $this->resource->parent_option_id : null,
            'code' => $this->resource->code,
            'label' => $this->resource->label,
            'description' => $this->resource->description,
            'sort_order' => (int) $this->resource->sort_order,
            'is_active' => (bool) $this->resource->is_active,
            'metadata' => $this->resource->metadata ?? (object) [],
        ];
    }
}
