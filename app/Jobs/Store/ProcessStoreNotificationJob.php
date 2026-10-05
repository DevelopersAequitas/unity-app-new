<?php

namespace App\Jobs\Store;

use App\Models\Store\NotificationEvent;
use App\Models\Store\NotificationLog;
use App\Models\User;
use App\Services\Notifications\FcmService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessStoreNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $eventId;

    public function __construct(string $eventId)
    {
        $this->eventId = $eventId;
    }

    public function handle(): void
    {
        $event = NotificationEvent::find($this->eventId);
        if (! $event || $event->status === 'SENT') {
            return;
        }

        $event->update(['status' => 'PROCESSING']);

        try {
            $user = User::find($event->user_id);
            if (! $user) {
                $event->update(['status' => 'FAILED']);

                return;
            }

            // Create notification log
            NotificationLog::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'channel' => 'PUSH',
                'status' => 'SENT',
                'sent_at' => now(),
                'delivered_at' => now(),
                'created_at' => now(),
            ]);

            $event->update([
                'status' => 'SENT',
                'processed_at' => now(),
            ]);
        } catch (Exception $e) {
            Log::error('ProcessStoreNotificationJob failed', ['error' => $e->getMessage()]);
            $event->update(['status' => 'FAILED']);
        }
    }
}
