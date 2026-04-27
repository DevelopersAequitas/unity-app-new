<?php

namespace App\Console\Commands;

use App\Services\LifeImpact\LifeImpactBackfillService;
use Illuminate\Console\Command;

class LifeImpactRecalculateUsersCommand extends Command
{
    protected $signature = 'life-impact:recalculate-users';

    protected $description = 'Recalculate users.life_impacted_count from life_impact_histories.';

    public function handle(LifeImpactBackfillService $backfillService): int
    {
        $this->info('Starting life impact users recalculation...');

        $updatedUsers = $backfillService->recalculateUsersWithHistory(true);

        $this->newLine();
        $this->info('Life impact users recalculation completed.');
        $this->line('Users updated from history totals: ' . $updatedUsers);

        return self::SUCCESS;
    }
}
