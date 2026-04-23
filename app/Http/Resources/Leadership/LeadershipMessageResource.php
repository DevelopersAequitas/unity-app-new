<?php

namespace App\Http\Resources\Leadership;

use Illuminate\Http\Resources\Json\JsonResource;

class LeadershipMessageResource extends JsonResource
{
    public function toArray($request): array
    {
        $authUserId = (string) optional($request->user())->id;
        $reads = $this->whenLoaded('reads');
        $replyTo = $this->relationLoaded('replyTo') ? $this->getRelation('replyTo') : null;
        $file = $this->relationLoaded('file') ? $this->getRelation('file') : null;
        $replyFile = $replyTo && $replyTo->relationLoaded('file') ? $replyTo->getRelation('file') : null;

        return [
            'id' => $this->id,
            'circle_id' => $this->circle_id,
            'message_type' => $this->message_type,
            'message_text' => $this->message_text,
            'file_id' => $this->file_id,
            'file' => $file ? [
                'id' => $file->id,
                'mime_type' => $file->mime_type,
                'size_bytes' => $file->size_bytes,
                'url' => url('/api/v1/files/' . $file->id),
            ] : null,
            'reply_to_message_id' => $this->reply_to_message_id,
            'reply_to_message' => $replyTo ? [
                'id' => $replyTo->id,
                'message_type' => $replyTo->message_type,
                'message_text' => $replyTo->message_text,
                'file_id' => $replyTo->file_id,
                'file' => $replyFile ? [
                    'id' => $replyFile->id,
                    'mime_type' => $replyFile->mime_type,
                    'size_bytes' => $replyFile->size_bytes,
                    'url' => url('/api/v1/files/' . $replyFile->id),
                ] : null,
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
}
