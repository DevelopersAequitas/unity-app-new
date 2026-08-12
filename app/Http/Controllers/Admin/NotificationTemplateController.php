<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class NotificationTemplateController extends Controller
{
    private static array $catalog = [
        'welcome_notification' => [
            'key' => 'welcome_notification',
            'name' => 'Welcome Notification',
            'description' => 'Sent to new users upon account approval.',
            'default_title' => 'Welcome to Peers Global!',
            'default_body' => 'Your account is now active. Explore circles and start networking with peers!',
            'default_payload' => ['route' => 'dashboard', 'type' => 'welcome'],
            'dynamic_params' => [
                '{name}' => 'Display name of the user',
            ],
            'icon' => 'bi bi-person-check',
        ],
        'login_otp_notification' => [
            'key' => 'login_otp_notification',
            'name' => 'Login OTP Notification',
            'description' => 'Sent as a push notification alternative for 2FA OTP codes.',
            'default_title' => 'Verification Code',
            'default_body' => 'Your login OTP is {otp}. Valid for 5 minutes.',
            'default_payload' => ['route' => 'otp_verify', 'type' => 'otp'],
            'dynamic_params' => [
                '{otp}' => 'The generated 6-digit OTP code',
            ],
            'icon' => 'bi bi-key',
        ],
        'p2p_meeting_notification' => [
            'key' => 'p2p_meeting_notification',
            'name' => 'P2P Meeting Scheduled',
            'description' => 'Sent to invitee notifying them of a scheduled P2P meeting.',
            'default_title' => 'New P2P Meeting Scheduled',
            'default_body' => 'You have a scheduled peer-to-peer meeting with {actorName} on {meetingDate}.',
            'default_payload' => ['route' => 'meetings', 'type' => 'meeting_scheduled'],
            'dynamic_params' => [
                '{actorName}' => 'Name of the meeting partner',
                '{meetingDate}' => 'Date and time of the meeting',
            ],
            'icon' => 'bi bi-people',
        ],
        'p2p_reschedule_notification' => [
            'key' => 'p2p_reschedule_notification',
            'name' => 'P2P Meeting Rescheduled',
            'description' => 'Sent when a P2P meeting schedule is changed by a partner.',
            'default_title' => 'P2P Meeting Rescheduled',
            'default_body' => '{actorName} has rescheduled the meeting to {meetingDate} at {meetingPlace}.',
            'default_payload' => ['route' => 'meetings', 'type' => 'meeting_rescheduled'],
            'dynamic_params' => [
                '{actorName}' => 'Name of the meeting partner',
                '{meetingDate}' => 'New date and time',
                '{meetingPlace}' => 'New meeting place',
            ],
            'icon' => 'bi bi-calendar-event',
        ],
        'coin_earned_notification' => [
            'key' => 'coin_earned_notification',
            'name' => 'Coins Awarded',
            'description' => 'Sent when a user earns/claims coins successfully.',
            'default_title' => 'Coins Awarded!',
            'default_body' => 'Congratulations! You have received {coins} coins for {activityName}.',
            'default_payload' => ['route' => 'wallet', 'type' => 'coins_received'],
            'dynamic_params' => [
                '{coins}' => 'Number of coins awarded',
                '{activityName}' => 'Name of the rewarded activity',
            ],
            'icon' => 'bi bi-coin',
        ],
        'support_ticket_notification' => [
            'key' => 'support_ticket_notification',
            'name' => 'Support Ticket Update',
            'description' => 'Sent when a support ticket status changes or is resolved.',
            'default_title' => 'Support Ticket Resolved',
            'default_body' => 'Your support ticket {ticketNumber} has been resolved successfully.',
            'default_payload' => ['route' => 'support', 'type' => 'ticket_status'],
            'dynamic_params' => [
                '{ticketNumber}' => 'Ticket reference ID',
            ],
            'icon' => 'bi bi-ticket-perforated',
        ],
        'referral_joined_notification' => [
            'key' => 'referral_joined_notification',
            'name' => 'Referral Joined',
            'description' => 'Sent to referrer when their contact registers on the platform.',
            'default_title' => 'New Referral Joined',
            'default_body' => '{referredName} has joined the community using your referral code!',
            'default_payload' => ['route' => 'referrals', 'type' => 'referral_joined'],
            'dynamic_params' => [
                '{referredName}' => 'Name of the registered user',
            ],
            'icon' => 'bi bi-person-plus',
        ],
        'circle_join_notification' => [
            'key' => 'circle_join_notification',
            'name' => 'Circle Membership Approved',
            'description' => 'Sent when a user joining request for a circle gets accepted.',
            'default_title' => 'Circle Request Approved',
            'default_body' => 'Your request to join the Circle {circleName} has been approved.',
            'default_payload' => ['route' => 'circle_details', 'type' => 'circle_approved'],
            'dynamic_params' => [
                '{circleName}' => 'Name of the circle',
            ],
            'icon' => 'bi bi-award',
        ],
        'story_approved_notification' => [
            'key' => 'story_approved_notification',
            'name' => 'Story Published',
            'description' => 'Sent to user when their submitted article/story is approved.',
            'default_title' => 'Story Published!',
            'default_body' => 'Your story "{storyTitle}" has been approved and published on VyaparJagat.',
            'default_payload' => ['route' => 'stories', 'type' => 'story_approved'],
            'dynamic_params' => [
                '{storyTitle}' => 'Title of the approved story',
            ],
            'icon' => 'bi bi-journal-check',
        ],
        'business_deal_notification' => [
            'key' => 'business_deal_notification',
            'name' => 'Business Deal Logged',
            'description' => 'Sent to a deal partner when a transaction is registered.',
            'default_title' => 'Business Deal Logged',
            'default_body' => '{actorName} has logged a business deal worth ₹{amount} with you.',
            'default_payload' => ['route' => 'business_deals', 'type' => 'deal_logged'],
            'dynamic_params' => [
                '{actorName}' => 'Name of the deal logger',
                '{amount}' => 'The monetary value of the logged deal',
            ],
            'icon' => 'bi bi-briefcase',
        ],
        'circle_membership_expiry_reminder' => [
            'key' => 'circle_membership_expiry_reminder',
            'name' => 'Circle Membership Expiry',
            'description' => 'Sent when a circle membership has expired.',
            'default_title' => 'Circle Membership Expired',
            'default_body' => 'Your membership for circle {circleName} has expired. Please renew to keep access.',
            'default_payload' => ['route' => 'circle_details', 'type' => 'circle_expired'],
            'dynamic_params' => [
                '{circleName}' => 'Name of the circle',
            ],
            'icon' => 'bi bi-calendar-x',
        ],
        'coin_claim_reviewed' => [
            'key' => 'coin_claim_reviewed',
            'name' => 'Coin Claim Reviewed',
            'description' => 'Sent when admin reviews and approves or rejects a coin claim request.',
            'default_title' => 'Coin Claim Reviewed',
            'default_body' => 'Your coin claim for {activityName} has been {status}.',
            'default_payload' => ['route' => 'wallet', 'type' => 'claim_reviewed'],
            'dynamic_params' => [
                '{activityName}' => 'Name of the activity',
                '{status}' => 'Status: approved or rejected',
            ],
            'icon' => 'bi bi-check2-square',
        ],
        'follow_accepted' => [
            'key' => 'follow_accepted',
            'name' => 'Follow Request Accepted',
            'description' => 'Sent when a peer accepts your follow request.',
            'default_title' => 'Follow Request Accepted',
            'default_body' => '{actorName} has accepted your follow request. Start collaborating!',
            'default_payload' => ['route' => 'peer_profile', 'type' => 'follow_accept'],
            'dynamic_params' => [
                '{actorName}' => 'Name of the peer',
            ],
            'icon' => 'bi bi-person-check-fill',
        ],
        'follow_requested' => [
            'key' => 'follow_requested',
            'name' => 'New Follow Request',
            'description' => 'Sent when a peer requests to follow you.',
            'default_title' => 'New Follow Request',
            'default_body' => '{actorName} wants to follow you on Peers Global.',
            'default_payload' => ['route' => 'pending_requests', 'type' => 'follow_request'],
            'dynamic_params' => [
                '{actorName}' => 'Name of the requesting peer',
            ],
            'icon' => 'bi bi-person-plus-fill',
        ],
        'membership_expiry_reminder' => [
            'key' => 'membership_expiry_reminder',
            'name' => 'Membership Expired',
            'description' => 'Sent on the day of membership expiration.',
            'default_title' => 'Membership Expired',
            'default_body' => 'Your Peers Global membership has expired. Renew today to continue networking.',
            'default_payload' => ['route' => 'membership', 'type' => 'expired'],
            'dynamic_params' => [],
            'icon' => 'bi bi-exclamation-octagon',
        ],
        'unfollowed' => [
            'key' => 'unfollowed',
            'name' => 'Unfollowed Notification',
            'description' => 'Sent when a user unfollows you.',
            'default_title' => 'Connection Removed',
            'default_body' => '{actorName} has unfollowed you.',
            'default_payload' => ['route' => 'peer_profile', 'type' => 'unfollowed'],
            'dynamic_params' => [
                '{actorName}' => 'Name of the peer',
            ],
            'icon' => 'bi bi-person-dash-fill',
        ],
        'upcoming_membership_expiry_reminder' => [
            'key' => 'upcoming_membership_expiry_reminder',
            'name' => 'Upcoming Membership Expiry',
            'description' => 'Sent as a warning reminder before membership expires.',
            'default_title' => 'Renew Your Membership',
            'default_body' => 'Your Peers Global membership is expiring in {days} days. Renew now to avoid interruption.',
            'default_payload' => ['route' => 'membership', 'type' => 'upcoming_expiry'],
            'dynamic_params' => [
                '{days}' => 'Number of days remaining',
            ],
            'icon' => 'bi bi-calendar-range',
        ],
        'new_offer_added' => [
            'key' => 'new_offer_added',
            'name' => 'New Offer Added',
            'description' => 'Sent when a brand partner posts a new discount or offer.',
            'default_title' => 'New Offer Alert!',
            'default_body' => '{partnerName} added a new offer: "{offerTitle}". Check it out!',
            'default_payload' => ['route' => 'offers', 'type' => 'new_offer'],
            'dynamic_params' => [
                '{partnerName}' => 'Name of the brand partner',
                '{offerTitle}' => 'Title of the offer',
            ],
            'icon' => 'bi bi-tags',
        ],
        'new_partner_joined' => [
            'key' => 'new_partner_joined',
            'name' => 'New Partner Joined',
            'description' => 'Sent when a new brand partner registers on the platform.',
            'default_title' => 'New Brand Partner',
            'default_body' => 'Please welcome our new brand partner: {partnerName}!',
            'default_payload' => ['route' => 'partners', 'type' => 'new_partner'],
            'dynamic_params' => [
                '{partnerName}' => 'Name of the brand partner',
            ],
            'icon' => 'bi bi-handshake',
        ],
        'offer_expiry_reminder' => [
            'key' => 'offer_expiry_reminder',
            'name' => 'Offer Expiring Soon',
            'description' => 'Sent as a reminder before a claimed offer expires.',
            'default_title' => 'Claimed Offer Expiring',
            'default_body' => 'Your claimed offer "{offerTitle}" is expiring soon. Redeem it now!',
            'default_payload' => ['route' => 'my_offers', 'type' => 'offer_expiring'],
            'dynamic_params' => [
                '{offerTitle}' => 'Title of the offer',
            ],
            'icon' => 'bi bi-clock-history',
        ],
        'daily_engagement_reminder' => [
            'key' => 'daily_engagement_reminder',
            'name' => 'Daily Engagement Reminder',
            'description' => 'Daily reminder for user engagement on the platform.',
            'default_title' => 'Connect with Peers Today',
            'default_body' => 'Check out new active circles and messages waiting for you on Peers Global.',
            'default_payload' => ['route' => 'dashboard', 'type' => 'daily_reminder'],
            'dynamic_params' => [],
            'icon' => 'bi bi-lightning-charge',
        ],
        'impact_reviewed' => [
            'key' => 'impact_reviewed',
            'name' => 'Impact Submission Reviewed',
            'description' => 'Sent when a user\'s life impact submission is reviewed by admin.',
            'default_title' => 'Impact Reviewed',
            'default_body' => 'Your impact action "{action}" has been {status}.',
            'default_payload' => ['route' => 'life_impact', 'type' => 'impact_reviewed'],
            'dynamic_params' => [
                '{action}' => 'Summary of impact action',
                '{status}' => 'Status: approved or rejected',
            ],
            'icon' => 'bi bi-heart-pulse-fill',
        ],
        'collaboration_created' => [
            'key' => 'collaboration_created',
            'name' => 'New Collaboration Opportunity',
            'description' => 'Sent to peers when a new collaboration post is created.',
            'default_title' => 'New Collaboration Opportunity',
            'default_body' => '{creatorName} posted a new collaboration: {title}',
            'default_payload' => ['route' => 'collaboration_details', 'type' => 'collaboration_created'],
            'dynamic_params' => [
                '{creatorName}' => 'Name of the creator user',
                '{title}' => 'Title of the collaboration opportunity',
            ],
            'icon' => 'bi bi-lightbulb',
        ],
        'my_collaboration_created' => [
            'key' => 'my_collaboration_created',
            'name' => 'Collaboration Posted',
            'description' => 'Sent to user confirming their collaboration is live.',
            'default_title' => 'Collaboration Posted',
            'default_body' => 'Your collaboration \'{title}\' has been posted successfully.',
            'default_payload' => ['route' => 'my_collaborations', 'type' => 'my_collaboration_created'],
            'dynamic_params' => [
                '{title}' => 'Title of the collaboration opportunity',
            ],
            'icon' => 'bi bi-file-post',
        ],
        'collaboration_completed' => [
            'key' => 'collaboration_completed',
            'name' => 'Collaboration Completed',
            'description' => 'Sent when a collaboration involving the user is marked complete.',
            'default_title' => 'Collaboration Completed',
            'default_body' => 'A collaboration you are involved in has been marked as completed.',
            'default_payload' => ['route' => 'collaboration_details', 'type' => 'collaboration_completed'],
            'dynamic_params' => [],
            'icon' => 'bi bi-check-circle',
        ],
        'circular_notification' => [
            'key' => 'circular_notification',
            'name' => 'New Circular Available',
            'description' => 'Sent when a new circular is published.',
            'default_title' => 'New Circular: {circularTitle}',
            'default_body' => '{circularSummary}',
            'default_payload' => ['route' => 'circulars', 'type' => 'circular'],
            'dynamic_params' => [
                '{circularTitle}' => 'Title of the circular',
                '{circularSummary}' => 'Brief summary of the circular contents',
            ],
            'icon' => 'bi bi-info-square',
        ],
        'connection_request_pending_reminder' => [
            'key' => 'connection_request_pending_reminder',
            'name' => 'Pending Connection Requests',
            'description' => 'Sent as a weekly reminder for pending peer connections.',
            'default_title' => 'Pending Connection Requests',
            'default_body' => 'You have {pendingCount} pending connection requests on Peers Global.',
            'default_payload' => ['route' => 'pending_requests', 'type' => 'connection_request_pending_reminder'],
            'dynamic_params' => [
                '{pendingCount}' => 'Count of pending connection requests',
            ],
            'icon' => 'bi bi-clock',
        ],
        'chat_message_notification' => [
            'key' => 'chat_message_notification',
            'name' => 'New Chat Message',
            'description' => 'Sent when a peer sends you a chat message.',
            'default_title' => 'New Message',
            'default_body' => '{senderName} sent you a message.',
            'default_payload' => ['route' => 'chat_room', 'type' => 'chat_message'],
            'dynamic_params' => [
                '{senderName}' => 'Name of the sender',
            ],
            'icon' => 'bi bi-chat-left-dots',
        ],
    ];

    /**
     * Display listing of all notifications.
     */
    public function index(): View
    {
        $templates = [];

        foreach (self::$catalog as $key => $tpl) {
            $dbRecord = NotificationTemplate::where('template_key', $key)->first();
            if (! $dbRecord) {
                // Pre-populate template in database if not present
                $dbRecord = NotificationTemplate::create([
                    'template_key' => $key,
                    'name' => $tpl['name'],
                    'title_template' => $tpl['default_title'],
                    'body_template' => $tpl['default_body'],
                    'default_payload' => $tpl['default_payload'],
                    'dynamic_params' => $tpl['dynamic_params'],
                ]);
            }

            $templates[] = array_merge($tpl, [
                'db_record' => $dbRecord,
            ]);
        }

        return view('admin.notification_templates.index', compact('templates'));
    }

    /**
     * Show edit form for notification template.
     */
    public function edit(string $key): View|RedirectResponse
    {
        if (! isset(self::$catalog[$key])) {
            return redirect()->route('admin.notification-templates.index')->with('error', 'Notification template not found.');
        }

        $template = self::$catalog[$key];
        $dbTemplate = NotificationTemplate::where('template_key', $key)->first();

        if (! $dbTemplate) {
            $dbTemplate = NotificationTemplate::create([
                'template_key' => $key,
                'name' => $template['name'],
                'title_template' => $template['default_title'],
                'body_template' => $template['default_body'],
                'default_payload' => $template['default_payload'],
                'dynamic_params' => $template['dynamic_params'],
            ]);
        }

        return view('admin.notification_templates.edit', compact('template', 'dbTemplate'));
    }

    /**
     * Update notification template.
     */
    public function update(Request $request, string $key): RedirectResponse
    {
        if (! isset(self::$catalog[$key])) {
            return redirect()->route('admin.notification-templates.index')->with('error', 'Template not found.');
        }

        $request->validate([
            'title_template' => 'required|string|max:255',
            'body_template' => 'required|string',
        ]);

        try {
            NotificationTemplate::where('template_key', $key)->update([
                'title_template' => $request->input('title_template'),
                'body_template' => $request->input('body_template'),
            ]);

            Log::info('notification_template.updated', [
                'key' => $key,
                'updated_by' => auth()->id(),
            ]);

            return redirect()->route('admin.notification-templates.edit', $key)->with('success', 'Notification template updated successfully.');
        } catch (\Throwable $e) {
            Log::error('notification_template.update_failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to update template: '.$e->getMessage());
        }
    }

    /**
     * Preview notification in mobile mockup.
     */
    public function preview(string $key)
    {
        if (! isset(self::$catalog[$key])) {
            abort(404);
        }

        $dbTemplate = NotificationTemplate::where('template_key', $key)->first();
        $title = $dbTemplate ? $dbTemplate->title_template : self::$catalog[$key]['default_title'];
        $body = $dbTemplate ? $dbTemplate->body_template : self::$catalog[$key]['default_body'];

        // Replace dynamic parameters with mock values
        $mockValues = [
            '{name}' => 'John Doe',
            '{otp}' => '692018',
            '{actorName}' => 'John Doe',
            '{meetingDate}' => '2026-08-15 14:30',
            '{meetingPlace}' => 'Conference Room B / Zoom',
            '{coins}' => '150',
            '{activityName}' => 'Business Referral Submission',
            '{ticketNumber}' => 'SUP-2026-904',
            '{referredName}' => 'Jane Smith',
            '{circleName}' => 'Fintech Leaders Circle',
            '{storyTitle}' => 'How I Scaled My Startup',
            '{amount}' => '5,00,000',
            '{creatorName}' => 'John Doe',
            '{title}' => 'Looking for Laravel Developer partner',
            '{circularTitle}' => 'Independence Day Holiday Announcement',
            '{circularSummary}' => 'Please note that the office will remain closed on August 15th.',
            '{pendingCount}' => '5',
            '{senderName}' => 'John Doe',
            '{partnerName}' => 'Greenpreneur Network',
            '{offerTitle}' => '20% off on Green Consultations',
            '{days}' => '7',
            '{action}' => 'Planting 100 trees in urban area',
            '{status}' => 'approved',
        ];

        $renderedTitle = strtr($title, $mockValues);
        $renderedBody = strtr($body, $mockValues);

        return response()->json([
            'title' => $renderedTitle,
            'body' => $renderedBody,
            'payload' => $dbTemplate ? $dbTemplate->default_payload : self::$catalog[$key]['default_payload'],
        ]);
    }
}
