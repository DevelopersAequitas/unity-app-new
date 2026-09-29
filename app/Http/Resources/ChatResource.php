<?php

namespace App\Http\Resources;

use App\Http\Resources\Ask\PeerResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ChatResource extends JsonResource
{
    public function toArray($request): array
    {
        $authUser = $request->user();

        $otherUser = null;
        if ($authUser) {
            if ($authUser->id === $this->user1_id) {
                $otherUser = $this->user2;
            } elseif ($authUser->id === $this->user2_id) {
                $otherUser = $this->user1;
            }
        }

        $lastMessage = $this->whenLoaded('lastMessage', function () {
            return [
                'id' => $this->lastMessage->id,
                'sender_id' => $this->lastMessage->sender_id,
                'content' => $this->lastMessage->content,
                'snippet' => $this->snippetFromMessage($this->lastMessage->content, $this->lastMessage->attachments),
                'attachments' => is_array($this->lastMessage->attachments) ? $this->lastMessage->attachments : [],
                'is_read' => (bool) $this->lastMessage->is_read,
                'created_at' => $this->lastMessage->created_at,
            ];
        });

        $unreadCount = null;
        if (isset($this->unread_count)) {
            $unreadCount = (int) $this->unread_count;
        }

        return [
            'id' => $this->id,
            'user1_id' => $this->user1_id,
            'user2_id' => $this->user2_id,
            'last_message_at' => $this->last_message_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'other_user' => $otherUser ? new PeerResource($otherUser) : null,
            'last_message' => $lastMessage,
            'unread_count' => $unreadCount,
        ];
    }

    private function snippetFromMessage(mixed $content, mixed $attachments): string
    {
        $text = is_string($content) ? trim($content) : '';
        if ($text !== '') {
            return Str::limit($text, 120);
        }

        $attachmentList = is_array($attachments) ? $attachments : [];

        if (count($attachmentList) === 0) {
            return '';
        }

        $firstAttachmentName = $attachmentList[0]['name'] ?? null;

        return is_string($firstAttachmentName) && trim($firstAttachmentName) !== ''
            ? Str::limit(trim($firstAttachmentName), 120)
            : '📎 Attachment';
    }
}
