<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Circle;
use App\Models\Notifications\NotificationDeliveryLog;
use App\Models\User;
use App\Services\Notifications\WhatsappNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SendCircleRecommendationWhatsappJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $userId,
        public string $circleName
    ) {
        $this->afterCommit = true;
    }

    /**
     * Execute the job to send Circle recommendation message (MSG 8 - Day 3).
     */
    public function handle(WhatsappNotificationService $whatsappService): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            Log::warning('WhatsApp Circle recommendation skipped: User not found.', [
                'user_id' => $this->userId,
                'template_key' => 'circle_calling_day3',
            ]);

            return;
        }

        $rawPhone = $user->phone ?? $user->secondary_mobile;

        if (blank($rawPhone)) {
            Log::warning('WhatsApp Circle recommendation skipped: Missing phone number.', [
                'user_id' => $this->userId,
                'template_key' => 'circle_calling_day3',
            ]);

            return;
        }

        if ($this->alreadySent($this->userId)) {
            Log::info('WhatsApp Circle recommendation skipped: Already sent to user.', [
                'user_id' => $this->userId,
                'template_key' => 'circle_calling_day3',
            ]);

            return;
        }

        $firstName = trim((string) ($user->first_name ?? $user->display_name ?? 'Entrepreneur'));
        if ($firstName === '') {
            $firstName = 'Entrepreneur';
        }

        $payload = [
            'first_name' => $firstName,
            'circle_name' => $this->circleName,
        ];

        try {
            $success = $whatsappService->send('circle_calling_day3', (string) $rawPhone, $payload);

            if ($success) {
                $this->logDelivery($this->userId, (string) $rawPhone, $firstName, 'sent', null);
            } else {
                Log::error('WhatsApp Circle recommendation delivery failed via Resobrand.', [
                    'template_key' => 'circle_calling_day3',
                    'user_id' => $this->userId,
                    'phone' => (string) $rawPhone,
                    'response' => 'Webhook check failed or template inactive',
                    'timestamp' => now()->toIso8601String(),
                ]);

                $this->logDelivery($this->userId, (string) $rawPhone, $firstName, 'failed', 'Webhook check failed or template inactive');
            }
        } catch (Throwable $exception) {
            Log::error('WhatsApp Circle recommendation threw an exception.', [
                'template_key' => 'circle_calling_day3',
                'user_id' => $this->userId,
                'phone' => (string) $rawPhone,
                'exception' => $exception->getMessage(),
                'timestamp' => now()->toIso8601String(),
            ]);

            $this->logDelivery($this->userId, (string) $rawPhone, $firstName, 'failed', $exception->getMessage());
        }
    }

    private function alreadySent(string $userId): bool
    {
        if (! Schema::hasTable('notification_delivery_logs')) {
            return false;
        }

        try {
            return NotificationDeliveryLog::query()
                ->where('user_id', $userId)
                ->where('channel', 'whatsapp')
                ->where('provider', 'circle_calling_day3')
                ->where('status', 'sent')
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function logDelivery(string $userId, string $phone, string $firstName, string $status, ?string $errorMessage): void
    {
        if (! Schema::hasTable('notification_delivery_logs')) {
            return;
        }

        try {
            NotificationDeliveryLog::create([
                'user_id' => $userId,
                'channel' => 'whatsapp',
                'provider' => 'circle_calling_day3',
                'status' => $status,
                'request_payload' => [
                    'template_key' => 'circle_calling_day3',
                    'phone' => $phone,
                    'first_name' => $firstName,
                    'circle_name' => $this->circleName,
                ],
                'error_message' => $errorMessage,
                'attempted_at' => now(),
                'delivered_at' => $status === 'sent' ? now() : null,
            ]);
        } catch (Throwable) {
            // Logging failure should not interrupt job execution
        }
    }
}
