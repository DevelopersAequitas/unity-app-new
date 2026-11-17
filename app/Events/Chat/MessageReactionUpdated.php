<?php

declare(strict_types=1);

namespace App\Events\Chat;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReactionUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<int, array<string, mixed>>  $reactions
     */
    public function __construct(
        public string $messageId,
        public string $action,
        public array $reactions,
        public ?string $chatId = null,
        public ?string $circleId = null,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [];

        if ($this->chatId !== null && $this->chatId !== '') {
            $channels[] = new PrivateChannel('chat.'.$this->chatId);
        }

        if ($this->circleId !== null && $this->circleId !== '') {
            $channels[] = new PresenceChannel('presence-circle-chat.'.$this->circleId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'message:reaction';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'chat_id' => $this->chatId ?? $this->circleId,
            'circle_id' => $this->circleId,
            'message_id' => $this->messageId,
            'action' => $this->action,
            'reactions' => $this->reactions,
        ];
    }
}
