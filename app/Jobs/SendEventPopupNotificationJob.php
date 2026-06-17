<?php

namespace App\Jobs;

use App\Http\Resources\EventPopupResource;
use App\Models\Event;
use App\Models\User;
use App\Services\Firebase\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendEventPopupNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public string $eventId) {}

    public function handle(FcmService $fcmService): void
    {
        $event = Event::query()->with('circle')->find($this->eventId);
        if (! $event) return;

        $payload = EventPopupResource::payload($event);
        $data = EventPopupResource::fcmData($event);
        $title = $event->popup_title ?: 'New Event Available';
        $body = $event->popup_message ?: trim(($event->title ?? 'Event') . ' ' . ($event->location_text ?? ''));

        User::query()
            ->where('status', 'active')
            ->whereHas('pushTokens')
            ->with('pushTokens')
            ->chunkById(100, function ($users) use ($fcmService, $title, $body, $data, $payload): void {
                foreach ($users as $user) {
                    foreach ($user->pushTokens as $token) {
                        try {
                            $fcmService->sendToDevice((string) $token->token, $title, $body, $data, null, 1, [
                                'user_id' => (string) $user->id,
                                'device_id' => $token->device_id,
                                'platform' => $token->platform,
                                'notification_type' => 'event_popup',
                            ], $payload['image_url'] ?? null);
                        } catch (Throwable $throwable) {
                            report($throwable);
                        }
                    }
                }
            });
    }
}
