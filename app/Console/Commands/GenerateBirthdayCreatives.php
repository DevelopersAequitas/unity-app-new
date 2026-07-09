<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Post;
use App\Models\BirthdayCreativeConfig;
use App\Services\Media\BirthdayCreativeImageService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Log;

class GenerateBirthdayCreatives extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'birthday:generate-creatives {--test-user-id= : Target a specific user ID for testing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Identify users whose birthday is today, generate a birthday creative, post to timeline, and deactivate old posts.';

    /**
     * Execute the console command.
     */
    public function handle(BirthdayCreativeImageService $imageService)
    {
        $this->info('Starting Birthday Creative generation process...');

        $config = BirthdayCreativeConfig::first();
        if ($config && !$config->is_enabled) {
            $this->warn('Birthday Creative feature is currently disabled in configuration.');
            return 0;
        }

        // 1. Auto-deactivate posts from previous days
        $this->info('Deactivating expired birthday posts...');
        $todayStart = Carbon::today();
        $deactivatedCount = Post::query()
            ->where('post_type', 'birthday')
            ->where('active', true)
            ->where('created_at', '<', $todayStart)
            ->update(['active' => false]);

        $this->info("Deactivated {$deactivatedCount} expired birthday posts.");

        // 2. Identify users whose birthday is today
        $testUserId = $this->option('test-user-id');
        if ($testUserId) {
            $this->info("Running in test mode for user ID: {$testUserId}");
            $users = User::query()->whereKey($testUserId)->get();
        } else {
            // Find users where DOB month and day match today
            $todayMmDd = Carbon::now()->format('m-d'); // 'MM-DD'
            $users = User::query()
                ->whereNotNull('dob')
                ->whereRaw("to_char(dob, 'MM-DD') = ?", [$todayMmDd])
                ->whereNull('deleted_at')
                ->get();
        }

        $this->info("Found {$users->count()} users with birthdays today.");

        $postsCreated = 0;
        foreach ($users as $user) {
            try {
                // Check if a birthday post was already created for this user today
                $alreadyExists = Post::query()
                    ->where('user_id', $user->id)
                    ->where('post_type', 'birthday')
                    ->whereDate('created_at', Carbon::today())
                    ->exists();

                if ($alreadyExists && !$testUserId) {
                    $this->line("Birthday post already exists for user: {$user->display_name} today. Skipping.");
                    continue;
                }

                $this->info("Generating creative for user: {$user->display_name} ({$user->id})...");

                // Generate image using Intervention Image
                $fileModel = $imageService->generate($user);

                // Create Timeline post
                $displayName = $user->display_name ?: ($user->first_name . ' ' . $user->last_name);
                $post = Post::create([
                    'user_id' => $user->id,
                    'content_text' => "Wishing {$displayName} a very Happy Birthday! 🎂",
                    'post_type' => 'birthday',
                    'active' => true,
                    'visibility' => 'public',
                    'moderation_status' => 'approved',
                    'media' => [
                        [
                            'id' => $fileModel->id,
                            'type' => 'image',
                            'url' => url("/api/v1/files/{$fileModel->id}"),
                        ]
                    ],
                ]);

                $this->info("Post created successfully for user: {$user->display_name}. Post ID: {$post->id}");
                $postsCreated++;
            } catch (\Throwable $e) {
                $this->error("Failed to generate birthday creative for user {$user->id}: " . $e->getMessage());
                Log::error("Birthday creative generation error: " . $e->getMessage(), [
                    'user_id' => $user->id,
                    'exception' => $e
                ]);
            }
        }

        $this->info("Completed. Generated {$postsCreated} birthday wish posts.");
        return 0;
    }
}
