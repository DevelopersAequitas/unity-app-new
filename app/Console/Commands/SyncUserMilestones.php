<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Users\UserMilestoneSyncService;
use Illuminate\Console\Command;

class SyncUserMilestones extends Command
{
    protected $signature = 'users:sync-milestones';

    protected $description = 'Sync contribution milestone fields for all users';

    public function handle(UserMilestoneSyncService $service): int
    {
        User::query()
            ->select(['id', 'members_introduced_count'])
            ->chunkById(200, function ($users) use ($service) {
                foreach ($users as $user) {
                    $fullUser = User::find($user->id);

                    if ($fullUser) {
                        $service->sync($fullUser);
                    }
                }
            });

        $this->info('User milestone sync completed.');

        return self::SUCCESS;
    }
}
