<?php

namespace App\Console\Commands;

use App\Services\LifeImpact\LifeImpactService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncLifeImpactTotals extends Command
{
    protected $signature = 'life-impact:sync-totals {--user_id=}';

    protected $description = 'Recalculate and synchronize users.life_impacted_count from life_impact_histories.';

    public function handle(LifeImpactService $lifeImpactService): int
    {
        $singleUserId = $this->option('user_id');

        $query = DB::table('users')->select('id');

        if (is_string($singleUserId) && trim($singleUserId) !== '') {
            $query->where('id', trim($singleUserId));
        } else {
            $query->where(function ($inner) {
                $inner->where('life_impacted_count', '>', 0)
                    ->orWhereExists(function ($history) {
                        $history->selectRaw('1')
                            ->from('life_impact_histories')
                            ->whereColumn('life_impact_histories.user_id', 'users.id');
                    });
            });
        }

        $userIds = $query->pluck('id');

        if ($userIds->isEmpty()) {
            $this->info('No users found to sync.');

            return self::SUCCESS;
        }

        $updated = 0;

        foreach ($userIds as $userId) {
            $lifeImpactService->recalculateUserLifeImpact((string) $userId);
            $updated++;
        }

        $this->info("Life impact totals synchronized for {$updated} user(s).");

        return self::SUCCESS;
    }
}
