<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Notifications\NotificationDeliveryLog;
use App\Models\User;
use App\Models\WhatsappMessageDeliveryLog;
use App\Models\WhatsAppMessageLog;
use App\Services\Creative\WelcomeCreativeService;
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

    /**
     * The number of times the job may be attempted.
     * Set to 1 so registration Welcome notification is never automatically re-queued on failure.
     */
    public int $tries = 1;

    public function __construct(
        public string $userId
    ) {
        $this->afterCommit = true;
    }

    /**
     * Execute the job to send Welcome WhatsApp message.
     */
    public function handle(WhatsappNotificationService $whatsappService, WelcomeCreativeService $welcomeService): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            Log::warning('WhatsApp welcome notification skipped: User record not found.', [
                'user_id' => $this->userId,
                'template_key' => 'welcome',
            ]);

            return;
        }

        // Always ensure the welcome creative image and timeline post exist (idempotent)
        $creativeUrl = $user->welcome_creative_url ?? null;
        try {
            $creative = $welcomeService->generateAndPostWelcomeCreative($user);
            $creativeUrl = $creative?->creative_url ?? $user->refresh()->welcome_creative_url ?? $creativeUrl;
        } catch (Throwable $e) {
            Log::warning('Failed generating welcome creative image in SendWelcomeWhatsappJob: '.$e->getMessage(), [
                'user_id' => $this->userId,
            ]);
        }

        $rawPhone = $user->phone ?? $user->secondary_mobile;

        if (blank($rawPhone)) {
            Log::warning('WhatsApp welcome notification skipped: User phone number is empty.', [
                'user_id' => $this->userId,
                'template_key' => 'welcome',
            ]);

            return;
        }

        // Duplicate & retry protection check: Registration-time only notification.
        // If ANY attempt (sent or failed) already exists, never attempt or send again.
        if ($this->alreadyAttempted($this->userId, (string) $rawPhone)) {
            Log::info('WhatsApp welcome notification skipped: Already attempted for user.', [
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

        $payload = array_filter([
            'user_id' => $this->userId,
            'first_name' => $firstName,
            'welcome_creative_url' => $creativeUrl,
            'header_image_url' => $creativeUrl,
            'media_url' => $creativeUrl,
        ]);

        try {
            $success = $whatsappService->send('welcome', (string) $rawPhone, $payload, $this->userId);

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

    private function alreadyAttempted(string $userId, ?string $rawPhone = null): bool
    {
        if (Schema::hasTable('notification_delivery_logs')) {
            try {
                if (NotificationDeliveryLog::query()
                    ->where('user_id', $userId)
                    ->where('channel', 'whatsapp')
                    ->whereIn('provider', ['welcome', 'wear_the_badge'])
                    ->exists()) {
                    return true;
                }
            } catch (Throwable) {
            }
        }

        $normalizedPhone = $rawPhone !== null ? WhatsappNotificationService::normalizePhone($rawPhone) : '';

        if (Schema::hasTable('whatsapp_message_delivery_logs')) {
            try {
                $query = WhatsappMessageDeliveryLog::query()
                    ->where('template_key', 'welcome')
                    ->where(function ($q) use ($userId, $normalizedPhone): void {
                        $q->where('user_id', $userId);
                        if ($normalizedPhone !== '') {
                            $q->orWhere('phone', $normalizedPhone);
                        }
                    });

                if ($query->exists()) {
                    return true;
                }
            } catch (Throwable) {
            }
        }

        if (Schema::hasTable('whatsapp_message_logs')) {
            try {
                $query = WhatsAppMessageLog::query()
                    ->where('template_key', 'welcome')
                    ->where(function ($q) use ($userId, $normalizedPhone): void {
                        $q->where('user_id', $userId);
                        if ($normalizedPhone !== '') {
                            $q->orWhere('recipient_phone', $normalizedPhone);
                        }
                    });

                if ($query->exists()) {
                    return true;
                }
            } catch (Throwable) {
            }
        }

        return false;
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
