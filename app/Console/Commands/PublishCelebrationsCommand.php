<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\PublishCelebrationPostJob;
use App\Models\CelebrationSchedule;
use App\Services\Celebrations\CelebrationDateResolver;
use App\Services\Celebrations\CelebrationPublishingService;
use App\Services\Celebrations\CelebrationScheduleSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublishCelebrationsCommand extends Command
{
    protected $signature = 'celebrations:publish-due
        {--date= : Override target date in YYYY-MM-DD (Asia/Kolkata)}
        {--type=all : Event type to process: all, birthday, or anniversary}
        {--user-id= : Target a specific peer UUID}
        {--sync : Execute publishing synchronously instead of dispatching queue jobs}
        {--force : Bypass the 7:00 AM IST time gate for manual/test runs}';

    protected $description = 'Detect due birthday & wedding anniversary celebrations at 7:00 AM IST (Asia/Kolkata), generate creatives, publish to Timeline, and notify celebrating members.';

    public function handle(
        CelebrationDateResolver $dateResolver,
        CelebrationScheduleSyncService $syncService,
        CelebrationPublishingService $publishingService
    ): int {
        $nowIst = $dateResolver->nowIst();
        $dateOption = $this->option('date');
        $targetDate = $dateOption
            ? Carbon::parse((string) $dateOption, CelebrationDateResolver::TIMEZONE)->startOfDay()
            : $nowIst->copy()->startOfDay();
        $targetDateStr = $targetDate->format('Y-m-d');
        $type = strtolower(trim((string) ($this->option('type') ?: 'all')));
        $userId = $this->option('user-id');
        $force = (bool) $this->option('force');
        $runSync = (bool) $this->option('sync') || config('queue.default') === 'sync';

        Log::info('[CelebrationsScheduler] Run started', [
            'now_ist' => $nowIst->toIso8601String(),
            'target_date' => $targetDateStr,
            'type' => $type,
            'force' => $force,
            'sync' => $runSync,
        ]);

        // 1. Always sync active peers first so newly registered or updated peers are included automatically
        $syncResult = $syncService->syncAllActiveUsers($targetDate, 'scheduler_pre_publish');
        $this->info("Schedule sync: {$syncResult['users_checked']} peers checked, {$syncResult['synced']} updated, {$syncResult['cancelled']} cancelled.");

        // 2. Enforce 7:00 AM IST gate unless --force is passed
        if (! $force && ! $dateOption) {
            if (! $dateResolver->isDueAt7AmIst($targetDate, $nowIst)) {
                $this->info("Current IST time is {$nowIst->format('H:i')} — waiting until 07:00 AM IST. No posts published.");

                return self::SUCCESS;
            }
        }

        // 3. Query all due pending/retryable schedules for target date
        $query = CelebrationSchedule::query()
            ->whereDate('scheduled_date', $targetDateStr)
            ->whereIn('status', [
                CelebrationSchedule::STATUS_PENDING,
                CelebrationSchedule::STATUS_FAILED,
            ])
            ->whereColumn('attempts', '<', 'max_attempts')
            ->orderBy('created_at');

        if (in_array($type, [CelebrationSchedule::TYPE_BIRTHDAY, CelebrationSchedule::TYPE_ANNIVERSARY], true)) {
            $query->where('event_type', $type);
        }

        if (! empty($userId)) {
            $query->where('user_id', (string) $userId);
        }

        $dueSchedules = $query->get();
        $this->info("Found {$dueSchedules->count()} due celebration schedule(s) for {$targetDateStr}.");

        $publishedCount = 0;
        $queuedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;

        foreach ($dueSchedules as $schedule) {
            try {
                if ($runSync) {
                    $result = $publishingService->publishSchedule($schedule, null, false);
                    if ($result['status'] === 'published') {
                        $publishedCount++;
                        $this->info("Published {$schedule->event_type} post ({$result['post']?->id}) for user {$schedule->user_id}.");
                    } else {
                        $skippedCount++;
                        $this->line("Skipped {$schedule->event_type} for user {$schedule->user_id}: {$result['message']}");
                    }
                } else {
                    PublishCelebrationPostJob::dispatch((string) $schedule->id, null, false);
                    $queuedCount++;
                    $this->info("Queued {$schedule->event_type} publish job for user {$schedule->user_id}.");
                }
            } catch (Throwable $e) {
                $failedCount++;
                $this->error("Failed {$schedule->event_type} for user {$schedule->user_id}: {$e->getMessage()}");
                // Continue processing remaining peers on the same date!
            }
        }

        $this->info("Celebrations run complete: published={$publishedCount}, queued={$queuedCount}, skipped={$skippedCount}, failed={$failedCount}.");

        return self::SUCCESS;
    }
}
