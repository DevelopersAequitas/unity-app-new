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
        $answersGrouped = [];

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

                if ($answer->option_id) {
                    if (! isset($answersGrouped[$key])) {
                        $answersGrouped[$key] = [];
                    }
                    $answersGrouped[$key][] = [
                        'option_id' => (string) $answer->option_id,
                        'code' => $answer->option?->code,
                        'label' => $answer->option?->label,
                    ];
                } elseif ($answer->value_text !== null) {
                    $answersGrouped[$key] = $answer->value_text;
                } elseif ($answer->value_number !== null) {
                    $answersGrouped[$key] = (float) $answer->value_number;
                } elseif ($answer->value_boolean !== null) {
                    $answersGrouped[$key] = (bool) $answer->value_boolean;
                } elseif ($answer->value_json !== null) {
                    $answersGrouped[$key] = $answer->value_json;
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
            'answers_by_key' => $answersGrouped,
            'visibility' => [
                'visibility_type' => $this->resource->visibility_type,
                'district' => $this->resource->district?->name,
                'circle' => $this->resource->circle?->name,
            ],
            'publish_to_timeline' => (bool) $this->resource->publish_to_timeline,
            'post_to_timeline' => (bool) $this->resource->publish_to_timeline,
            'post_to_timeline_default' => true,
            'response_count' => (int) ($this->resource->responses_count ?? ($this->resource->relationLoaded('responses') ? $this->resource->responses->count() : 0)),
            'match_count' => (int) ($this->resource->matches_count ?? ($this->resource->relationLoaded('matches') ? $this->resource->matches->count() : 0)),
            'published_at' => $this->resource->published_at?->toISOString(),
            'expires_at' => $this->resource->expires_at?->toISOString(),
            'creator' => $this->resource->user ? new PeerResource($this->resource->user) : null,
        ];
    }
}
