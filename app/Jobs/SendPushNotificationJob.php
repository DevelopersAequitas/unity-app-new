<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\PushNotificationService;
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
    ) {
    }

    public function handle(PushNotificationService $pushNotificationService): void
    {
        try {
            Log::info('SendPushNotificationJob started', [
                'user_id' => $this->user->id,
            ]);

            if (($this->user->status ?? null) !== 'active') {
                return;
            }

            $pushNotificationService->sendNow($this->user, $this->title, $this->body, $this->data, false);
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }

}
