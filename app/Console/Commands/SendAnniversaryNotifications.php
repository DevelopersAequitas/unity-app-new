<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendAnniversaryNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-anniversary-notifications {--user-id= : Target a specific user ID for manual execution/testing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily check for wedding anniversaries: creates timeline posts and dispatches push notifications.';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService): int
    {
        $logPrefix = '[AnniversaryScheduler]';
        Log::info("{$logPrefix} Run started at ".now()->toIso8601String());
        $this->info('Anniversary notifications scheduler run started.');

        $targetUserId = $this->option('user-id');
        $today = Carbon::now(config('app.timezone', 'UTC'));

        // Query users celebrating their anniversary today
        $query = User::query()
            ->where('status', 'active')
            ->whereNotNull('anniversary_date');

        if ($targetUserId) {
            Log::info("{$logPrefix} Manual trigger for user ID: {$targetUserId}");
            $query->where('id', $targetUserId);
        } else {
            $query->whereMonth('anniversary_date', $today->month)
                ->whereDay('anniversary_date', $today->day);
        }

        $users = $query->get();
        Log::info("{$logPrefix} Found {$users->count()} celebrating user(s).");
        $this->info("Found {$users->count()} celebrating user(s).");

        foreach ($users as $user) {
            Log::info("{$logPrefix} Processing user {$user->id} ({$user->display_name})");

            try {
                // 1. Check for duplicate timeline post today
                $alreadyPosted = Post::query()
                    ->where('source_type', 'anniversary')
                    ->where('source_id', $user->id)
                    ->whereDate('created_at', today(config('app.timezone', 'UTC')))
                    ->exists();

                if ($alreadyPosted) {
                    Log::info("{$logPrefix} Duplicate timeline post already exists today for user {$user->id}. Skipping post creation.");
                } else {
                    $activeTemplate = \App\Models\AnniversaryTemplate::where('is_active', true)->first();

                    // Generate backend creative image
                    $imageGenerator = app(\App\Services\Creative\AnniversaryImageGenerator::class);
                    $fileRecord = $imageGenerator->generate($user, $activeTemplate);
                    $imageUrl = url('/api/v1/files/'.$fileRecord->id);
                    $description = "Happy Wedding Anniversary to our peer {$user->display_name}! Wishing you a lifetime of love and happiness. 🎉🥂";

                    // Retrieve system/admin fallback account to own the automated post
                    $systemUser = User::where('email', 'info@peersglobal.com')->first();
                    if (! $systemUser) {
                        $systemUser = User::create([
                            'id' => (string) \Illuminate\Support\Str::uuid(),
                            'first_name' => 'PeersGlobal',
                            'last_name' => 'Unity',
                            'display_name' => 'PeersGlobal Unity',
                            'email' => 'info@peersglobal.com',
                            'password_hash' => hash('sha256', \Illuminate\Support\Str::random(16)),
                            'status' => 'active',
                        ]);
                    }
                    $authorUserId = $systemUser ? $systemUser->id : $user->id;

                    // Create timeline announcement post with creative image references
                    $post = Post::create([
                        'user_id' => $authorUserId,
                        'circle_id' => null,
                        'content_text' => $description,
                        'media' => [
                            [
                                'id' => $fileRecord->id,
                                'type' => 'image',
                                'url' => $imageUrl,
                            ],
                        ],
                        'tags' => ['anniversary'],
                        'visibility' => 'public',
                        'moderation_status' => 'approved',
                        'sponsored' => false,
                        'is_deleted' => false,
                        'source_type' => 'anniversary',
                        'source_id' => $user->id,
                        'source_event' => 'anniversary',
                        'post_type' => 'anniversary',
                        'template_id' => $activeTemplate?->id,
                        'title' => 'Happy Anniversary! 🎉',
                        'description' => $description,
                        'image' => $imageUrl,
                        'status' => 'active',
                    ]);

                    Log::info("{$logPrefix} Generated creative ID {$fileRecord->id} and created timeline post ID {$post->id} owned by admin/system user {$authorUserId} for user {$user->id}");
                }

                // 2. Dispatch push notification using NotificationService
                $notification = $notificationService->sendToUser(
                    $user,
                    'birthday_anniversary', // notification type
                    'Happy Anniversary! 🎉', // title
                    'Wishing you a very Happy Wedding Anniversary! Have a great day ahead! 🎊', // body
                    [
                        'screen' => 'profile',
                        'tap_destination' => 'profile',
                        'user_id' => (string) $user->id,
                        'reference_type' => 'user',
                        'reference_id' => (string) $user->id,
                    ],
                    [
                        'channel' => 'push',
                        'reference_type' => 'user',
                        'reference_id' => (string) $user->id,
                        'dedupe_key' => 'anniversary:'.$user->id.':'.$today->toDateString(),
                        'bypass_daily_limit' => true,
                    ]
                );

                if ($notification) {
                    Log::info("{$logPrefix} Dispatched push notification ID {$notification->id} to user {$user->id}");
                } else {
                    Log::info("{$logPrefix} Push notification suppressed/deduplicated for user {$user->id}");
                }

            } catch (Throwable $e) {
                Log::error("{$logPrefix} Failed processing user {$user->id}: ".$e->getMessage(), [
                    'exception' => $e,
                ]);
            }
        }

        Log::info("{$logPrefix} Run completed successfully at ".now()->toIso8601String());
        $this->info('Anniversary notifications scheduler run completed.');

        return self::SUCCESS;
    }
}
