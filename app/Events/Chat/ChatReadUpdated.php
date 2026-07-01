<?php

namespace App\Events\Chat;

use App\Models\Chat;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ChatReadUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Chat $chat,
        public string $readerUserId,
        public Carbon $readAt
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('private-chat.'.$this->chat->id)];
    }

    public function broadcastAs(): string
    {
        return 'chat.read.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'chat_id' => (string) $this->chat->id,
            'reader_user_id' => (string) $this->readerUserId,
            'read_at' => $this->readAt,
        ];
    }
}
