<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Notifications\WearTheBadgeWhatsappService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CleanupDuplicateWearTheBadgeJobsCommand extends Command
{
    protected $signature = 'queue:cleanup-duplicate-wear-the-badge {--dry-run : Inspect and report what would be deleted without deleting anything}';

    protected $description = 'Safely clean duplicate pending WearTheBadge jobs from the database queue while preserving Milestone and all other jobs.';

    public function handle(WearTheBadgeWhatsappService $wearTheBadgeService): int
    {
        if (! Schema::hasTable('jobs')) {
            $this->info('Jobs table does not exist.');

            return 0;
        }

        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->info('[DRY RUN MODE] Scanning database queue for duplicate WearTheBadge jobs without deleting...');
        } else {
            $this->info('Scanning database queue for duplicate WearTheBadge jobs...');
        }

        $wearJobs = DB::table('jobs')
            ->where('payload', 'like', '%SendWearTheBadgeWhatsappJob%')
            ->orderBy('id', 'asc')
            ->get(['id', 'payload']);

        $totalWearJobs = $wearJobs->count();
        $this->info("Found {$totalWearJobs} total WearTheBadge jobs in database queue.");

        if ($totalWearJobs === 0) {
            $this->info('No WearTheBadge jobs to clean.');

            return 0;
        }

        $keptUserIds = [];
        $deletedCount = 0;
        $keptCount = 0;

        foreach ($wearJobs as $job) {
            $userId = $this->extractUserIdFromPayload((string) $job->payload);

            if (! $userId) {
                // If user ID cannot be determined, delete safe duplicate
                if (! $isDryRun) {
                    DB::table('jobs')->where('id', $job->id)->delete();
                }
                $deletedCount++;

                continue;
            }

            // If already sent or already kept 1 pending job for this user, delete duplicate
            if (isset($keptUserIds[$userId]) || $wearTheBadgeService->isSent($userId)) {
                if (! $isDryRun) {
                    DB::table('jobs')->where('id', $job->id)->delete();
                }
                $deletedCount++;
            } else {
                $keptUserIds[$userId] = true;
                $keptCount++;
            }
        }

        if ($isDryRun) {
            $this->info("[DRY RUN COMPLETE] Would delete {$deletedCount} duplicate/redundant WearTheBadge jobs. Would keep {$keptCount} pending WearTheBadge jobs.");
        } else {
            $this->info("Queue cleanup complete. Deleted {$deletedCount} redundant WearTheBadge jobs. Kept {$keptCount} pending WearTheBadge jobs.");
        }

        return 0;
    }

    private function extractUserIdFromPayload(string $payload): ?string
    {
        try {
            $data = json_decode($payload, true);
            if (! empty($data['data']['command'])) {
                $command = $data['data']['command'];
                if (is_string($command)) {
                    // Try regex pattern matching serialized userId property
                    if (preg_match('/s:6:"userId";s:\d+:"([^"]+)"/', $command, $matches)) {
                        return $matches[1];
                    }
                    if (preg_match('/userId";s:\d+:"([^"]+)"/', $command, $matches)) {
                        return $matches[1];
                    }

                    // Try unserialize safely
                    try {
                        $unserialized = @unserialize($command);
                        if (is_object($unserialized) && isset($unserialized->userId)) {
                            return (string) $unserialized->userId;
                        }
                    } catch (Throwable) {
                        // Ignore unserialize errors
                    }
                }
            }
        } catch (Throwable) {
            // Ignore payload parse errors
        }

        return null;
    }
}
