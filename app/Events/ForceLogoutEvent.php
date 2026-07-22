<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ForceLogoutEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  string|null  $newDeviceId  Optional identifier of the newly logged-in device
     */
    public function __construct(
        public string $userId,
        public string $message = 'You have been logged out because your account was accessed on another device.',
        public ?string $newDeviceId = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->userId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'FORCE_LOGOUT';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'code' => 'SESSION_SUPERSEDED',
            'message' => $this->message,
            'new_device_id' => $this->newDeviceId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
