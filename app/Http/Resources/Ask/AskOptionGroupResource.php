<?php

declare(strict_types=1);

namespace App\Http\Resources\Ask;

use App\Models\Ask\AskOptionGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property AskOptionGroup $resource
 */
class AskOptionGroupResource extends JsonResource
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
            'input_type' => $this->resource->input_type,
            'is_multi_select' => (bool) $this->resource->is_multi_select,
            'is_required' => (bool) $this->resource->is_required,
            'sort_order' => (int) $this->resource->sort_order,
            'is_active' => (bool) $this->resource->is_active,
            'options' => $this->whenLoaded('options', function () {
                return AskOptionResource::collection($this->resource->options);
            }),
            'metadata' => $this->resource->metadata ?? (object) [],
        ];
    }
}
