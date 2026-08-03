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

class SendFounderEngagementJob implements ShouldQueue
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
     * Execute the job to send 3-Hour Founder Engagement WhatsApp message.
     */
    public function handle(WhatsappNotificationService $whatsappService): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            Log::warning('WhatsApp founder engagement notification skipped: User record not found.', [
                'user_id' => $this->userId,
                'template_key' => 'engagement_founder',
            ]);

            return;
        }

        if (method_exists($user, 'trashed') && $user->trashed()) {
            Log::warning('WhatsApp founder engagement notification skipped: User record has been deleted.', [
                'user_id' => $this->userId,
                'template_key' => 'engagement_founder',
            ]);

            return;
        }

        if (in_array(strtolower((string) $user->status), ['deleted', 'blocked', 'suspended'], true)) {
            Log::warning('WhatsApp founder engagement notification skipped: User status is inactive or restricted.', [
                'user_id' => $this->userId,
                'status' => $user->status,
                'template_key' => 'engagement_founder',
            ]);

            return;
        }

        $rawPhone = $user->phone ?? $user->secondary_mobile;

        if (blank($rawPhone)) {
            Log::warning('WhatsApp founder engagement notification skipped: User phone number is empty.', [
                'user_id' => $this->userId,
                'template_key' => 'engagement_founder',
            ]);

            return;
        }

        // Duplicate protection check
        if ($this->alreadySent($this->userId)) {
            Log::info('WhatsApp founder engagement notification skipped: Already sent to user.', [
                'user_id' => $this->userId,
                'template_key' => 'engagement_founder',
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

        $normalizedPhone = WhatsappNotificationService::normalizePhone((string) $rawPhone);

        $payload = [
            'mobile' => $normalizedPhone,
            'first_name' => $firstName,
            'media_url' => 'https://peersunity.com/api/v1/files/019fc673-313b-7135-96dd-ca70a094f2ad',
        ];

        try {
            $success = $whatsappService->send('engagement_founder', (string) $rawPhone, $payload);

            if ($success) {
                $this->logDelivery($this->userId, (string) $rawPhone, $firstName, 'sent', null);
            } else {
                Log::error('WhatsApp founder engagement notification delivery failed.', [
                    'template_key' => 'engagement_founder',
                    'user_id' => $this->userId,
                    'phone' => (string) $rawPhone,
                    'response' => 'Webhook response check failed or template inactive',
                    'timestamp' => now()->toIso8601String(),
                ]);

                $this->logDelivery($this->userId, (string) $rawPhone, $firstName, 'failed', 'Webhook response check failed or template inactive');
            }
        } catch (Throwable $exception) {
            Log::error('WhatsApp founder engagement notification threw an exception.', [
                'template_key' => 'engagement_founder',
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
                ->where('provider', 'engagement_founder')
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
                'provider' => 'engagement_founder',
                'status' => $status,
                'request_payload' => [
                    'template_key' => 'engagement_founder',
                    'phone' => $phone,
                    'mobile' => WhatsappNotificationService::normalizePhone($phone),
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
