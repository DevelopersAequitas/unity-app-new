<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatTyping implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $chatId,
        public string $userId,
        public bool $isTyping
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('private-chat.'.$this->chatId);
    }

    public function broadcastAs(): string
    {
        return 'chat.typing';
    }

    public function broadcastWith(): array
    {
        return [
            'chat_id' => $this->chatId,
            'user_id' => $this->userId,
            'is_typing' => $this->isTyping,
        ];
    }
}
