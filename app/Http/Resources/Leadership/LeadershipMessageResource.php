<?php

namespace App\Http\Resources\Leadership;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class LeadershipMessageResource extends JsonResource
{
    public function toArray($request): array
    {
        $authUserId = (string) optional($request->user())->id;
        $reads = $this->whenLoaded('reads');
        $replyTo = $this->relationLoaded('replyTo') ? $this->getRelation('replyTo') : null;

        return [
            'id' => $this->id,
            'circle_id' => $this->circle_id,
            'message_type' => $this->message_type,
            'message_text' => $this->message_text,
            'attachment' => $this->formatAttachment($this),
            'reply_to_message_id' => $this->reply_to_message_id,
            'reply_to_message' => $replyTo ? [
                'id' => $replyTo->id,
                'message_type' => $replyTo->message_type,
                'message_text' => $replyTo->message_text,
                'attachment' => $this->formatAttachment($replyTo),
                'sender' => [
                    'id' => optional($replyTo->sender)->id,
                    'display_name' => optional($replyTo->sender)->display_name,
                    'profile_photo_url' => optional($replyTo->sender)->profile_photo_url,
                ],
            ] : null,
            'is_read' => (string) $this->sender_user_id === $authUserId
                || ($reads !== null && $reads->isNotEmpty()),
            'created_at' => $this->created_at,
            'sender' => [
                'id' => optional($this->sender)->id,
                'display_name' => optional($this->sender)->display_name,
                'profile_photo_url' => optional($this->sender)->profile_photo_url,
            ],
        ];
    }

    private function formatAttachment(object $message): ?array
    {
        $attachment = data_get($message, 'meta.attachment');

        if (! is_array($attachment) || blank($attachment['path'] ?? null)) {
            return null;
        }

        return [
            'id' => $attachment['id'] ?? null,
            'type' => (string) ($attachment['type'] ?? data_get($message, 'message_type', 'file')),
            'url' => $this->resolveStorageUrl((string) $attachment['path']),
            'mime' => $attachment['mime'] ?? null,
            'size' => $attachment['size'] ?? null,
            'name' => $attachment['name'] ?? null,
            'path' => $attachment['path'] ?? null,
        ];
    }

    private function resolveStorageUrl(string $path): string
    {
        $disk = config('filesystems.default', 'public');

        return Storage::disk($disk)->url($path);
    }
}
