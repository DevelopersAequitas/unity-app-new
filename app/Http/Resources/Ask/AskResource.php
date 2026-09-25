<?php

declare(strict_types=1);

namespace App\Http\Resources\Ask;

use App\Models\Ask\Ask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Ask $resource
 */
class AskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $answersGrouped = [];
        if ($this->resource->relationLoaded('answers')) {
            foreach ($this->resource->answers as $answer) {
                $key = (string) $answer->field_key;
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
            'user_id' => (string) $this->resource->user_id,
            'title' => $this->resource->title,
            'status' => $this->resource->status,
            'flow' => $this->resource->relationLoaded('flow') && $this->resource->flow
                ? new AskFlowResource($this->resource->flow)
                : null,
            'type' => $this->resource->relationLoaded('type') && $this->resource->type
                ? new AskTypeResource($this->resource->type)
                : null,
            'visibility' => [
                'visibility_type' => $this->resource->visibility_type,
                'district_id' => $this->resource->visibility_district_id ? (string) $this->resource->visibility_district_id : null,
                'circle_id' => $this->resource->visibility_circle_id ? (string) $this->resource->visibility_circle_id : null,
            ],
            'publish_to_timeline' => (bool) $this->resource->publish_to_timeline,
            'post_to_timeline' => (bool) $this->resource->publish_to_timeline,
            'published_at' => $this->resource->published_at?->toISOString(),
            'expires_at' => $this->resource->expires_at?->toISOString(),
            'closed_at' => $this->resource->closed_at?->toISOString(),
            'answers' => $this->whenLoaded('answers', function () {
                return AskAnswerResource::collection($this->resource->answers);
            }),
            'answers_by_key' => $answersGrouped,
            'match_count' => (int) ($this->resource->matches_count ?? ($this->resource->relationLoaded('matches') ? $this->resource->matches->count() : 0)),
            'response_count' => (int) ($this->resource->responses_count ?? ($this->resource->relationLoaded('responses') ? $this->resource->responses->count() : 0)),
            'user' => $this->resource->relationLoaded('user') && $this->resource->user
                ? new PeerResource($this->resource->user)
                : null,
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
