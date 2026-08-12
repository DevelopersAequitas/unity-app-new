<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendProfileCompletionWhatsappJob;
use App\Models\Notifications\NotificationDeliveryLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SendProfileCompletionRemindersCommand extends Command
{
    protected $signature = 'profile-completion:send-reminders';

    protected $description = 'Send automated 48-hour recurring profile completion WhatsApp reminders to users with completion < 70%';

    public function handle(): int
    {
        $cutoff = now()->subHours(48);

        $users = User::query()
            ->where(function ($query): void {
                $query->whereNotNull('phone')
                    ->orWhereNotNull('secondary_mobile');
            })
            ->where('created_at', '<=', $cutoff)
            ->whereNotIn('status', ['deleted', 'blocked', 'suspended', 'inactive'])
            ->get();

        $processedCount = 0;
        $dispatchedCount = 0;

        foreach ($users as $user) {
            $processedCount++;

            if ($user->calculateProfileCompletionPercentage() >= 70) {
                continue;
            }

            if ($this->sentInLast48Hours((string) $user->id)) {
                continue;
            }

            SendProfileCompletionWhatsappJob::dispatch((string) $user->id);
            $dispatchedCount++;
        }

        $this->info("Profile completion reminders check completed. Processed {$processedCount} candidate users, dispatched {$dispatchedCount} WhatsApp reminder jobs.");

        Log::info('Profile completion reminders scheduled command finished.', [
            'processed_count' => $processedCount,
            'dispatched_count' => $dispatchedCount,
        ]);

        return 0;
    }

    private function sentInLast48Hours(string $userId): bool
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
}
