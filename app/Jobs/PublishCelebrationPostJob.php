<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CelebrationPublishLog;
use App\Models\CelebrationSchedule;
use App\Services\Celebrations\CelebrationPublishingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublishCelebrationPostJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * Exponential retry backoff in seconds (1 min, 5 min, 15 min).
     *
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public int $timeout = 120;

    public function __construct(
        public readonly string $scheduleId,
        public readonly ?string $customMessage = null,
        public readonly bool $forceManual = false,
    ) {}

    public function handle(CelebrationPublishingService $publishingService): void
    {
        $schedule = CelebrationSchedule::query()->find($this->scheduleId);
        if (! $schedule) {
            Log::warning("[PublishCelebrationPostJob] Schedule {$this->scheduleId} not found; skipping.");

            return;
        }

        if ($schedule->status === CelebrationSchedule::STATUS_PUBLISHED) {
            return;
        }

        if ($schedule->status === CelebrationSchedule::STATUS_CANCELLED) {
            return;
        }

        $publishingService->publishSchedule($schedule, $this->customMessage, $this->forceManual);
    }

    public function failed(Throwable $exception): void
    {
        $schedule = CelebrationSchedule::query()->find($this->scheduleId);
        if (! $schedule) {
            return;
        }

        $schedule->update([
            'status' => CelebrationSchedule::STATUS_FAILED,
            'last_error' => $exception->getMessage(),
        ]);

        try {
            CelebrationPublishLog::create([
                'celebration_schedule_id' => $schedule->id,
                'user_id' => $schedule->user_id,
                'event_type' => $schedule->event_type,
                'event_year' => $schedule->event_year,
                'action' => 'job_permanently_failed',
                'status' => 'failed',
                'attempt_number' => max(1, (int) $schedule->attempts),
                'message' => "Celebration publish job exhausted retries: {$exception->getMessage()}",
                'error_details' => $exception->getMessage(),
                'context' => ['exception' => get_class($exception)],
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Do not fail failed() handler
        }

        Log::error("[PublishCelebrationPostJob] Permanently failed for schedule {$this->scheduleId}: {$exception->getMessage()}");
    }
}
