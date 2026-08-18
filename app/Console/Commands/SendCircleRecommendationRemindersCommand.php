<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendCircleRecommendationWhatsappJob;
use App\Models\CircleMember;
use App\Models\JoinedCircleCategory;
use App\Models\Notifications\NotificationDeliveryLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SendCircleRecommendationRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'circle-recommendation:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send automated WhatsApp Circle recommendation reminders every 3 days to users who selected a category.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoff = now()->subDays(3);

        // Find joined/selected category records created at least 3 days ago
        $categorySelections = JoinedCircleCategory::query()
            ->with(['user', 'circle'])
            ->where('created_at', '<=', $cutoff)
            ->get();

        $processedCount = 0;
        $dispatchedCount = 0;

        foreach ($categorySelections as $selection) {
            $user = $selection->user;

            if (! $user) {
                continue;
            }

            $rawPhone = $user->phone ?? $user->secondary_mobile;
            if (blank($rawPhone)) {
                continue;
            }

            $processedCount++;

            // Skip if user is already an approved member of a circle
            $isAlreadyMember = CircleMember::query()
                ->where('user_id', $user->id)
                ->whereIn('status', ['approved', 'active'])
                ->exists();

            if ($isAlreadyMember) {
                continue;
            }

            // Prevent sending if already sent in the last 3 days
            if ($this->sentInLast3Days((string) $user->id)) {
                continue;
            }

            $circleName = (string) ($selection->circle->name ?? 'Recommended Circle');

            SendCircleRecommendationWhatsappJob::dispatch((string) $user->id, $circleName);
            $dispatchedCount++;
        }

        $this->info("Circle recommendation reminders check finished. Processed {$processedCount} selections, dispatched {$dispatchedCount} WhatsApp jobs.");

        Log::info('Circle recommendation reminders scheduled command finished.', [
            'processed_count' => $processedCount,
            'dispatched_count' => $dispatchedCount,
        ]);

        return 0;
    }

    /**
     * Check if the notification was sent to this user in the last 3 days.
     */
    private function sentInLast3Days(string $userId): bool
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
                ->where('attempted_at', '>=', now()->subDays(3))
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
