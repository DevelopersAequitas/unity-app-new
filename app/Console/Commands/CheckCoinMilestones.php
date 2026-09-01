<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\MilestoneBadgeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckCoinMilestones extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coins:check-milestones';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evaluate user milestone badges across all users';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting milestone badge calculation check...');
        $processedCount = 0;
        $badgeService = app(MilestoneBadgeService::class);

        User::select('id', 'coins_balance', 'life_impacted_count', 'members_introduced_count')
            ->chunkById(200, function ($users) use ($badgeService, &$processedCount): void {
                foreach ($users as $user) {
                    $processedCount++;
                    $badgeService->calculateForUser($user);
                }
            });

        $this->info("Completed! Processed {$processedCount} users.");
        Log::info('coins.check_milestones_completed', [
            'processed_users' => $processedCount,
        ]);

        return self::SUCCESS;
    }
}
