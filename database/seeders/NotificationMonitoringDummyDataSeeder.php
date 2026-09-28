<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class NotificationMonitoringDummyDataSeeder extends Seeder
{
    /**
     * Run the database seeds for testing the Notification Monitoring Dashboard.
     */
    public function run(): void
    {
        if (! Schema::hasTable('app_notifications')) {
            $this->command?->error('Table app_notifications does not exist.');

            return;
        }

        // Get first few available users or fallback
        $users = User::take(10)->get();
        $primaryUserId = $users->first()?->id ?? 1;

        $dummyNotifications = [
            [
                'title' => 'New Business Deal Received',
                'body' => 'Rajesh Patel shared a business deal opportunity for Solar Installation worth ₹5,00,000.',
                'type' => 'business_deal',
                'category' => 'activity',
                'channel' => 'push',
                'priority' => 'high',
                'status' => 'sent',
                'screen' => '/deals',
                'reference_type' => 'business_deal',
                'created_at' => Carbon::now()->subMinutes(12),
                'sent_at' => Carbon::now()->subMinutes(11),
                'read_at' => Carbon::now()->subMinutes(5),
                'clicked_at' => Carbon::now()->subMinutes(4),
                'failed_at' => null,
                'failure_reason' => null,
                'logs' => [
                    [
                        'channel' => 'push',
                        'provider' => 'fcm',
                        'status' => 'sent',
                        'request_payload' => ['title' => 'New Business Deal Received', 'device_count' => 1],
                        'response_payload' => ['multicast_id' => 891273981273, 'success' => 1, 'failure' => 0],
                        'error_message' => null,
                    ],
                ],
            ],
            [
                'title' => 'Requirement Matches Your Profile',
                'body' => 'New Industrial Requirement: "Looking for ERP & Cloud Migration Consultant for Textile Industry in Ahmedabad".',
                'type' => 'requirement',
                'category' => 'activity',
                'channel' => 'push',
                'priority' => 'normal',
                'status' => 'sent',
                'screen' => '/post-details',
                'reference_type' => 'requirement',
                'created_at' => Carbon::now()->subMinutes(35),
                'sent_at' => Carbon::now()->subMinutes(34),
                'read_at' => null,
                'clicked_at' => null,
                'failed_at' => null,
                'failure_reason' => null,
                'logs' => [
                    [
                        'channel' => 'push',
                        'provider' => 'fcm',
                        'status' => 'sent',
                        'request_payload' => ['title' => 'Requirement Matches Your Profile', 'device_count' => 1],
                        'response_payload' => ['multicast_id' => 129837198273, 'success' => 1, 'failure' => 0],
                        'error_message' => null,
                    ],
                ],
            ],
            [
                'title' => 'P2P Meeting Scheduled',
                'body' => 'Your 1-to-1 P2P networking meeting with Amit Shah has been confirmed for tomorrow at 4:00 PM.',
                'type' => 'p2p_meeting',
                'category' => 'activity',
                'channel' => 'all',
                'priority' => 'high',
                'status' => 'partial',
                'screen' => '/p2p-meetings',
                'reference_type' => 'p2p_meeting',
                'created_at' => Carbon::now()->subHours(1)->subMinutes(15),
                'sent_at' => Carbon::now()->subHours(1)->subMinutes(14),
                'read_at' => null,
                'clicked_at' => null,
                'failed_at' => Carbon::now()->subHours(1)->subMinutes(14),
                'failure_reason' => 'Push sent successfully; SMTP Email delivery failed due to mailbox quota exceeded.',
                'logs' => [
                    [
                        'channel' => 'push',
                        'provider' => 'fcm',
                        'status' => 'sent',
                        'request_payload' => ['title' => 'P2P Meeting Scheduled'],
                        'response_payload' => ['success' => 1],
                        'error_message' => null,
                    ],
                    [
                        'channel' => 'email',
                        'provider' => 'smtp',
                        'status' => 'failed',
                        'request_payload' => ['to' => 'user@example.com', 'subject' => 'P2P Meeting Scheduled'],
                        'response_payload' => ['code' => 552, 'message' => '5.2.2 Mailbox full'],
                        'error_message' => '552 5.2.2 Mailbox quota exceeded for recipient.',
                    ],
                ],
            ],
            [
                'title' => 'Membership Expiry Alert',
                'body' => 'Your annual Peers Global membership expires in 7 days. Renew today to maintain uninterrupted benefits.',
                'type' => 'membership_expiry',
                'category' => 'system',
                'channel' => 'push',
                'priority' => 'urgent',
                'status' => 'failed',
                'screen' => '/profile',
                'reference_type' => 'membership',
                'created_at' => Carbon::now()->subHours(2)->subMinutes(10),
                'sent_at' => null,
                'read_at' => null,
                'clicked_at' => null,
                'failed_at' => Carbon::now()->subHours(2)->subMinutes(9),
                'failure_reason' => 'FCM Registration Token Unregistered (device uninstalled the app).',
                'logs' => [
                    [
                        'channel' => 'push',
                        'provider' => 'fcm',
                        'status' => 'failed',
                        'request_payload' => ['token' => 'eZb...test_token'],
                        'response_payload' => ['error' => 'messaging/registration-token-not-registered'],
                        'error_message' => 'FCM Token has expired or app was uninstalled on device.',
                    ],
                ],
            ],
            [
                'title' => 'Weekly Growth Conclave Reminder',
                'body' => 'Join the upcoming Mega Business Conclave this Saturday at 10:00 AM. 150+ Entrepreneurs participating.',
                'type' => 'event_reminder',
                'category' => 'engagement',
                'channel' => 'push',
                'priority' => 'normal',
                'status' => 'pending',
                'screen' => '/events',
                'reference_type' => 'event',
                'created_at' => Carbon::now()->subMinutes(5),
                'sent_at' => null,
                'read_at' => null,
                'clicked_at' => null,
                'failed_at' => null,
                'failure_reason' => null,
                'logs' => [],
            ],
            [
                'title' => 'New Connection Request',
                'body' => 'Pooja Mehta sent you a connection request. Tap to view profile and accept.',
                'type' => 'connection_request',
                'category' => 'activity',
                'channel' => 'push',
                'priority' => 'normal',
                'status' => 'sent',
                'screen' => '/connection-requests',
                'reference_type' => 'connection',
                'created_at' => Carbon::now()->subHours(4),
                'sent_at' => Carbon::now()->subHours(4),
                'read_at' => Carbon::now()->subHours(3)->subMinutes(40),
                'clicked_at' => Carbon::now()->subHours(3)->subMinutes(39),
                'failed_at' => null,
                'failure_reason' => null,
                'logs' => [
                    [
                        'channel' => 'push',
                        'provider' => 'fcm',
                        'status' => 'sent',
                        'request_payload' => ['title' => 'New Connection Request'],
                        'response_payload' => ['success' => 1],
                        'error_message' => null,
                    ],
                ],
            ],
            [
                'title' => 'Life Impact Award Recognition',
                'body' => 'Congratulations! You have been awarded 50 Coins for conducting community welfare drive.',
                'type' => 'life_impact',
                'category' => 'activity',
                'channel' => 'in_app',
                'priority' => 'high',
                'status' => 'sent',
                'screen' => '/life-impact',
                'reference_type' => 'life_impact',
                'created_at' => Carbon::now()->subHours(6),
                'sent_at' => Carbon::now()->subHours(6),
                'read_at' => Carbon::now()->subHours(5),
                'clicked_at' => null,
                'failed_at' => null,
                'failure_reason' => null,
                'logs' => [],
            ],
            [
                'title' => 'Brand Partner Exclusive Offer',
                'body' => 'Special 25% discount on Enterprise Coworking spaces exclusive for Peers members.',
                'type' => 'brand_partner_offer',
                'category' => 'engagement',
                'channel' => 'push',
                'priority' => 'low',
                'status' => 'skipped',
                'screen' => '/brand-partner-details',
                'reference_type' => 'brand_partner',
                'created_at' => Carbon::now()->subHours(8),
                'sent_at' => null,
                'read_at' => null,
                'clicked_at' => null,
                'failed_at' => null,
                'failure_reason' => 'User has disabled promotional marketing push notifications in preferences.',
                'logs' => [],
            ],
        ];

        $hasDeliveryLogsTable = Schema::hasTable('notification_delivery_logs');

        foreach ($dummyNotifications as $index => $item) {
            $user = $users->get($index % max(1, $users->count())) ?? $users->first();
            $userId = $user?->id ?? $primaryUserId;
            $notificationId = (string) Str::uuid();

            $logs = $item['logs'];
            unset($item['logs']);

            $notificationData = array_merge($item, [
                'id' => $notificationId,
                'user_id' => $userId,
                'campaign_id' => null,
                'data' => json_encode([
                    'title' => $item['title'],
                    'body' => $item['body'],
                    'type' => $item['type'],
                    'screen' => $item['screen'],
                    'navigation_screen' => $item['screen'],
                    'reference_type' => $item['reference_type'],
                ]),
                'dedupe_key' => 'dummy_test_'.Str::random(10),
                'updated_at' => Carbon::now(),
            ]);

            DB::table('app_notifications')->insert($notificationData);

            if ($hasDeliveryLogsTable && ! empty($logs)) {
                foreach ($logs as $logItem) {
                    DB::table('notification_delivery_logs')->insert([
                        'id' => (string) Str::uuid(),
                        'notification_id' => $notificationId,
                        'user_id' => $userId,
                        'campaign_id' => null,
                        'channel' => $logItem['channel'],
                        'provider' => $logItem['provider'],
                        'provider_message_id' => 'msg_'.Str::random(16),
                        'status' => $logItem['status'],
                        'request_payload' => json_encode($logItem['request_payload']),
                        'response_payload' => json_encode($logItem['response_payload']),
                        'error_message' => $logItem['error_message'],
                        'attempted_at' => Carbon::now(),
                        'delivered_at' => $logItem['status'] === 'sent' ? Carbon::now() : null,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                }
            }
        }

        $this->command?->info('Dummy notification monitoring data inserted successfully.');
    }
}
