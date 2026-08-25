<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Notifications\AppNotification;
use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AppNotificationCatalogService
{
    /**
     * Complete catalogue of all App Notifications with mobile navigation screens and payloads.
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $catalog = [
        'welcome_notification' => [
            'key' => 'welcome_notification',
            'name' => 'Welcome Notification',
            'category' => 'Membership & Account',
            'description' => 'Sent to new peers immediately upon account approval and activation.',
            'default_title' => 'Welcome to Peers Global!',
            'default_body' => 'Your account is now active. Explore circles and start networking with peers!',
            'navigation_screen' => '/dashboard',
            'default_payload' => [
                'navigation_screen' => '/dashboard',
                'screen' => 'dashboard',
                'type' => 'welcome',
                'tap_destination' => '/dashboard',
            ],
            'dynamic_params' => [
                '{name}' => 'Display name of the user',
            ],
            'priority' => 'high',
            'channel' => 'push',
            'icon' => 'bi bi-person-check',
        ],
        'new_post' => [
            'key' => 'new_post',
            'name' => 'New Post in Feed / Circle',
            'category' => 'Social & Feed',
            'description' => 'Notifies connected peers and circle members when a new post is published.',
            'default_title' => 'New post by {actorName}',
            'default_body' => '{actorName} published a new post: "{postTitle}"',
            'navigation_screen' => '/member-profile',
            'default_payload' => [
                'navigation_screen' => '/member-profile',
                'screen' => 'member-profile',
                'type' => 'new_post',
                'activity_type' => 'post',
                'tap_destination' => '/member-profile',
                'post_id' => '{postId}',
                'member_id' => '{actorId}',
            ],
            'dynamic_params' => [
                '{actorName}' => 'Author name of the post',
                '{postTitle}' => 'Snippet or title of the post',
                '{postId}' => 'Unique UUID/ID of the post',
                '{actorId}' => 'UUID of the post author',
            ],
            'priority' => 'medium',
            'channel' => 'push',
            'icon' => 'bi bi-chat-square-text',
        ],
        'requirement_created' => [
            'key' => 'requirement_created',
            'name' => 'New Business Requirement Posted',
            'category' => 'Business & Deals',
            'description' => 'Sent when a peer posts an open business requirement / ask.',
            'default_title' => 'New Requirement from {actorName}',
            'default_body' => 'A new business requirement has been posted: "{requirementTitle}". Tap to view.',
            'navigation_screen' => '/post-details',
            'default_payload' => [
                'navigation_screen' => '/post-details',
                'screen' => 'post-details',
                'type' => 'requirement',
                'activity_type' => 'requirement',
                'tap_destination' => '/post-details',
                'requirement_id' => '{requirementId}',
                'post_id' => '{postId}',
            ],
            'dynamic_params' => [
                '{actorName}' => 'Name of the requirement poster',
                '{requirementTitle}' => 'Title or summary of the business ask',
                '{requirementId}' => 'UUID of the requirement',
                '{postId}' => 'UUID of the associated post',
            ],
            'priority' => 'high',
            'channel' => 'push',
            'icon' => 'bi bi-briefcase',
        ],
        'p2p_meeting_notification' => [
            'key' => 'p2p_meeting_notification',
            'name' => 'P2P Meeting Scheduled',
            'category' => 'Networking & P2P',
            'description' => 'Sent to invitee notifying them of a scheduled 1-on-1 peer meeting.',
            'default_title' => 'New P2P Meeting Scheduled',
            'default_body' => 'You have a scheduled peer-to-peer meeting with {actorName} on {meetingDate}.',
            'navigation_screen' => '/meetings',
            'default_payload' => [
                'navigation_screen' => '/meetings',
                'screen' => 'meetings',
                'type' => 'meeting_scheduled',
                'tap_destination' => '/meetings',
                'meeting_id' => '{meetingId}',
            ],
            'dynamic_params' => [
                '{actorName}' => 'Name of the meeting partner',
                '{meetingDate}' => 'Scheduled date and time of the meeting',
                '{meetingId}' => 'UUID of the meeting record',
            ],
            'priority' => 'high',
            'channel' => 'push',
            'icon' => 'bi bi-calendar2-week',
        ],
        'p2p_reschedule_notification' => [
            'key' => 'p2p_reschedule_notification',
            'name' => 'P2P Meeting Rescheduled',
            'category' => 'Networking & P2P',
            'description' => 'Sent when a P2P meeting schedule is modified by a partner.',
            'default_title' => 'P2P Meeting Rescheduled',
            'default_body' => '{actorName} has rescheduled the meeting to {meetingDate} at {meetingPlace}.',
            'navigation_screen' => '/meetings',
            'default_payload' => [
                'navigation_screen' => '/meetings',
                'screen' => 'meetings',
                'type' => 'meeting_rescheduled',
                'tap_destination' => '/meetings',
                'meeting_id' => '{meetingId}',
            ],
            'dynamic_params' => [
                '{actorName}' => 'Name of the meeting partner',
                '{meetingDate}' => 'New date and time',
                '{meetingPlace}' => 'New meeting location or virtual link',
            ],
            'priority' => 'high',
            'channel' => 'push',
            'icon' => 'bi bi-calendar-event',
        ],
        'business_deal_notification' => [
            'key' => 'business_deal_notification',
            'name' => 'Business Deal Logged',
            'category' => 'Business & Deals',
            'description' => 'Sent to a deal partner when a business deal/transaction is registered.',
            'default_title' => 'Business Deal Logged',
            'default_body' => '{actorName} has logged a business deal worth ₹{amount} with you.',
            'navigation_screen' => '/business-deals',
            'default_payload' => [
                'navigation_screen' => '/business-deals',
                'screen' => 'business_deals',
                'type' => 'deal_logged',
                'tap_destination' => '/business-deals',
                'deal_id' => '{dealId}',
            ],
            'dynamic_params' => [
                '{actorName}' => 'Name of the deal partner',
                '{amount}' => 'Monetary value of the deal',
                '{dealId}' => 'UUID of the deal record',
            ],
            'priority' => 'high',
            'channel' => 'push',
            'icon' => 'bi bi-cash-stack',
        ],
        'coin_earned_notification' => [
            'key' => 'coin_earned_notification',
            'name' => 'Coins Awarded / Earned',
            'category' => 'Coins & Rewards',
            'description' => 'Sent when a user earns or claims reward coins successfully.',
            'default_title' => 'Coins Awarded!',
            'default_body' => 'Congratulations! You have received {coins} coins for {activityName}.',
            'navigation_screen' => '/wallet',
            'default_payload' => [
                'navigation_screen' => '/wallet',
                'screen' => 'wallet',
                'type' => 'coins_received',
                'tap_destination' => '/wallet',
            ],
            'dynamic_params' => [
                '{coins}' => 'Number of coins awarded',
                '{activityName}' => 'Name of the rewarded activity',
            ],
            'priority' => 'medium',
            'channel' => 'push',
            'icon' => 'bi bi-coin',
        ],
        'coin_claim_reviewed' => [
            'key' => 'coin_claim_reviewed',
            'name' => 'Coin Claim Reviewed',
            'category' => 'Coins & Rewards',
            'description' => 'Sent when admin reviews and approves or rejects a coin claim submission.',
            'default_title' => 'Coin Claim Reviewed',
            'default_body' => 'Your coin claim for {activityName} has been {status}.',
            'navigation_screen' => '/wallet',
            'default_payload' => [
                'navigation_screen' => '/wallet',
                'screen' => 'wallet',
                'type' => 'claim_reviewed',
                'tap_destination' => '/wallet',
            ],
            'dynamic_params' => [
                '{activityName}' => 'Name of the activity claimed',
                '{status}' => 'approved / rejected',
            ],
            'priority' => 'medium',
            'channel' => 'push',
            'icon' => 'bi bi-check2-circle',
        ],
        'circle_join_notification' => [
            'key' => 'circle_join_notification',
            'name' => 'Circle Membership Approved',
            'category' => 'Circles & Communities',
            'description' => 'Sent when a user joining request for a circle gets accepted.',
            'default_title' => 'Circle Request Approved',
            'default_body' => 'Your request to join the Circle {circleName} has been approved. Welcome!',
            'navigation_screen' => '/circle_details',
            'default_payload' => [
                'navigation_screen' => '/circle_details',
                'screen' => 'circle_details',
                'type' => 'circle_approved',
                'tap_destination' => '/circle_details',
                'circle_id' => '{circleId}',
            ],
            'dynamic_params' => [
                '{circleName}' => 'Name of the joined circle',
                '{circleId}' => 'UUID of the circle',
            ],
            'priority' => 'high',
            'channel' => 'push',
            'icon' => 'bi bi-diagram-3',
        ],
        'trending_circle' => [
            'key' => 'trending_circle',
            'name' => 'Trending Circle Highlight',
            'category' => 'Circles & Communities',
            'description' => 'Sent to recommend high-activity circles to explore and join.',
            'default_title' => 'Trending Circle Highlight',
            'default_body' => '{circleName} is trending today! Tap to view members and join.',
            'navigation_screen' => '/join-circle',
            'default_payload' => [
                'navigation_screen' => '/join-circle',
                'screen' => 'join-circle',
                'type' => 'trending_circle',
                'tap_destination' => '/join-circle',
                'circle_id' => '{circleId}',
            ],
            'dynamic_params' => [
                '{circleName}' => 'Name of the featured circle',
                '{circleId}' => 'UUID of the circle',
            ],
            'priority' => 'medium',
            'channel' => 'push',
            'icon' => 'bi bi-fire',
        ],
        'follow_accepted' => [
            'key' => 'follow_accepted',
            'name' => 'Follow Request Accepted',
            'category' => 'Networking & P2P',
            'description' => 'Sent when a peer accepts your connection/follow request.',
            'default_title' => 'Connection Request Accepted',
            'default_body' => '{actorName} has accepted your follow request. Start collaborating!',
            'navigation_screen' => '/member-profile',
            'default_payload' => [
                'navigation_screen' => '/member-profile',
                'screen' => 'peer_profile',
                'type' => 'follow_accept',
                'tap_destination' => '/member-profile',
                'member_id' => '{actorId}',
            ],
            'dynamic_params' => [
                '{actorName}' => 'Name of the peer who accepted',
                '{actorId}' => 'UUID of the peer',
            ],
            'priority' => 'medium',
            'channel' => 'push',
            'icon' => 'bi bi-person-check-fill',
        ],
        'follow_requested' => [
            'key' => 'follow_requested',
            'name' => 'New Follow / Connection Request',
            'category' => 'Networking & P2P',
            'description' => 'Sent when another peer requests to follow/connect with you.',
            'default_title' => 'New Connection Request',
            'default_body' => '{actorName} wants to connect with you on Peers Global.',
            'navigation_screen' => '/pending-requests',
            'default_payload' => [
                'navigation_screen' => '/pending-requests',
                'screen' => 'pending_requests',
                'type' => 'follow_request',
                'tap_destination' => '/pending-requests',
                'member_id' => '{actorId}',
            ],
            'dynamic_params' => [
                '{actorName}' => 'Name of the requesting peer',
                '{actorId}' => 'UUID of the peer',
            ],
            'priority' => 'high',
            'channel' => 'push',
            'icon' => 'bi bi-person-plus-fill',
        ],
        'referral_joined_notification' => [
            'key' => 'referral_joined_notification',
            'name' => 'Referral Joined Platform',
            'category' => 'Networking & P2P',
            'description' => 'Sent to referrer when their invited contact registers on the platform.',
            'default_title' => 'New Referral Joined!',
            'default_body' => '{referredName} has joined the community using your referral link!',
            'navigation_screen' => '/referrals',
            'default_payload' => [
                'navigation_screen' => '/referrals',
                'screen' => 'referrals',
                'type' => 'referral_joined',
                'tap_destination' => '/referrals',
            ],
            'dynamic_params' => [
                '{referredName}' => 'Name of the registered referral',
            ],
            'priority' => 'medium',
            'channel' => 'push',
            'icon' => 'bi bi-share',
        ],
        'membership_expiry_reminder' => [
            'key' => 'membership_expiry_reminder',
            'name' => 'Membership Expired Reminder',
            'category' => 'Membership & Account',
            'description' => 'Sent on the day a user membership reaches expiry.',
            'default_title' => 'Membership Expired',
            'default_body' => 'Your Peers Global membership has expired. Renew today to continue networking.',
            'navigation_screen' => '/profile',
            'default_payload' => [
                'navigation_screen' => '/profile',
                'screen' => 'membership',
                'type' => 'membership_expiry',
                'tap_destination' => '/profile',
            ],
            'dynamic_params' => [],
            'priority' => 'urgent',
            'channel' => 'push',
            'icon' => 'bi bi-shield-exclamation',
        ],
        'upcoming_membership_expiry_reminder' => [
            'key' => 'upcoming_membership_expiry_reminder',
            'name' => 'Upcoming Membership Expiry Warning',
            'category' => 'Membership & Account',
            'description' => 'Sent as an advance warning before membership expires.',
            'default_title' => 'Renew Your Membership',
            'default_body' => 'Your Peers Global membership is expiring in {days} days. Renew now to avoid interruption.',
            'navigation_screen' => '/profile',
            'default_payload' => [
                'navigation_screen' => '/profile',
                'screen' => 'membership',
                'type' => 'membership_expiry',
                'tap_destination' => '/profile',
            ],
            'dynamic_params' => [
                '{days}' => 'Number of days remaining (e.g. 7, 3)',
            ],
            'priority' => 'high',
            'channel' => 'push',
            'icon' => 'bi bi-calendar-range',
        ],
        'new_offer_added' => [
            'key' => 'new_offer_added',
            'name' => 'New Brand Partner Offer',
            'category' => 'Brand Partners & Offers',
            'description' => 'Sent when a brand partner posts a new exclusive discount or offer.',
            'default_title' => 'New Offer Alert!',
            'default_body' => '{partnerName} added a new offer: "{offerTitle}". Check it out!',
            'navigation_screen' => '/brand-partner-details',
            'default_payload' => [
                'navigation_screen' => '/brand-partner-details',
                'screen' => 'offers',
                'type' => 'brand_partner_offer',
                'tap_destination' => '/brand-partner-details',
                'partner_id' => '{partnerId}',
                'offer_id' => '{offerId}',
            ],
            'dynamic_params' => [
                '{partnerName}' => 'Name of the brand partner company',
                '{offerTitle}' => 'Title of the discount/offer',
                '{partnerId}' => 'UUID of the partner',
                '{offerId}' => 'UUID of the offer',
            ],
            'priority' => 'medium',
            'channel' => 'push',
            'icon' => 'bi bi-tags',
        ],
        'new_partner_joined' => [
            'key' => 'new_partner_joined',
            'name' => 'New Brand Partner Joined',
            'category' => 'Brand Partners & Offers',
            'description' => 'Sent when a new brand partner registers on the platform.',
            'default_title' => 'New Brand Partner Welcome',
            'default_body' => 'Please welcome our new brand partner: {partnerName}!',
            'navigation_screen' => '/brand-partner-details',
            'default_payload' => [
                'navigation_screen' => '/brand-partner-details',
                'screen' => 'partners',
                'type' => 'new_partner',
                'tap_destination' => '/brand-partner-details',
                'partner_id' => '{partnerId}',
            ],
            'dynamic_params' => [
                '{partnerName}' => 'Name of the brand partner',
                '{partnerId}' => 'UUID of the brand partner',
            ],
            'priority' => 'medium',
            'channel' => 'push',
            'icon' => 'bi bi-handshake',
        ],
        'offer_expiry_reminder' => [
            'key' => 'offer_expiry_reminder',
            'name' => 'Claimed Offer Expiring Soon',
            'category' => 'Brand Partners & Offers',
            'description' => 'Sent as a reminder before a claimed coupon/offer expires.',
            'default_title' => 'Claimed Offer Expiring',
            'default_body' => 'Your claimed offer "{offerTitle}" is expiring soon. Redeem it now!',
            'navigation_screen' => '/my-offers',
            'default_payload' => [
                'navigation_screen' => '/my-offers',
                'screen' => 'my_offers',
                'type' => 'offer_expiring',
                'tap_destination' => '/my-offers',
            ],
            'dynamic_params' => [
                '{offerTitle}' => 'Title of the claimed offer',
            ],
            'priority' => 'high',
            'channel' => 'push',
            'icon' => 'bi bi-clock-history',
        ],
        'collaboration_created' => [
            'key' => 'collaboration_created',
            'name' => 'New Collaboration Opportunity',
            'category' => 'Business & Deals',
            'description' => 'Sent to peers when a new business collaboration post is created.',
            'default_title' => 'New Collaboration Opportunity',
            'default_body' => '{creatorName} posted a new collaboration: "{title}"',
            'navigation_screen' => '/collaborations',
            'default_payload' => [
                'navigation_screen' => '/collaborations',
                'screen' => 'collaboration_details',
                'type' => 'collaboration_created',
                'tap_destination' => '/collaborations',
                'collaboration_id' => '{collaborationId}',
            ],
            'dynamic_params' => [
                '{creatorName}' => 'Name of the creator peer',
                '{title}' => 'Title of the collaboration opportunity',
                '{collaborationId}' => 'UUID of the collaboration',
            ],
            'priority' => 'high',
            'channel' => 'push',
            'icon' => 'bi bi-lightbulb',
        ],
        'collaboration_completed' => [
            'key' => 'collaboration_completed',
            'name' => 'Collaboration Marked Complete',
            'category' => 'Business & Deals',
            'description' => 'Sent when a collaboration is successfully executed and marked complete.',
            'default_title' => 'Collaboration Completed!',
            'default_body' => 'A collaboration you are involved in ("{title}") has been marked completed.',
            'navigation_screen' => '/collaborations',
            'default_payload' => [
                'navigation_screen' => '/collaborations',
                'screen' => 'collaboration_details',
                'type' => 'collaboration_completed',
                'tap_destination' => '/collaborations',
                'collaboration_id' => '{collaborationId}',
            ],
            'dynamic_params' => [
                '{title}' => 'Title of the collaboration',
            ],
            'priority' => 'medium',
            'channel' => 'push',
            'icon' => 'bi bi-check-circle',
        ],
        'impact_reviewed' => [
            'key' => 'impact_reviewed',
            'name' => 'Life Impact Submission Reviewed',
            'category' => 'Impact & Giving',
            'description' => 'Sent when a user life impact submission is approved or rejected by admin.',
            'default_title' => 'Life Impact Submission Reviewed',
            'default_body' => 'Your impact action "{action}" has been {status}.',
            'navigation_screen' => '/life-impact',
            'default_payload' => [
                'navigation_screen' => '/life-impact',
                'screen' => 'life_impact',
                'type' => 'impact_reviewed',
                'tap_destination' => '/life-impact',
            ],
            'dynamic_params' => [
                '{action}' => 'Summary of the impact activity',
                '{status}' => 'approved / rejected',
            ],
            'priority' => 'medium',
            'channel' => 'push',
            'icon' => 'bi bi-heart-pulse',
        ],
        'circular_notification' => [
            'key' => 'circular_notification',
            'name' => 'New Circular / Announcement Published',
            'category' => 'Events & Circulars',
            'description' => 'Sent to the community when a new official circular or bulletin is published.',
            'default_title' => 'New Circular: {circularTitle}',
            'default_body' => '{circularSummary}',
            'navigation_screen' => '/circulars',
            'default_payload' => [
                'navigation_screen' => '/circulars',
                'screen' => 'circulars',
                'type' => 'circular',
                'tap_destination' => '/circulars',
                'circular_id' => '{circularId}',
            ],
            'dynamic_params' => [
                '{circularTitle}' => 'Title of the circular',
                '{circularSummary}' => 'Brief summary of the announcement',
                '{circularId}' => 'UUID of the circular',
            ],
            'priority' => 'high',
            'channel' => 'push',
            'icon' => 'bi bi-megaphone',
        ],
        'support_ticket_notification' => [
            'key' => 'support_ticket_notification',
            'name' => 'Support Ticket Update / Resolved',
            'category' => 'Help & Support',
            'description' => 'Sent when a support ticket status changes or is answered by admin.',
            'default_title' => 'Support Ticket Update',
            'default_body' => 'Your support ticket {ticketNumber} has been updated to {status}.',
            'navigation_screen' => '/support',
            'default_payload' => [
                'navigation_screen' => '/support',
                'screen' => 'support',
                'type' => 'ticket_status',
                'tap_destination' => '/support',
                'ticket_id' => '{ticketId}',
            ],
            'dynamic_params' => [
                '{ticketNumber}' => 'Ticket reference ID (e.g. SUP-2026-904)',
                '{status}' => 'resolved / in progress / answered',
                '{ticketId}' => 'UUID of the ticket',
            ],
            'priority' => 'medium',
            'channel' => 'push',
            'icon' => 'bi bi-ticket-perforated',
        ],
        'chat_message_notification' => [
            'key' => 'chat_message_notification',
            'name' => 'New Circle Chat Message',
            'category' => 'Social & Feed',
            'description' => 'Sent when a peer posts a message in your circle chat.',
            'default_title' => 'New Message from {senderName}',
            'default_body' => '{senderName}: "{messageText}"',
            'navigation_screen' => '/circle_chat',
            'default_payload' => [
                'navigation_screen' => '/circle_chat',
                'screen' => 'chat_room',
                'type' => 'chat_message',
                'tap_destination' => '/circle_chat',
                'circle_id' => '{circleId}',
            ],
            'dynamic_params' => [
                '{senderName}' => 'Name of the message author',
                '{messageText}' => 'Snippet of the message',
                '{circleId}' => 'UUID of the circle',
            ],
            'priority' => 'high',
            'channel' => 'push',
            'icon' => 'bi bi-chat-dots',
        ],
        'daily_engagement_reminder' => [
            'key' => 'daily_engagement_reminder',
            'name' => 'Daily Engagement Reminder',
            'category' => 'Membership & Account',
            'description' => 'Sent to prompt daily networking and check-ins.',
            'default_title' => 'Connect with Peers Today',
            'default_body' => 'Check out new active circles, asks, and peer updates waiting for you on Peers Global.',
            'navigation_screen' => '/home',
            'default_payload' => [
                'navigation_screen' => '/home',
                'screen' => 'dashboard',
                'type' => 'daily_reminder',
                'tap_destination' => '/home',
            ],
            'dynamic_params' => [],
            'priority' => 'low',
            'channel' => 'push',
            'icon' => 'bi bi-lightning-charge',
        ],
        'app_update_reminder' => [
            'key' => 'app_update_reminder',
            'name' => 'New App Version Available',
            'category' => 'Membership & Account',
            'description' => 'Sent when a newer mobile app version is published with new features.',
            'default_title' => 'New Peers Global Update Available!',
            'default_body' => 'Version {appVersion} is now available with new networking features and performance enhancements.',
            'navigation_screen' => '/settings',
            'default_payload' => [
                'navigation_screen' => '/settings',
                'screen' => 'app_update',
                'type' => 'app_update',
                'tap_destination' => '/settings',
                'app_version' => '{appVersion}',
            ],
            'dynamic_params' => [
                '{appVersion}' => 'Version string (e.g. 2.5.0)',
            ],
            'priority' => 'high',
            'channel' => 'push',
            'icon' => 'bi bi-arrow-up-circle',
        ],
    ];

    /**
     * Get all catalog items merged with active database template overrides.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getAll(?string $search = null, ?string $category = null): Collection
    {
        $dbRecords = Schema::hasTable('notification_templates')
            ? NotificationTemplate::all()->keyBy('template_key')
            : collect();

        $knownKeys = [];

        // 1. Process static catalog merged with DB overrides
        $items = collect(self::$catalog)->map(function (array $tpl, string $key) use ($dbRecords, &$knownKeys): array {
            $knownKeys[] = $key;
            $db = $dbRecords->get($key);

            $title = $db && filled($db->title_template) ? $db->title_template : $tpl['default_title'];
            $body = $db && filled($db->body_template) ? $db->body_template : $tpl['default_body'];
            $payload = $db && ! empty($db->default_payload) ? $db->default_payload : $tpl['default_payload'];

            // Ensure navigation_screen is always present in payload
            if (! isset($payload['navigation_screen']) && isset($tpl['navigation_screen'])) {
                $payload['navigation_screen'] = $tpl['navigation_screen'];
            }

            return array_merge($tpl, [
                'title' => $title,
                'body' => $body,
                'payload' => $payload,
                'has_db_override' => $db !== null,
                'db_record' => $db,
                'is_dynamic' => false,
            ]);
        });

        // 2. Automatically discover ANY new notification template created in the database
        foreach ($dbRecords as $dbKey => $dbRecord) {
            if (! in_array($dbKey, $knownKeys, true)) {
                $knownKeys[] = $dbKey;
                $items->push($this->formatDbTemplateToCatalogItem($dbRecord));
            }
        }

        // 3. Automatically discover ANY new notification type logged/sent in app_notifications
        if (Schema::hasTable('app_notifications')) {
            try {
                $distinctTypes = AppNotification::select('type', 'category', 'screen', 'title', 'body', 'data')
                    ->whereNotNull('type')
                    ->whereNotIn('type', $knownKeys)
                    ->latest()
                    ->get()
                    ->unique('type');

                foreach ($distinctTypes as $appNotif) {
                    $typeKey = (string) $appNotif->type;
                    if (! in_array($typeKey, $knownKeys, true) && filled($typeKey)) {
                        $knownKeys[] = $typeKey;
                        $items->push($this->formatAppNotificationToCatalogItem($appNotif));
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        if (filled($category) && $category !== 'all') {
            $items = $items->filter(fn (array $item): bool => strtolower((string) $item['category']) === strtolower($category));
        }

        if (filled($search)) {
            $needle = strtolower(trim($search));
            $items = $items->filter(function (array $item) use ($needle): bool {
                return str_contains(strtolower((string) $item['name']), $needle)
                    || str_contains(strtolower((string) $item['key']), $needle)
                    || str_contains(strtolower((string) $item['navigation_screen']), $needle)
                    || str_contains(strtolower((string) $item['description']), $needle)
                    || str_contains(strtolower((string) $item['category']), $needle);
            });
        }

        return $items
            ->sortBy(fn (array $item): string => strtolower((string) ($item['name'] ?? '')))
            ->values();
    }

    /**
     * Format a database template into a standard catalog item array.
     *
     * @return array<string, mixed>
     */
    public function formatDbTemplateToCatalogItem(NotificationTemplate $db): array
    {
        $payload = (array) ($db->default_payload ?? []);
        $navScreen = (string) ($payload['navigation_screen'] ?? ($payload['screen'] ?? $this->inferNavigationScreen((string) $db->template_key)));

        if (! isset($payload['navigation_screen'])) {
            $payload['navigation_screen'] = $navScreen;
        }

        $dynamicParams = (array) ($db->dynamic_params ?? [
            '{name}' => 'Recipient member name',
        ]);

        return [
            'key' => (string) $db->template_key,
            'name' => (string) ($db->name ?? Str::headline((string) $db->template_key)),
            'category' => (string) ($payload['category'] ?? 'Custom & Dynamic'),
            'icon' => 'bi bi-bell-fill',
            'priority' => (string) ($payload['priority'] ?? 'high'),
            'channels' => ['push', 'in_app'],
            'navigation_screen' => $navScreen,
            'description' => 'Dynamically registered notification template in database.',
            'default_title' => (string) ($db->title_template ?? 'Notification Alert'),
            'default_body' => (string) ($db->body_template ?? 'You have a new update.'),
            'default_payload' => $payload,
            'dynamic_params' => $dynamicParams,
            'title' => (string) ($db->title_template ?? 'Notification Alert'),
            'body' => (string) ($db->body_template ?? 'You have a new update.'),
            'payload' => $payload,
            'has_db_override' => true,
            'db_record' => $db,
            'is_dynamic' => true,
        ];
    }

    /**
     * Format an existing app_notification row into a catalog item.
     *
     * @return array<string, mixed>
     */
    public function formatAppNotificationToCatalogItem(AppNotification $notif): array
    {
        $type = (string) $notif->type;
        $payload = (array) ($notif->data ?? []);
        $navScreen = (string) ($notif->screen ?? ($payload['navigation_screen'] ?? $this->inferNavigationScreen($type)));

        if (! isset($payload['navigation_screen'])) {
            $payload['navigation_screen'] = $navScreen;
        }

        return [
            'key' => $type,
            'name' => Str::headline($type),
            'category' => (string) ($notif->category ?? 'Auto-Discovered'),
            'icon' => 'bi bi-lightning-charge',
            'priority' => (string) ($notif->priority ?? 'high'),
            'channels' => ['push', 'in_app'],
            'navigation_screen' => $navScreen,
            'description' => 'Discovered automatically from live app notification events.',
            'default_title' => (string) ($notif->title ?? Str::headline($type)),
            'default_body' => (string) ($notif->body ?? 'New notification event.'),
            'default_payload' => $payload,
            'dynamic_params' => ['{name}' => 'Recipient member name'],
            'title' => (string) ($notif->title ?? Str::headline($type)),
            'body' => (string) ($notif->body ?? 'New notification event.'),
            'payload' => $payload,
            'has_db_override' => false,
            'db_record' => null,
            'is_dynamic' => true,
        ];
    }

    /**
     * Infer target Flutter navigation screen route from notification key.
     */
    public function inferNavigationScreen(string $key): string
    {
        $normalized = strtolower($key);

        return match (true) {
            str_contains($normalized, 'profile') || str_contains($normalized, 'connection') => '/member-profile',
            str_contains($normalized, 'post') || str_contains($normalized, 'requirement') || str_contains($normalized, 'like') || str_contains($normalized, 'comment') => '/post-details',
            str_contains($normalized, 'meeting') => '/meetings',
            str_contains($normalized, 'deal') => '/business-deals',
            str_contains($normalized, 'coin') || str_contains($normalized, 'wallet') => '/wallet',
            str_contains($normalized, 'chat') || str_contains($normalized, 'message') => '/circle_chat',
            str_contains($normalized, 'circle') => '/circle_details',
            str_contains($normalized, 'referral') => '/referrals',
            str_contains($normalized, 'offer') => '/my-offers',
            str_contains($normalized, 'partner') => '/brand-partner-details',
            str_contains($normalized, 'impact') => '/life-impact',
            str_contains($normalized, 'circular') => '/circulars',
            str_contains($normalized, 'ticket') || str_contains($normalized, 'support') => '/support',
            str_contains($normalized, 'setting') => '/settings',
            default => '/dashboard',
        };
    }

    /**
     * Get a specific notification by its key.
     *
     * @return array<string, mixed>|null
     */
    public function getByKey(string $key): ?array
    {
        if (isset(self::$catalog[$key])) {
            $tpl = self::$catalog[$key];
            $db = Schema::hasTable('notification_templates')
                ? NotificationTemplate::where('template_key', $key)->first()
                : null;

            $title = $db && filled($db->title_template) ? $db->title_template : $tpl['default_title'];
            $body = $db && filled($db->body_template) ? $db->body_template : $tpl['default_body'];
            $payload = $db && ! empty($db->default_payload) ? $db->default_payload : $tpl['default_payload'];

            if (! isset($payload['navigation_screen']) && isset($tpl['navigation_screen'])) {
                $payload['navigation_screen'] = $tpl['navigation_screen'];
            }

            return array_merge($tpl, [
                'title' => $title,
                'body' => $body,
                'payload' => $payload,
                'has_db_override' => $db !== null,
                'db_record' => $db,
                'is_dynamic' => false,
            ]);
        }

        // Check if exists as a custom DB template
        if (Schema::hasTable('notification_templates')) {
            $db = NotificationTemplate::where('template_key', $key)->first();
            if ($db) {
                return $this->formatDbTemplateToCatalogItem($db);
            }
        }

        // Check if exists as an app notification record
        if (Schema::hasTable('app_notifications')) {
            $appNotif = AppNotification::where('type', $key)->latest()->first();
            if ($appNotif) {
                return $this->formatAppNotificationToCatalogItem($appNotif);
            }
        }

        return null;
    }

    /**
     * Render title, body, and payload for a target user with resolved dynamic parameters.
     *
     * @param  array<string, string>  $customReplacements
     * @return array{title: string, body: string, navigation_screen: string, payload: array<string, mixed>}
     */
    public function renderForUser(string $key, ?User $user = null, array $customReplacements = []): array
    {
        $tpl = $this->getByKey($key) ?? self::$catalog['welcome_notification'];

        $displayName = 'Peer Member';
        $email = 'peer@example.com';
        $circleName = 'Executive Founders Circle';
        $userId = (string) Str::uuid();

        if ($user) {
            $userId = (string) $user->id;
            $name = trim(((string) ($user->first_name ?? '')).' '.((string) ($user->last_name ?? '')));
            if ($name === '') {
                $name = (string) ($user->name ?? $user->display_name ?? 'Peer Member');
            }
            $displayName = $name;
            $email = (string) ($user->email ?? $email);

            // Attempt to resolve primary circle name
            if (Schema::hasTable('circles') && Schema::hasTable('circle_members') && method_exists($user, 'circles') && $user->circles()->exists()) {
                $circleName = (string) ($user->circles()->first()->name ?? $circleName);
            }
        }

        $defaultReplacements = [
            '{name}' => $displayName,
            '{actorName}' => 'Alex Morgan',
            '{actorId}' => (string) Str::uuid(),
            '{requirementTitle}' => 'Senior Laravel Backend Specialist Needed',
            '{requirementCategory}' => 'Software Development',
            '{requirementId}' => (string) Str::uuid(),
            '{postTitle}' => 'New Industry Opportunities for 2026',
            '{postId}' => (string) Str::uuid(),
            '{commentText}' => 'Great insights! Would love to connect.',
            '{commentId}' => (string) Str::uuid(),
            '{meetingDate}' => now()->addDays(2)->format('l, M d at 4:00 PM'),
            '{meetingPlace}' => 'Peers Virtual Meeting Room #4',
            '{meetingId}' => (string) Str::uuid(),
            '{circleName}' => $circleName,
            '{circleId}' => (string) Str::uuid(),
            '{referrerName}' => 'Rajesh Sharma',
            '{referralTitle}' => 'Enterprise Cloud Migration RFP',
            '{referralId}' => (string) Str::uuid(),
            '{clientName}' => 'Apex Global Ventures',
            '{dealTitle}' => 'Custom CRM Platform Architecture',
            '{dealValue}' => '50,000',
            '{dealId}' => (string) Str::uuid(),
            '{coins}' => '100',
            '{balance}' => '450',
            '{claimedDeal}' => '25% Discount Voucher',
            '{expiryDate}' => now()->addDays(7)->format('M d, Y'),
            '{days}' => '5',
            '{partnerName}' => 'EcoGreen Solutions',
            '{partnerId}' => (string) Str::uuid(),
            '{offerTitle}' => '30% Discount on Enterprise Cloud Subscriptions',
            '{offerId}' => (string) Str::uuid(),
            '{creatorName}' => 'Priya Mehta',
            '{title}' => 'Joint ESG Sustainability Report 2026',
            '{collaborationId}' => (string) Str::uuid(),
            '{action}' => 'Organized Green Business Summit',
            '{circularTitle}' => 'Annual Members Networking Meet 2026',
            '{circularSummary}' => 'Join us for the national leadership conclave on Friday.',
            '{circularId}' => (string) Str::uuid(),
            '{ticketNumber}' => 'TICK-'.rand(1000, 9999),
            '{ticketId}' => (string) Str::uuid(),
            '{senderName}' => 'Aman Gupta',
            '{messageText}' => 'Hey! Are you attending tomorrow circle meetup?',
            '{appVersion}' => '2.5.0',
            '{user_id}' => $userId,
            '{member_id}' => $userId,
        ];

        $replacements = array_merge($defaultReplacements, $customReplacements);

        $renderedTitle = strtr((string) $tpl['title'], $replacements);
        $renderedBody = strtr((string) $tpl['body'], $replacements);

        // Render payload values recursively
        $renderedPayload = $this->replaceInPayload((array) $tpl['payload'], $replacements);

        // Ensure user_id, notification_type, and navigation_screen are properly populated
        $renderedPayload['navigation_screen'] = (string) ($renderedPayload['navigation_screen'] ?? $tpl['navigation_screen']);
        $renderedPayload['screen'] = (string) ($renderedPayload['screen'] ?? ltrim((string) $tpl['navigation_screen'], '/'));
        $renderedPayload['tap_destination'] = (string) ($renderedPayload['tap_destination'] ?? $tpl['navigation_screen']);
        $renderedPayload['user_id'] = $userId;
        $renderedPayload['type'] = (string) ($tpl['key']);

        return [
            'title' => $renderedTitle,
            'body' => $renderedBody,
            'navigation_screen' => (string) $tpl['navigation_screen'],
            'payload' => $renderedPayload,
        ];
    }

    /**
     * Replace placeholders in nested payload array.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $replacements
     * @return array<string, mixed>
     */
    private function replaceInPayload(array $payload, array $replacements): array
    {
        $result = [];
        foreach ($payload as $key => $val) {
            if (is_string($val)) {
                $result[$key] = strtr($val, $replacements);
            } elseif (is_array($val)) {
                $result[$key] = $this->replaceInPayload($val, $replacements);
            } else {
                $result[$key] = $val;
            }
        }

        return $result;
    }

    /**
     * Get distinct category list.
     *
     * @return array<string>
     */
    public function getCategories(): array
    {
        return $this->getAll()
            ->pluck('category')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get all unique navigation screen routes supported by the mobile app.
     *
     * @return array<string, array{route: string, label: string, icon: string}>
     */
    public function getNavigationScreens(): array
    {
        return [
            '/dashboard' => ['route' => '/dashboard', 'label' => 'Dashboard / Home Feed', 'icon' => 'bi bi-house-door'],
            '/member-profile' => ['route' => '/member-profile', 'label' => 'Peer / Member Profile', 'icon' => 'bi bi-person-badge'],
            '/post-details' => ['route' => '/post-details', 'label' => 'Post & Requirement Details', 'icon' => 'bi bi-file-text'],
            '/meetings' => ['route' => '/meetings', 'label' => 'P2P Meetings Hub', 'icon' => 'bi bi-calendar2-event'],
            '/wallet' => ['route' => '/wallet', 'label' => 'Coins & Rewards Wallet', 'icon' => 'bi bi-wallet2'],
            '/business-deals' => ['route' => '/business-deals', 'label' => 'Business Deals Register', 'icon' => 'bi bi-briefcase'],
            '/circle_details' => ['route' => '/circle_details', 'label' => 'Circle Overview & Members', 'icon' => 'bi bi-diagram-3'],
            '/join-circle' => ['route' => '/join-circle', 'label' => 'Explore & Join Circles', 'icon' => 'bi bi-compass'],
            '/pending-requests' => ['route' => '/pending-requests', 'label' => 'Pending Connection Requests', 'icon' => 'bi bi-clock-history'],
            '/referrals' => ['route' => '/referrals', 'label' => 'My Referrals & Network', 'icon' => 'bi bi-people'],
            '/profile' => ['route' => '/profile', 'label' => 'User Profile & Membership', 'icon' => 'bi bi-person-gear'],
            '/brand-partner-details' => ['route' => '/brand-partner-details', 'label' => 'Brand Partners & Exclusive Offers', 'icon' => 'bi bi-tag'],
            '/my-offers' => ['route' => '/my-offers', 'label' => 'Claimed Offers & Coupons', 'icon' => 'bi bi-ticket'],
            '/collaborations' => ['route' => '/collaborations', 'label' => 'Collaborations & Asks', 'icon' => 'bi bi-puzzle'],
            '/life-impact' => ['route' => '/life-impact', 'label' => 'Life Impact & ESG Scores', 'icon' => 'bi bi-heart-pulse'],
            '/circulars' => ['route' => '/circulars', 'label' => 'Official Circulars & Announcements', 'icon' => 'bi bi-megaphone'],
            '/support' => ['route' => '/support', 'label' => 'Help Desk & Support Tickets', 'icon' => 'bi bi-headset'],
            '/circle_chat' => ['route' => '/circle_chat', 'label' => 'Live Circle Chat & Messaging', 'icon' => 'bi bi-chat-dots'],
            '/settings' => ['route' => '/settings', 'label' => 'App Settings & Updates', 'icon' => 'bi bi-gear'],
        ];
    }
}
