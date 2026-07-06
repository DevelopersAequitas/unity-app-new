<?php

namespace App\Events\Chat;

use App\Models\Chat;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TypingIndicator implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Chat $chat,
        public User $user,
        public bool $isTyping
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('presence-chat.'.$this->chat->id)];
    }

    public function broadcastAs(): string
    {
        return 'chat.typing';
    }

    public function broadcastWith(): array
    {
        return [
            'chat_id' => (string) $this->chat->id,
            'user' => [
                'id' => (string) $this->user->id,
                'display_name' => $this->user->display_name
                    ?? trim(($this->user->first_name ?? '').' '.($this->user->last_name ?? '')),
            ],
            'is_typing' => $this->isTyping,
        ];
    }
}
