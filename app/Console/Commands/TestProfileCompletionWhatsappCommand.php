<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendProfileCompletionWhatsappJob;
use App\Models\Notifications\NotificationDeliveryLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestProfileCompletionWhatsappCommand extends Command
{
    protected $signature = 'whatsapp:test-profile-completion {user? : User email or UUID to test}';

    protected $description = 'Dispatch SendProfileCompletionWhatsappJob immediately for local/QA testing';

    public function handle(): int
    {
        $userInput = $this->argument('user');

        if ($userInput) {
            $query = User::withTrashed()->where('email', $userInput);
            if (Str::isUuid((string) $userInput)) {
                $query->orWhere('id', $userInput);
            }
            $user = $query->first();
        } else {
            $user = User::query()->whereNotNull('phone')->latest('created_at')->first();
        }

        if (! $user) {
            $this->error('User not found.');

            return 1;
        }

        $completionPercentage = $user->calculateProfileCompletionPercentage();

        $this->info("Target User: {$user->first_name} {$user->last_name} (Email: {$user->email}, ID: {$user->id}, Phone: {$user->phone}, Status: {$user->status}, Profile Completion: {$completionPercentage}%)");

        $logsBefore = NotificationDeliveryLog::query()
            ->where('user_id', $user->id)
            ->where('channel', 'whatsapp')
            ->where('provider', 'profile_completion_reminder')
            ->count();

        $this->info('Dispatching SendProfileCompletionWhatsappJob synchronously for test execution...');

        SendProfileCompletionWhatsappJob::dispatchSync((string) $user->id);

        $latestLog = NotificationDeliveryLog::query()
            ->where('user_id', $user->id)
            ->where('channel', 'whatsapp')
            ->where('provider', 'profile_completion_reminder')
            ->latest()
            ->first();

        $logsAfter = NotificationDeliveryLog::query()
            ->where('user_id', $user->id)
            ->where('channel', 'whatsapp')
            ->where('provider', 'profile_completion_reminder')
            ->count();

        if ($latestLog && $logsAfter > $logsBefore) {
            if ($latestLog->status === 'sent') {
                $this->info("SUCCESS: WhatsApp message delivered to local mock webhook. Delivery Log ID: {$latestLog->id}");
            } else {
                $this->error("FAILED: Notification delivery status is '{$latestLog->status}'. Error: {$latestLog->error_message}");
            }
        } else {
            $this->warn('SKIPPED: Job executed without creating a new delivery log (User profile >= 70%, opted out, restricted/suspended, missing phone, or deduplicated via alreadySentInLast48Hours).');
        }

        return 0;
    }
}
