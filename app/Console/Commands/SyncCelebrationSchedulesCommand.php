<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Celebrations\CelebrationScheduleSyncService;
use Illuminate\Console\Command;

class SyncCelebrationSchedulesCommand extends Command
{
    protected $signature = 'celebrations:sync-schedules';

    protected $description = 'Synchronize birthday and wedding anniversary schedules for all active peers from the database and cancel schedules for deactivated/deleted peers.';

    public function handle(CelebrationScheduleSyncService $syncService): int
    {
        $this->info('Synchronizing peer birthday & anniversary schedules...');

        $result = $syncService->syncAllActiveUsers(trigger: 'artisan_sync');

        $this->info("Completed sync: {$result['users_checked']} peers checked, {$result['synced']} schedules created/updated, {$result['cancelled']} schedules cancelled.");

        return self::SUCCESS;
    }
}
