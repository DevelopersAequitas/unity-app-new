<?php

declare(strict_types=1);

namespace App\Jobs;

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

class SendWelcomeWhatsappJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $userId
    ) {
        $this->afterCommit = true;
    }

    /**
     * Execute the job to send Welcome WhatsApp message.
     */
    public function handle(WhatsappNotificationService $whatsappService): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            Log::warning('WhatsApp welcome notification skipped: User record not found.', [
                'user_id' => $this->userId,
                'template_key' => 'welcome',
            ]);

            return;
        }

        $rawPhone = $user->phone ?? $user->secondary_mobile;

        if (blank($rawPhone)) {
            Log::warning('WhatsApp welcome notification skipped: User phone number is empty.', [
                'user_id' => $this->userId,
                'template_key' => 'welcome',
            ]);

            return;
        }

        // Duplicate protection check
        if ($this->alreadySent($this->userId)) {
            Log::info('WhatsApp welcome notification skipped: Already sent to user.', [
                'user_id' => $this->userId,
                'template_key' => 'welcome',
            ]);

            return;
        }

        $firstName = trim((string) ($user->first_name ?? ''));
        if ($firstName === '') {
            $firstName = trim((string) ($user->display_name ?? ''));
        }
        if ($firstName === '') {
            $firstName = 'Friend';
        }

        $payload = [
            'first_name' => $firstName,
        ];

        try {
            $success = $whatsappService->send('welcome', (string) $rawPhone, $payload);

            if ($success) {
                $this->logDelivery($this->userId, (string) $rawPhone, $firstName, 'sent', null);
            } else {
                Log::error('WhatsApp welcome notification delivery failed.', [
                    'template_key' => 'welcome',
                    'user_id' => $this->userId,
                    'phone' => (string) $rawPhone,
                    'response' => 'Webhook response check failed or template inactive',
                    'timestamp' => now()->toIso8601String(),
                ]);

                $this->logDelivery($this->userId, (string) $rawPhone, $firstName, 'failed', 'Webhook response check failed or template inactive');
            }
        } catch (Throwable $exception) {
            Log::error('WhatsApp welcome notification threw an exception.', [
                'template_key' => 'welcome',
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
                ->where('provider', 'welcome')
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
                'provider' => 'welcome',
                'status' => $status,
                'request_payload' => [
                    'template_key' => 'welcome',
                    'phone' => $phone,
                    'first_name' => $firstName,
                ],
                'error_message' => $errorMessage,
                'attempted_at' => now(),
                'delivered_at' => $status === 'sent' ? now() : null,
            ]);
        } catch (Throwable) {
            // Logging must never crash execution
        }
    }
}
