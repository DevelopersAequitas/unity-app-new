<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Notifications\AppNotification;
use App\Models\User;
use App\Services\Notifications\FcmService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SendAllTestNotificationsCommand extends Command
{
    protected $signature = 'notification:send-all {user : User UUID or email address}';

    protected $description = 'Send all types of sample notifications to a specific user for testing';

    public function handle(FcmService $fcmService): int
    {
        $userInput = $this->argument('user');

        $user = User::where('id', $userInput)
            ->orWhere('email', $userInput)
            ->first();

        if (! $user) {
            $this->error("User not found with email or UUID: {$userInput}");

            return 1;
        }

        $this->info("Sending all sample notification types to user: {$user->email} (ID: {$user->id})");

        $tokens = $fcmService->activeTokensForUser($user->id);
        $tokenCount = $tokens->count();
        $this->info("User has {$tokenCount} active FCM push token(s).");

        $notificationSamples = [
            [
                'type' => 'new_post',
                'category' => 'feed',
                'title' => 'New Post Published',
                'body' => 'John Doe published a new post.',
                'screen' => '/member-profile',
                'priority' => 'high',
                'data' => [
                    'navigation_screen' => '/member-profile',
                    'type' => 'new_post',
                    'user_id' => (string) $user->id,
                    'post_id' => 'post_123456',
                ],
            ],
            [
                'type' => 'requirement',
                'category' => 'business',
                'title' => 'New Requirement Posted',
                'body' => 'A new business requirement has been posted: Need UI/UX Designer.',
                'screen' => '/post-details',
                'priority' => 'high',
                'data' => [
                    'navigation_screen' => '/post-details',
                    'activity_type' => 'requirement',
                    'type' => 'requirement',
                    'post_id' => 'req_post_987654',
                    'requirement_id' => 'req_555777',
                ],
            ],
            [
                'type' => 'brand_partner_offer',
                'category' => 'promotional',
                'title' => 'Exclusive Brand Offer!',
                'body' => 'Get 20% off with our new Brand Partner offer.',
                'screen' => '/brand-partner-details',
                'priority' => 'medium',
                'data' => [
                    'navigation_screen' => '/brand-partner-details',
                    'type' => 'brand_partner_offer',
                    'partner_id' => 'bp_443322',
                ],
            ],
            [
                'type' => 'membership_expiry',
                'category' => 'membership',
                'title' => 'Membership Expiring Soon',
                'body' => 'Your membership will expire in 3 days. Tap to review.',
                'screen' => '/profile',
                'priority' => 'high',
                'data' => [
                    'navigation_screen' => '/profile',
                    'type' => 'membership_expiry',
                    'activity_type' => 'membership_expiry',
                    'user_id' => (string) $user->id,
                ],
            ],
            [
                'type' => 'trending_circle',
                'category' => 'engagement',
                'title' => 'Trending Circle Highlight',
                'body' => 'Design Thinkers Circle is trending! Tap to view and join.',
                'screen' => '/join-circle',
                'priority' => 'medium',
                'data' => [
                    'navigation_screen' => '/join-circle',
                    'type' => 'trending_circle',
                    'circle_id' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
                ],
            ],
        ];

        $sentCount = 0;
        $pushDeliveredCount = 0;

        foreach ($notificationSamples as $index => $sample) {
            $dedupeKey = 'test_all:'.$user->id.':'.$sample['type'].':'.Str::random(6);
            $extraData = array_merge([
                'screen' => $sample['screen'],
                'tap_destination' => $sample['screen'],
                'type' => $sample['type'],
                'test_index' => $index + 1,
            ], $sample['data'] ?? []);

            $notification = AppNotification::create([
                'user_id' => $user->id,
                'type' => $sample['type'],
                'category' => $sample['category'],
                'title' => $sample['title'],
                'body' => $sample['body'],
                'channel' => 'push',
                'priority' => $sample['priority'],
                'screen' => $sample['screen'],
                'data' => $extraData,
                'dedupe_key' => $dedupeKey,
                'status' => 'pending',
            ]);

            $sentCount++;

            // Attempt FCM push if user has tokens registered
            foreach ($tokens as $token) {
                $res = $fcmService->sendToToken(
                    $token,
                    $sample['title'],
                    $sample['body'],
                    $notification->dataPayload(),
                    $notification
                );
                if ($res['success'] ?? false) {
                    $pushDeliveredCount++;
                }
            }

            $notification->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $this->line('  ['.($index + 1).'/'.count($notificationSamples)."] Created '{$sample['title']}' ({$sample['type']})");
        }

        $this->info("Successfully sent {$sentCount} notifications to {$user->email}.");
        if ($tokenCount > 0) {
            $this->info("Pushed {$pushDeliveredCount} notification message(s) via FCM.");
        } else {
            $this->warn('Note: FCM push skipped because user has no active push tokens registered.');
        }

        return 0;
    }
}
