<?php

namespace App\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberOnlineStatusUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public array $payload)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('presence-member-status'),
            new PresenceChannel('presence-online-members'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'member.online-status.updated';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
