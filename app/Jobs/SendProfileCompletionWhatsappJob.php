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

class SendProfileCompletionWhatsappJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $userId,
        public bool $force = false
    ) {
        $this->afterCommit = true;
        if (config('queue.default') === 'sync') {
            $this->connection = 'database';
        }
    }

    /**
     * Execute the job to send 48-Hour Profile Completion WhatsApp reminder.
     */
    public function handle(WhatsappNotificationService $whatsappService): void
    {
        $user = User::withTrashed()->find($this->userId);

        if (! $user) {
            Log::warning('WhatsApp profile completion reminder skipped: User record not found.', [
                'user_id' => $this->userId,
                'template_key' => 'profile_completion_reminder',
            ]);

            return;
        }

        if (method_exists($user, 'trashed') && $user->trashed()) {
            Log::warning('WhatsApp profile completion reminder skipped: User record has been deleted.', [
                'user_id' => $this->userId,
                'template_key' => 'profile_completion_reminder',
            ]);

            return;
        }

        if (in_array(strtolower((string) $user->status), ['deleted', 'blocked', 'suspended', 'inactive'], true)) {
            Log::warning('WhatsApp profile completion reminder skipped: User status is inactive or restricted.', [
                'user_id' => $this->userId,
                'status' => $user->status,
                'template_key' => 'profile_completion_reminder',
            ]);

            return;
        }

        if (isset($user->whatsapp_opt_in) && ! $user->whatsapp_opt_in) {
            Log::warning('WhatsApp profile completion reminder skipped: User opted out of WhatsApp.', [
                'user_id' => $this->userId,
                'template_key' => 'profile_completion_reminder',
            ]);

            return;
        }

        $rawPhone = $user->phone ?? $user->secondary_mobile;

        if (blank($rawPhone)) {
            Log::warning('WhatsApp profile completion reminder skipped: User phone number is empty.', [
                'user_id' => $this->userId,
                'template_key' => 'profile_completion_reminder',
            ]);

            return;
        }

        // Recalculate latest profile completion percentage immediately before sending
        $completionPercentage = $user->calculateProfileCompletionPercentage();

        // Stop Condition: If profile completion is 70% or higher, skip reminder (bypassed if force flag is true)
        if (! $this->force && $completionPercentage >= 70) {
            Log::info('WhatsApp profile completion reminder skipped: Profile completion is 70% or higher.', [
                'user_id' => $this->userId,
                'completion_percentage' => $completionPercentage,
                'template_key' => 'profile_completion_reminder',
            ]);

            return;
        }

        // Duplicate protection check within the last 48 hours
        if (! $this->force && $this->alreadySentInLast48Hours($this->userId)) {
            Log::info('WhatsApp profile completion reminder skipped: Already sent to user within last 48 hours.', [
                'user_id' => $this->userId,
                'template_key' => 'profile_completion_reminder',
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
            '@mobile' => $normalizedPhone,
            'mobile' => $normalizedPhone,
            'phone' => $normalizedPhone,
            'first_name' => $firstName,
            '@first_name' => $firstName,
            'profile_percent' => (string) $completionPercentage,
            '@profile_percent' => (string) $completionPercentage,
            'profile_completion' => (string) $completionPercentage,
            'profile_completion_percentage' => (string) $completionPercentage,
            '1' => $firstName,
            '2' => (string) $completionPercentage,
        ];

        try {
            $success = $whatsappService->send('profile_completion_reminder', (string) $rawPhone, $payload);

            if ($success) {
                $this->logDelivery($this->userId, (string) $rawPhone, $firstName, $completionPercentage, 'sent', null);
            } else {
                Log::error('WhatsApp profile completion reminder delivery failed.', [
                    'template_key' => 'profile_completion_reminder',
                    'user_id' => $this->userId,
                    'phone' => (string) $rawPhone,
                    'completion_percentage' => $completionPercentage,
                    'response' => 'Webhook response check failed or template inactive',
                    'timestamp' => now()->toIso8601String(),
                ]);

                $this->logDelivery($this->userId, (string) $rawPhone, $firstName, $completionPercentage, 'failed', 'Webhook response check failed or template inactive');
            }
        } catch (Throwable $exception) {
            Log::error('WhatsApp profile completion reminder threw an exception.', [
                'template_key' => 'profile_completion_reminder',
                'user_id' => $this->userId,
                'phone' => (string) $rawPhone,
                'completion_percentage' => $completionPercentage,
                'exception' => $exception->getMessage(),
                'timestamp' => now()->toIso8601String(),
            ]);

            $this->logDelivery($this->userId, (string) $rawPhone, $firstName, $completionPercentage, 'failed', $exception->getMessage());
        }
    }

    private function alreadySentInLast48Hours(string $userId): bool
    {
        if (! Schema::hasTable('notification_delivery_logs')) {
            return false;
        }

        try {
            return NotificationDeliveryLog::query()
                ->where('user_id', $userId)
                ->where('channel', 'whatsapp')
                ->where('provider', 'profile_completion_reminder')
                ->where('status', 'sent')
                ->where('attempted_at', '>=', now()->subHours(48))
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function logDelivery(string $userId, string $phone, string $firstName, int $completionPercentage, string $status, ?string $errorMessage): void
    {
        if (! Schema::hasTable('notification_delivery_logs')) {
            return;
        }

        $normalizedPhone = WhatsappNotificationService::normalizePhone($phone);

        try {
            NotificationDeliveryLog::create([
                'user_id' => $userId,
                'channel' => 'whatsapp',
                'provider' => 'profile_completion_reminder',
                'status' => $status,
                'request_payload' => [
                    'template_key' => 'profile_completion_reminder',
                    'phone' => $normalizedPhone,
                    'mobile' => $normalizedPhone,
                    '@mobile' => $normalizedPhone,
                    'first_name' => $firstName,
                    '@first_name' => $firstName,
                    'profile_percent' => (string) $completionPercentage,
                    '@profile_percent' => (string) $completionPercentage,
                    'profile_completion' => (string) $completionPercentage,
                    'profile_completion_percentage' => (string) $completionPercentage,
                    '1' => $firstName,
                    '2' => (string) $completionPercentage,
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
