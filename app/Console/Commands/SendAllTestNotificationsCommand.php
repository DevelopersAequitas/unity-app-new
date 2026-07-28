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
                'type' => 'admin_test',
                'category' => 'admin_test',
                'title' => 'System Test Notification',
                'body' => 'This is a general system test notification for backend testing.',
                'screen' => 'home',
                'priority' => 'high',
            ],
            [
                'type' => 'peer_suggestion',
                'category' => 'engagement',
                'title' => 'New Peer Recommendation',
                'body' => 'Connect with new active peers in your network today!',
                'screen' => 'peer_profile',
                'priority' => 'medium',
            ],
            [
                'type' => 'circle_highlight',
                'category' => 'engagement',
                'title' => 'Trending Circle Highlight',
                'body' => 'Check out popular discussions in the Global Leaders circle.',
                'screen' => 'circle_detail',
                'priority' => 'medium',
            ],
            [
                'type' => 'leaderboard_teaser',
                'category' => 'engagement',
                'title' => 'Weekly Leaderboard Update',
                'body' => 'You are close to climbing the top member leaderboard this week!',
                'screen' => 'leaderboard',
                'priority' => 'low',
            ],
            [
                'type' => 'coins_reminder',
                'category' => 'coins',
                'title' => 'Unused Coins Balance Alert',
                'body' => 'You have unused Unity Coins in your wallet. Claim your rewards!',
                'screen' => 'wallet',
                'priority' => 'medium',
            ],
            [
                'type' => 'follow_request',
                'category' => 'network',
                'title' => 'New Connection Request',
                'body' => 'A peer sent you a connection request. Click to view profile.',
                'screen' => 'connection_requests',
                'priority' => 'high',
            ],
            [
                'type' => 'coin_claim_review',
                'category' => 'coins',
                'title' => 'Coin Claim Approved',
                'body' => 'Your coin claim request for 50 coins has been approved.',
                'screen' => 'coin_claims',
                'priority' => 'high',
            ],
            [
                'type' => 'membership_expiry',
                'category' => 'membership',
                'title' => 'Membership Expiry Reminder',
                'body' => 'Your membership is expiring soon. Renew now to retain full benefits.',
                'screen' => 'membership_plans',
                'priority' => 'high',
            ],
            [
                'type' => 'circle_expiry',
                'category' => 'membership',
                'title' => 'Circle Membership Expiring',
                'body' => 'Your circle membership renewal is due in 3 days.',
                'screen' => 'my_circles',
                'priority' => 'high',
            ],
            [
                'type' => 'brand_offer',
                'category' => 'promotional',
                'title' => 'Exclusive Brand Partner Offer',
                'body' => 'Enjoy an exclusive 20% discount on software tools for members.',
                'screen' => 'brand_partners',
                'priority' => 'medium',
            ],
            [
                'type' => 'event_announcement',
                'category' => 'events',
                'title' => 'Upcoming Networking Event',
                'body' => 'Join the upcoming Peers Global Monthly Networking Summit.',
                'screen' => 'event_detail',
                'priority' => 'medium',
            ],
            [
                'type' => 'new_post',
                'category' => 'feed',
                'title' => 'New Requirement Posted',
                'body' => 'A new collaboration requirement has been published in your feed.',
                'screen' => 'post_detail',
                'priority' => 'medium',
            ],
        ];

        $sentCount = 0;
        $pushDeliveredCount = 0;

        foreach ($notificationSamples as $index => $sample) {
            $dedupeKey = 'test_all:'.$user->id.':'.$sample['type'].':'.Str::random(6);

            $notification = AppNotification::create([
                'user_id' => $user->id,
                'type' => $sample['type'],
                'category' => $sample['category'],
                'title' => $sample['title'],
                'body' => $sample['body'],
                'channel' => 'push',
                'priority' => $sample['priority'],
                'screen' => $sample['screen'],
                'data' => [
                    'screen' => $sample['screen'],
                    'tap_destination' => $sample['screen'],
                    'type' => $sample['type'],
                    'test_index' => $index + 1,
                ],
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
