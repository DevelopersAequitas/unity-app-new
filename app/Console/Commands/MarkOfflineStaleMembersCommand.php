<?php

namespace App\Console\Commands;

use App\Services\OnlineStatusService;
use Illuminate\Console\Command;

class MarkOfflineStaleMembersCommand extends Command
{
    protected $signature = 'members:mark-offline-stale';

    protected $description = 'Mark users offline when their last heartbeat is stale';

    public function handle(OnlineStatusService $onlineStatusService): int
    {
        $count = $onlineStatusService->markStaleUsersOffline();
        $this->info("Marked {$count} stale members offline.");

        return self::SUCCESS;
    }
}
