<?php

namespace App\Console\Commands;

use App\Models\AwardCoinsHistory;
use App\Models\User;
use App\Support\CoinMilestoneResolver;
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
    protected $description = 'Check users who have achieved new coin milestones and record them in the history';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting coin milestone checks...');
        $milestones = CoinMilestoneResolver::getMilestones();
        $processedCount = 0;
        $insertedCount = 0;

        User::select('id', 'coins_balance')->chunkById(200, function ($users) use ($milestones, &$processedCount, &$insertedCount) {
            $userIds = $users->pluck('id')->toArray();

            // Get all existing milestones for these users to prevent duplicates
            $existing = AwardCoinsHistory::whereIn('user_id', $userIds)
                ->get()
                ->groupBy('user_id');

            foreach ($users as $user) {
                $processedCount++;
                $userCoins = (int) ($user->coins_balance ?? 0);
                $userExisting = $existing->get($user->id) ?? collect();

                foreach ($milestones as $milestone) {
                    $threshold = (int) $milestone['threshold'];

                    // If user has reached the milestone threshold
                    if ($userCoins >= $threshold) {
                        // Check if they already have this milestone registered
                        $hasMilestone = $userExisting->contains('coins_earned', $threshold);

                        if (! $hasMilestone) {
                            AwardCoinsHistory::create([
                                'user_id' => $user->id,
                                'coins_earned' => $threshold,
                                'medal_rank' => $milestone['medal_rank'],
                                'title' => $milestone['title'],
                                'meaning' => $milestone['meaning'],
                                'achieved_at' => now(),
                            ]);
                            $insertedCount++;
                        }
                    }
                }
            }
        });

        $this->info("Completed! Processed {$processedCount} users and added {$insertedCount} new milestone records.");
        Log::info('coins.check_milestones_completed', [
            'processed_users' => $processedCount,
            'new_milestones' => $insertedCount,
        ]);

        return self::SUCCESS;
    }
}
