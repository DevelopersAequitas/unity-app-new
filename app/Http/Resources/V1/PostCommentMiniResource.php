<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class PostCommentMiniResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->formatPreviewText($this->content !== null ? (string) $this->content : null),
            'created_at' => $this->created_at,
            'user' => new UserMiniResource($this->whenLoaded('user')),
        ];
    }

    private function formatPreviewText(?string $content): ?string
    {
        if ($content === null) {
            return null;
        }

        $formatted = preg_replace('/@\[([^\]]+)\]\([0-9a-fA-F-]{36}\)/', '$1', $content) ?? $content;
        $formatted = preg_replace('/@\{[0-9a-fA-F-]{36}:([^\}]+)\}/', '$1', $formatted) ?? $formatted;

        return trim($formatted);
    }
}
