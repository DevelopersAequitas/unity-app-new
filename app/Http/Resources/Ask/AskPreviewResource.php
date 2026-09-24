<?php

declare(strict_types=1);

namespace App\Http\Resources\Ask;

use App\Models\Ask\Ask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Ask $resource
 */
class AskPreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $details = [];
        $filters = [];

        if ($this->resource->relationLoaded('answers')) {
            foreach ($this->resource->answers as $answer) {
                $key = (string) $answer->field_key;
                $label = $answer->option?->label ?? $answer->value_text ?? (string) $answer->value_number ?? '';

                if (in_array($key, ['industry', 'geography', 'business_stage', 'timeline'], true)) {
                    if (! isset($filters[$key])) {
                        $filters[$key] = [];
                    }
                    $filters[$key][] = [
                        'id' => $answer->option_id ? (string) $answer->option_id : null,
                        'label' => $answer->option?->label ?? $label,
                        'code' => $answer->option?->code,
                    ];
                } else {
                    if (! isset($details[$key])) {
                        $details[$key] = [];
                    }
                    $details[$key][] = [
                        'id' => $answer->option_id ? (string) $answer->option_id : null,
                        'label' => $answer->option?->label ?? $label,
                        'code' => $answer->option?->code,
                        'value' => $answer->value_text,
                    ];
                }
            }
        }

        return [
            'id' => (string) $this->resource->id,
            'flow' => [
                'id' => (string) $this->resource->flow?->id,
                'code' => $this->resource->flow?->code,
                'name' => $this->resource->flow?->name,
            ],
            'type' => [
                'id' => (string) $this->resource->type?->id,
                'code' => $this->resource->type?->code,
                'name' => $this->resource->type?->name,
            ],
            'title' => $this->resource->title,
            'status' => $this->resource->status,
            'details' => $details,
            'filters' => $filters,
            'visibility' => [
                'visibility_type' => $this->resource->visibility_type,
                'district' => $this->resource->district?->name,
                'circle' => $this->resource->circle?->name,
            ],
            'publish_to_timeline' => (bool) $this->resource->publish_to_timeline,
            'creator' => $this->resource->user ? new PeerResource($this->resource->user) : null,
        ];
    }
}
