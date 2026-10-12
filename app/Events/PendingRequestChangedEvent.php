<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PendingRequestChangedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $action,         // 'created' | 'approved' | 'rejected'
        public string $category,       // 'circle_joining_requests', 'coin_claims', etc.
        public ?string $requestId = null,
        public ?array $itemData = null,
        public ?array $summaryBreakdown = null
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('admin-pending-stream')];
    }

    public function broadcastAs(): string
    {
        return 'pending.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'category' => $this->category,
            'requestId' => $this->requestId,
            'itemData' => $this->itemData,
            'summaryBreakdown' => $this->summaryBreakdown,
        ];
    }
}
