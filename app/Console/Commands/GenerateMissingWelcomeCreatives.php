<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Creative\WearTheBadgeImageGenerator;
use Illuminate\Console\Command;
use Throwable;

class GenerateMissingWelcomeCreatives extends Command
{
    protected $signature = 'users:generate-missing-welcome-creatives
                            {--user= : Process a specific user ID}
                            {--limit=200 : Maximum number of users to process in this run}
                            {--force : Force regenerate images even if URL already exists}';

    protected $description = 'Generate and persist missing welcome creative image URLs for existing users in the database.';

    public function handle(WearTheBadgeImageGenerator $generator): int
    {
        $userId = $this->option('user');
        $force = (bool) $this->option('force');

        if ($userId) {
            $user = User::find($userId);
            if (! $user) {
                $this->error("User with ID {$userId} not found.");

                return self::FAILURE;
            }

            $url = $generator->generateOrGetUrl($user, $force);
            $this->info("Generated welcome creative URL for user {$user->id}: {$url}");

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');

        $query = User::query();

        if (! $force) {
            $query->where(function ($q): void {
                $q->whereNull('welcome_creative_url')
                    ->orWhereNull('profile_card_image_url')
                    ->orWhere('welcome_creative_url', '')
                    ->orWhere('profile_card_image_url', '');
            });
        }

        $users = $query->limit($limit)->get();
        $total = $users->count();

        if ($total === 0) {
            $this->info('No users found missing welcome creative image URLs.');

            return self::SUCCESS;
        }

        $this->info("Found {$total} user(s) to process.");

        $processed = 0;
        $failed = 0;

        foreach ($users as $user) {
            try {
                $url = $generator->generateOrGetUrl($user, $force);
                $processed++;
                $this->line("[{$processed}/{$total}] User {$user->id} ({$user->display_name}): {$url}");
            } catch (Throwable $e) {
                $failed++;
                $this->error("Failed user {$user->id}: {$e->getMessage()}");
            }
        }

        $this->info("Completed processing. Success: {$processed}, Failed: {$failed}.");

        return self::SUCCESS;
    }
}
