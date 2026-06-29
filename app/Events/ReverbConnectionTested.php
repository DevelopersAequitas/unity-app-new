<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReverbConnectionTested implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly string $message = 'Reverb test event')
    {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('reverb-test');
    }

    public function broadcastAs(): string
    {
        return 'reverb.connection.tested';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'sent_at' => now()->toIso8601String(),
        ];
    }
}
