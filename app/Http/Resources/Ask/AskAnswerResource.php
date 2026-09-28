<?php

declare(strict_types=1);

namespace App\Http\Resources\Ask;

use App\Models\Ask\AskAnswer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property AskAnswer $resource
 */
class AskAnswerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'ask_id' => (string) $this->resource->ask_id,
            'option_group_id' => $this->resource->option_group_id ? (string) $this->resource->option_group_id : null,
            'option_id' => $this->resource->option_id ? (string) $this->resource->option_id : null,
            'field_key' => $this->resource->field_key,
            'value_text' => $this->resource->value_text,
            'value_number' => $this->resource->value_number !== null ? (float) $this->resource->value_number : null,
            'value_boolean' => $this->resource->value_boolean !== null ? (bool) $this->resource->value_boolean : null,
            'value_json' => $this->resource->value_json,
            'option' => $this->whenLoaded('option', function () {
                return new AskOptionResource($this->resource->option);
            }),
            'option_group' => $this->whenLoaded('optionGroup', function () {
                return new AskOptionGroupResource($this->resource->optionGroup);
            }),
            'sort_order' => (int) $this->resource->sort_order,
        ];
    }
}
