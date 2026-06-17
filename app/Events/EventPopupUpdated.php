<?php

namespace App\Events;

use App\Http\Resources\EventPopupResource;
use App\Models\Event;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventPopupUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Event $event)
    {
        $this->event->loadMissing('circle');
    }

    public function broadcastOn(): Channel
    {
        return new Channel('events.popups');
    }

    public function broadcastAs(): string
    {
        return 'event.popup.updated';
    }

    public function broadcastWith(): array
    {
        return EventPopupResource::payload($this->event);
    }
}
