<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CelebrationSchedule;
use App\Services\Celebrations\CelebrationDateResolver;
use App\Services\Celebrations\CelebrationPublishingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class RefreshCelebrationCreativesCommand extends Command
{
    protected $signature = 'celebrations:refresh-creatives
        {--date=today : Target scheduled date in YYYY-MM-DD (Asia/Kolkata), "today", or "all"}
        {--type=birthday : Event type: all, birthday, or anniversary}
        {--user-id= : Target a specific peer UUID}';

    protected $description = 'Regenerate and update creative images in-place on already-published celebration Timeline posts (zero duplicates).';

    public function handle(
        CelebrationDateResolver $dateResolver,
        CelebrationPublishingService $publishingService
    ): int {
        $dateOpt = strtolower(trim((string) ($this->option('date') ?: 'today')));
        $type = strtolower(trim((string) ($this->option('type') ?: 'birthday')));
        $userId = $this->option('user-id');

        $query = CelebrationSchedule::query()
            ->where('status', CelebrationSchedule::STATUS_PUBLISHED)
            ->whereNotNull('post_id')
            ->orderByDesc('published_at');

        if ($dateOpt === 'today') {
            $todayStr = $dateResolver->todayIst()->format('Y-m-d');
            $query->whereDate('scheduled_date', $todayStr);
        } elseif ($dateOpt !== 'all') {
            $targetStr = Carbon::parse($dateOpt, CelebrationDateResolver::TIMEZONE)->format('Y-m-d');
            $query->whereDate('scheduled_date', $targetStr);
        }

        if (in_array($type, [CelebrationSchedule::TYPE_BIRTHDAY, CelebrationSchedule::TYPE_ANNIVERSARY], true)) {
            $query->where('event_type', $type);
        }

        if (! empty($userId)) {
            $query->where('user_id', (string) $userId);
        }

        $schedules = $query->get();
        $this->info("Found {$schedules->count()} published celebration schedule(s) to refresh.");

        $updated = 0;
        $failed = 0;
        foreach ($schedules as $schedule) {
            try {
                $res = $publishingService->refreshPublishedCreative($schedule);
                $updated++;
                $this->info("Refreshed {$schedule->event_type} post {$res['post']?->id} for user {$schedule->user_id} -> {$res['schedule']?->creative_url}");
            } catch (Throwable $e) {
                $failed++;
                $this->error("Failed refreshing schedule {$schedule->id}: {$e->getMessage()}");
            }
        }

        $this->info("Refresh complete: updated={$updated}, failed={$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
