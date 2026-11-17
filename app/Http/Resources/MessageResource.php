<?php

namespace App\Http\Resources;

use App\Http\Resources\Ask\PeerResource;
use App\Services\Chat\MessageReactionService;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray($request): ?array
    {
        if (! $this->resource) {
            return null;
        }

        $authUserId = (string) optional($request?->user())->id;

        return [
            'id' => (string) $this->id,
            'chat_id' => (string) $this->chat_id,
            'sender_id' => (string) $this->sender_id,
            'content' => $this->content,
            'attachments' => is_array($this->attachments) ? $this->attachments : [],
            'preview' => $this->messagePreview(),
            'is_read' => (bool) $this->is_read,
            'reactions' => $this->relationLoaded('reactions')
                ? MessageReactionService::formatReactionsCollection($this->reactions, $authUserId)
                : [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'sender' => $this->whenLoaded('sender', function () {
                return $this->sender ? new PeerResource($this->sender) : null;
            }),
        ];
    }

    private function messagePreview(): string
    {
        $content = is_string($this->content) ? trim($this->content) : '';
        if ($content !== '') {
            return $content;
        }

        $attachments = is_array($this->attachments) ? $this->attachments : [];

        return count($attachments) > 0 ? '📎 Attachment' : '';
    }
}
