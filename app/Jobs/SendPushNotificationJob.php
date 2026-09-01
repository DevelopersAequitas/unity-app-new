<?php

namespace App\Jobs;

use App\Models\Notifications\AppNotification;
use App\Models\User;
use App\Services\Notifications\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $user,
        public string $title,
        public string $body,
        public array $data = []
    ) {}

    public function handle(FcmService $fcmService): void
    {
        try {
            Log::info('SendPushNotificationJob started', [
                'user_id' => $this->user->id,
            ]);

            if (($this->user->status ?? null) !== 'active') {
                return;
            }

            $notification = null;
            $notificationId = $this->data['notification_id'] ?? null;
            if ($notificationId) {
                $notification = AppNotification::where('id', $notificationId)
                    ->orWhere('data->notification_id', $notificationId)
                    ->first();
            }

            // Centralized send using Notifications\FcmService wrapper
            $result = $fcmService->sendToUser($this->user, $this->title, $this->body, $this->data, $notification);

            Log::info('SendPushNotificationJob completed', [
                'user_id' => $this->user->id,
                'success' => $result['success'] ?? false,
                'error' => $result['error'] ?? null,
            ]);
        } catch (Throwable $throwable) {
            Log::error('SendPushNotificationJob failed', [
                'user_id' => $this->user->id,
                'error' => $throwable->getMessage(),
            ]);
            report($throwable);
        }
    }
}
