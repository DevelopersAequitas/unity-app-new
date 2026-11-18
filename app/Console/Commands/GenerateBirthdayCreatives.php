<?php

namespace App\Console\Commands;

use App\Models\BirthdayCreativeConfig;
use App\Models\Post;
use App\Models\PostMention;
use App\Models\User;
use App\Services\Media\BirthdayCreativeImageService;
use App\Services\Notifications\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
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
    public function handle(BirthdayCreativeImageService $imageService, NotificationService $notificationService)
    {
        $this->info('Starting Birthday Creative generation process...');

        $config = BirthdayCreativeConfig::first();
        if ($config && ! $config->is_enabled) {
            $this->warn('Birthday Creative feature is currently disabled in configuration.');

            return 0;
        }

        // 1. Auto-deactivate posts from previous days
        $this->info('Deactivating expired birthday posts...');
        $todayStart = Carbon::today('Asia/Kolkata');
        $deactivatedCount = Post::query()
            ->where('post_type', 'birthday')
            ->where('active', true)
            ->where('created_at', '<', $todayStart)
            ->update(['active' => false]);

        $this->info("Deactivated {$deactivatedCount} expired birthday posts.");

        // 2. Identify users whose birthday is today based on timezone
        $testUserId = $this->option('test-user-id');
        if ($testUserId) {
            $this->info("Running in test mode for user ID: {$testUserId}");
            $users = User::query()->whereKey($testUserId)->get();
        } else {
            // Fetch users with DOB set
            $users = User::query()
                ->whereNotNull('dob')
                ->whereNull('deleted_at')
                ->get();
        }

        $this->info("Found {$users->count()} users with birthdays/DOB set.");

        $postsCreated = 0;
        foreach ($users as $user) {
            try {
                if (! $testUserId) {
                    $timezone = $user->timezone ?: 'Asia/Kolkata';
                    try {
                        $localTime = Carbon::now($timezone);
                    } catch (\Throwable $tzError) {
                        $localTime = Carbon::now('Asia/Kolkata');
                    }

                    // Check if today is the user's birthday in their local timezone
                    $dobLocal = Carbon::parse($user->dob);
                    if ($localTime->format('m-d') !== $dobLocal->format('m-d')) {
                        continue;
                    }
                }

                // Check if a birthday post was already created for this user this year
                $alreadyExists = Post::query()
                    ->where(function ($q) use ($user) {
                        $q->where('source_id', $user->id)
                            ->orWhere('user_id', $user->id);
                    })
                    ->where(function ($q) {
                        $q->where('post_type', 'birthday')
                            ->orWhere('source_type', 'birthday');
                    })
                    ->where('created_at', '>=', Carbon::now('Asia/Kolkata')->startOfYear())
                    ->exists();

                if ($alreadyExists && ! $testUserId) {
                    $this->line("Birthday post already exists for user: {$user->display_name} this year. Skipping.");

                    continue;
                }

                $this->info("Generating creative for user: {$user->display_name} ({$user->id})...");

                // Generate image using BirthdayCreativeImageService
                $fileModel = $imageService->generate($user);

                // Retrieve system account (Peers Global Genie) to own the automated post
                $systemUser = User::getSystemUser();
                $authorUserId = $systemUser ? $systemUser->id : $user->id;

                // Calculate turning age
                $dobCarbon = Carbon::parse($user->dob);
                $todayKolkata = Carbon::today('Asia/Kolkata');
                $turningAge = (int) round($dobCarbon->diffInYears($todayKolkata));
                if ($turningAge <= 0 && $dobCarbon->year < $todayKolkata->year) {
                    $turningAge = $todayKolkata->year - $dobCarbon->year;
                }
                $milestoneStr = $turningAge > 0 ? "Celebrating {$turningAge} wonderful years!" : "";

                $displayName = $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
                $contentText = "Wishing {$displayName} a very Happy Birthday! 🎂" . ($milestoneStr ? " {$milestoneStr}" : "");
                $fileUrl = "/api/v1/files/{$fileModel->id}";

                // Create Timeline post
                $post = Post::create([
                    'user_id' => $authorUserId,
                    'content_text' => $contentText,
                    'title' => "🎂 Birthday Celebration: {$displayName}",
                    'description' => "{$displayName} is celebrating their birthday!" . ($milestoneStr ? " ({$turningAge} Years)" : ""),
                    'post_type' => 'birthday',
                    'active' => true,
                    'status' => 'active',
                    'visibility' => 'public',
                    'moderation_status' => 'approved',
                    'source_type' => 'birthday',
                    'source_id' => $user->id,
                    'source_event' => 'birthday',
                    'image' => $fileUrl,
                    'media' => [
                        [
                            'id' => (string) $fileModel->id,
                            'type' => 'image',
                            'url' => $fileUrl,
                        ],
                    ],
                    'tags' => ['#Birthday', '#Celebration', '#PeersGlobal'],
                ]);

                if (Schema::hasTable('post_mentions')) {
                    PostMention::firstOrCreate([
                        'post_id' => $post->id,
                        'peer_id' => $user->id,
                    ]);
                }

                $this->info("Post created successfully for user: {$displayName}. Post ID: {$post->id}");

                // Dispatch in-app notification to member
                try {
                    $notificationService->sendToUser(
                        $user,
                        'birthday_anniversary',
                        "Happy Birthday, {$displayName}! 🎂",
                        "A special birthday celebration creative honour has been published to your community timeline at 7:00 AM IST. Celebrating {$turningAge} wonderful years! Have a wonderful celebration! 🎉",
                        [
                            'screen' => 'timeline',
                            'tap_destination' => 'timeline',
                            'post_id' => (string) $post->id,
                            'user_id' => (string) $user->id,
                            'reference_type' => 'post',
                            'reference_id' => (string) $post->id,
                        ],
                        [
                            'channel' => 'in_app',
                            'reference_type' => 'post',
                            'reference_id' => (string) $post->id,
                            'dedupe_key' => 'birthday:'.$user->id.':'.$todayKolkata->toDateString(),
                            'bypass_daily_limit' => true,
                        ]
                    );
                    $this->info("In-app notification dispatched to user: {$displayName}");
                } catch (\Throwable $notifEx) {
                    Log::warning("Could not send birthday notification for user {$user->id}: ".$notifEx->getMessage());
                }

                $postsCreated++;
            } catch (\Throwable $e) {
                $this->error("Failed to generate birthday creative for user {$user->id}: ".$e->getMessage());
                Log::error('Birthday creative generation error: '.$e->getMessage(), [
                    'user_id' => $user->id,
                    'exception' => $e,
                ]);
            }
        }

        $this->info("Completed. Generated {$postsCreated} birthday wish posts.");

        return 0;
    }
}
