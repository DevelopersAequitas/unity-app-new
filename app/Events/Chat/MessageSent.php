<?php

namespace App\Events\Chat;

use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Chat $chat,
        public Message $message,
        public User $sender
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('private-chat.'.$this->chat->id)];
    }

    public function broadcastAs(): string
    {
        return 'chat.message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'chat_id' => (string) $this->chat->id,
            'message' => [
                'id' => (string) $this->message->id,
                'body' => $this->previewBody(),
                'content' => $this->message->content,
                'attachments' => is_array($this->message->attachments) ? $this->message->attachments : [],
                'sender' => [
                    'id' => (string) $this->sender->id,
                    'display_name' => $this->sender->display_name
                        ?? trim(($this->sender->first_name ?? '').' '.($this->sender->last_name ?? '')),
                    'profile_photo_url' => $this->sender->profile_photo_url,
                ],
                'created_at' => $this->message->created_at,
            ],
        ];
    }

    private function previewBody(): string
    {
        $content = is_string($this->message->content) ? trim($this->message->content) : '';
        if ($content !== '') {
            return Str::limit($content, 120, '');
        }

        $attachments = is_array($this->message->attachments) ? $this->message->attachments : [];

        return count($attachments) > 0 ? '📎 Attachment' : '';
    }
}
