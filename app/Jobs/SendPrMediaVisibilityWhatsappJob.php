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

class SendPrMediaVisibilityWhatsappJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $userId
    ) {
        $this->afterCommit = true;
        if (config('queue.default') === 'sync') {
            $this->connection = 'database';
        }
    }

    /**
     * Execute the job to send PR & Media Visibility WhatsApp message.
     */
    public function handle(WhatsappNotificationService $whatsappService): void
    {
        $user = User::withTrashed()->find($this->userId);

        if (! $user) {
            Log::warning('WhatsApp PR & media visibility notification skipped: User record not found.', [
                'user_id' => $this->userId,
                'template_key' => 'pgu_pr_media_visibility_v2',
            ]);

            return;
        }

        if (method_exists($user, 'trashed') && $user->trashed()) {
            Log::warning('WhatsApp PR & media visibility notification skipped: User record has been deleted.', [
                'user_id' => $this->userId,
                'template_key' => 'pgu_pr_media_visibility_v2',
            ]);

            return;
        }

        if (in_array(strtolower((string) $user->status), ['deleted', 'blocked', 'suspended', 'inactive'], true)) {
            Log::warning('WhatsApp PR & media visibility notification skipped: User status is inactive or restricted.', [
                'user_id' => $this->userId,
                'status' => $user->status,
                'template_key' => 'pgu_pr_media_visibility_v2',
            ]);

            return;
        }

        if (isset($user->whatsapp_opt_in) && ! $user->whatsapp_opt_in) {
            Log::warning('WhatsApp PR & media visibility notification skipped: User opted out of WhatsApp.', [
                'user_id' => $this->userId,
                'template_key' => 'pgu_pr_media_visibility_v2',
            ]);

            return;
        }

        $rawPhone = $user->phone ?? $user->secondary_mobile;

        if (blank($rawPhone)) {
            Log::warning('WhatsApp PR & media visibility notification skipped: User phone number is empty.', [
                'user_id' => $this->userId,
                'template_key' => 'pgu_pr_media_visibility_v2',
            ]);

            return;
        }

        // Duplicate protection check
        if ($this->alreadySent($this->userId)) {
            Log::info('WhatsApp PR & media visibility notification skipped: Already sent to user.', [
                'user_id' => $this->userId,
                'template_key' => 'pgu_pr_media_visibility_v2',
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
            $success = $whatsappService->send('pgu_pr_media_visibility_v2', (string) $rawPhone, $payload);

            if ($success) {
                $this->logDelivery($this->userId, (string) $rawPhone, $firstName, 'sent', null);
            } else {
                Log::error('WhatsApp PR & media visibility notification delivery failed.', [
                    'template_key' => 'pgu_pr_media_visibility_v2',
                    'user_id' => $this->userId,
                    'phone' => (string) $rawPhone,
                    'response' => 'Webhook response check failed or template inactive',
                    'timestamp' => now()->toIso8601String(),
                ]);

                $this->logDelivery($this->userId, (string) $rawPhone, $firstName, 'failed', 'Webhook response check failed or template inactive');
            }
        } catch (Throwable $exception) {
            Log::error('WhatsApp PR & media visibility notification threw an exception.', [
                'template_key' => 'pgu_pr_media_visibility_v2',
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
                ->where('provider', 'pgu_pr_media_visibility_v2')
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

        $normalizedPhone = WhatsappNotificationService::normalizePhone($phone);

        try {
            NotificationDeliveryLog::create([
                'user_id' => $userId,
                'channel' => 'whatsapp',
                'provider' => 'pgu_pr_media_visibility_v2',
                'status' => $status,
                'request_payload' => [
                    'phone' => $normalizedPhone,
                    'mobile' => $normalizedPhone,
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
