<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Leader\Services\LeaderNotificationDispatcherService;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LeaderScheduledNotificationsCommand extends Command
{
    protected $signature = 'leader:dispatch-scheduled-notifications {--dry-run : Preview notifications without writing or pushing}';

    protected $description = 'Dispatch idempotent, timezone-aware Leadership App scheduled notifications (tenure expiry & overdue report compliance).';

    public function handle(LeaderNotificationDispatcherService $dispatcher): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $tz = config('app.timezone', 'Asia/Kolkata');
        $now = now($tz);

        $tenureCount = $this->processTenureExpiry($dispatcher, $now, $dryRun);
        $overdueCount = $this->processOverdueReports($dispatcher, $now, $dryRun);

        $this->info(($dryRun ? '[DRY-RUN] ' : '')."Processed {$tenureCount} tenure expiry alert(s) and {$overdueCount} overdue report alert(s).");

        return self::SUCCESS;
    }

    private function processTenureExpiry(LeaderNotificationDispatcherService $dispatcher, CarbonInterface $now, bool $dryRun): int
    {
        if (! Schema::hasTable('finance_leader_tenures') || ! Schema::hasColumn('finance_leader_tenures', 'ends_at')) {
            return 0;
        }

        $count = 0;
        foreach ([30, 7] as $days) {
            $windowEnd = $now->copy()->addDays($days)->endOfDay();
            $windowStart = $now->copy()->addDays($days)->startOfDay();

            $rows = DB::table('finance_leader_tenures')
                ->where('status', 'active')
                ->whereBetween('ends_at', [$windowStart, $windowEnd])
                ->get();

            foreach ($rows as $row) {
                $count++;
                if ($dryRun) {
                    continue;
                }

                $dispatcher->dispatchEvent(
                    'leadership_tenure_expiring',
                    "Leadership Tenure Expiring in {$days} Days",
                    'A leadership tenure assignment is approaching its end date and requires renewal review.',
                    [
                        'circle_id' => (string) ($row->circle_id ?? ''),
                        'entity_type' => 'role_assignment',
                        'entity_id' => (string) ($row->role ?? $row->id ?? ''),
                        'reference_id' => (string) ($row->id ?? ''),
                        'explicit_recipient_ids' => ! empty($row->user_id) ? [(string) $row->user_id] : [],
                        'idempotency_window' => $now->format('Y-m-d')."_tenure_{$days}d",
                    ]
                );
            }
        }

        return $count;
    }

    private function processOverdueReports(LeaderNotificationDispatcherService $dispatcher, CarbonInterface $now, bool $dryRun): int
    {
        if (! Schema::hasTable('circles') || ! Schema::hasTable('leader_reports')) {
            return 0;
        }

        $overdueDays = (int) config('leader.overdue_report_days', 14);
        $cutoff = $now->copy()->subDays($overdueDays);

        $recentCircleIds = DB::table('leader_reports')
            ->where('created_at', '>=', $cutoff)
            ->pluck('circle_id')
            ->filter()
            ->unique()
            ->all();

        $circles = DB::table('circles')
            ->whereNull('deleted_at')
            ->when(! empty($recentCircleIds), fn ($q) => $q->whereNotIn('id', $recentCircleIds))
            ->get(['id', 'name', 'district_id']);

        $count = 0;
        foreach ($circles as $circle) {
            $count++;
            if ($dryRun) {
                continue;
            }

            $dispatcher->dispatchEvent(
                'overdue_report_compliance',
                'Overdue Circle Report Compliance',
                (string) ($circle->name ?? 'Circle')." has no submitted leadership report in the last {$overdueDays} days.",
                [
                    'circle_id' => (string) $circle->id,
                    'district_id' => (string) ($circle->district_id ?? ''),
                    'entity_type' => 'circle',
                    'entity_id' => (string) $circle->id,
                    'reference_id' => (string) $circle->id,
                    'idempotency_window' => $now->format('Y-\WW').'_overdue_report',
                ]
            );
        }

        return $count;
    }
}
