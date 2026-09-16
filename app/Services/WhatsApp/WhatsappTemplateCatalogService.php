<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Models\WhatsappTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsappTemplateCatalogService
{
    /**
     * Master catalog of all 37 WhatsApp notification templates configured across the platform.
     *
     * @var array<string, array<string, mixed>>
     */
    private const CATALOG = [
        // ==========================================
        // 1. AUTHENTICATION & SECURITY
        // ==========================================
        'otp_verification' => [
            'key' => 'otp_verification',
            'name' => 'WhatsApp OTP Authentication',
            'category' => 'Authentication',
            'category_badge' => 'primary',
            'trigger_type' => 'Authentication',
            'trigger_title' => 'When user requests WhatsApp login/verification OTP',
            'when_sent' => 'Triggered immediately when a user requests a 6-digit verification OTP code on WhatsApp for login, registration, or passwordless auth.',
            'description' => 'Delivers secure one-time authentication passcodes via WhatsApp with 5-minute validity.',
            'recipient' => 'Authenticating user / mobile number owner',
            'icon' => 'bi bi-shield-lock',
            'workflow_steps' => [
                ['title' => 'User Requests OTP', 'desc' => 'Member requests login or phone verification OTP from mobile app or web'],
                ['title' => 'Generate 6-Digit Code', 'desc' => 'System creates secure, expiring OTP record in database'],
                ['title' => 'Template Lookup', 'desc' => 'Resolves otp_verification template configuration from DB'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches payload to FlexiMSG webhook endpoint'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives instant OTP message on WhatsApp'],
            ],
            'variables' => [
                'mobile' => 'Target normalized mobile number with country code',
                'otp' => '6-digit numeric verification code',
                'appName' => 'Application brand name (Peers Global Unity)',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/whatsapp-otp',
            'default_webhook_secret' => '',
        ],

        // ==========================================
        // 2. ONBOARDING & WELCOME
        // ==========================================
        'welcome' => [
            'key' => 'welcome',
            'name' => 'Welcome to Peers Global',
            'category' => 'Onboarding',
            'category_badge' => 'info',
            'trigger_type' => 'Registration',
            'trigger_title' => 'Immediately after successful member registration',
            'when_sent' => 'Sent immediately upon successful user registration and initial account setup.',
            'description' => 'Welcomes new members to the platform with an introductory greeting, personalized digital creative image, and community orientation links.',
            'recipient' => 'Newly registered member',
            'icon' => 'bi bi-person-check',
            'workflow_steps' => [
                ['title' => 'Member Registration', 'desc' => 'New user completes signup form and creates account'],
                ['title' => 'Generate Welcome Creative', 'desc' => 'Background job renders personalized welcome banner asset'],
                ['title' => 'Template Lookup', 'desc' => 'Resolves welcome template configuration from DB'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches payload with image media URL to FlexiMSG'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'New member receives welcome message and customized graphic'],
            ],
            'variables' => [
                'first_name' => 'Member first name or display name',
                'mobile' => 'Member mobile number',
                'creative_url' => 'Public URL of rendered welcome graphic',
                'badge_image_url' => 'Public URL of member digital badge',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/welcome',
            'default_webhook_secret' => '',
        ],
        'wear_the_badge' => [
            'key' => 'wear_the_badge',
            'name' => 'Wear The Badge Recognition Creative',
            'category' => 'Onboarding',
            'category_badge' => 'info',
            'trigger_type' => 'Registration / Profile Action',
            'trigger_title' => 'When personalized member digital badge creative is generated',
            'when_sent' => 'Sent after registration once the customized member digital badge creative is generated and verified.',
            'description' => 'Delivers the official high-resolution "Wear The Badge" peer identity graphic for members to share on social media and WhatsApp status.',
            'recipient' => 'Registered member',
            'icon' => 'bi bi-patch-check',
            'workflow_steps' => [
                ['title' => 'Profile Ready / Badge Triggered', 'desc' => 'Registration completed or profile updated to 100%'],
                ['title' => 'Badge Graphic Rendered', 'desc' => 'WearTheBadgeImageGenerator creates personalized badge image'],
                ['title' => 'Template Lookup', 'desc' => 'Resolves wear_the_badge template configuration from DB'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Sends image header and caption payload to FlexiMSG'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives badge graphic ready for social sharing'],
            ],
            'variables' => [
                'first_name' => 'Member first name',
                'mobile' => 'Member mobile number',
                'header_media_url' => 'High-resolution URL of generated badge creative',
                'badge_image_url' => 'Direct badge graphic URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/wear-the-badge',
            'default_webhook_secret' => '',
        ],
        'engagement_founder' => [
            'key' => 'engagement_founder',
            'name' => 'Founder Engagement & Welcome Message',
            'category' => 'Engagement',
            'category_badge' => 'warning',
            'trigger_type' => 'Time-based (3 hours post-registration)',
            'trigger_title' => '3 hours after member registration',
            'when_sent' => 'Sent 3 hours after new member registration to initiate founder engagement and community connection.',
            'description' => 'Direct personal greeting from the founder introducing the mission, core values, and inviting the member to connect.',
            'recipient' => 'New member (3 hours active)',
            'icon' => 'bi bi-chat-quote',
            'workflow_steps' => [
                ['title' => 'Member Signup Completed', 'desc' => 'Registration event schedules SendFounderEngagementJob with 3h delay'],
                ['title' => '3-Hour Delay Expires', 'desc' => 'Queue worker picks up delayed job for execution'],
                ['title' => 'Template Lookup', 'desc' => 'Resolves engagement_founder template configuration from DB'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches founder greeting payload to FlexiMSG'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives personal message from founder'],
            ],
            'variables' => [
                'first_name' => 'Member first name',
                'mobile' => 'Member mobile number',
                'media_url' => 'Founder video or introduction media URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/founder-engagement',
            'default_webhook_secret' => '',
        ],
        'pgu_pr_media_visibility_v2' => [
            'key' => 'pgu_pr_media_visibility_v2',
            'name' => 'PR & Media Visibility Amplification',
            'category' => 'Engagement',
            'category_badge' => 'warning',
            'trigger_type' => 'Time-based (24 hours post-registration)',
            'trigger_title' => '24 hours after member registration',
            'when_sent' => 'Sent 24 hours after user registration offering national PR, media visibility, and brand spotlight opportunities.',
            'description' => 'Presents exclusive PR distribution and media spotlight opportunities to new members to amplify their venture.',
            'recipient' => 'New member (24 hours active)',
            'icon' => 'bi bi-broadcast',
            'workflow_steps' => [
                ['title' => 'Member Signup Completed', 'desc' => 'Registration event schedules SendPrMediaVisibilityWhatsappJob with 24h delay'],
                ['title' => '24-Hour Delay Expires', 'desc' => 'Queue worker processes delayed job'],
                ['title' => 'Template Lookup', 'desc' => 'Resolves pgu_pr_media_visibility_v2 template from DB'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches PR opportunities payload to FlexiMSG'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives PR visibility announcement on WhatsApp'],
            ],
            'variables' => [
                'first_name' => 'Member first name',
                'mobile' => 'Member mobile number',
                'media_url' => 'PR visibility pamphlet or media brochure URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/pr-media-visibility',
            'default_webhook_secret' => '',
        ],
        'circle_calling_day3' => [
            'key' => 'circle_calling_day3',
            'name' => 'Circle Recommendation — Your Circle is Calling',
            'category' => 'Engagement',
            'category_badge' => 'warning',
            'trigger_type' => 'Time-based (Day 3 post-registration)',
            'trigger_title' => 'Day 3 post-registration or via circle reminder',
            'when_sent' => 'Sent on Day 3 or via automated recommendation reminder suggesting relevant circles matching member industry.',
            'description' => 'Recommends high-affinity circles and active peer networks to members who have not yet joined a primary circle.',
            'recipient' => 'New member without active circle membership',
            'icon' => 'bi bi-diagram-3',
            'workflow_steps' => [
                ['title' => 'Day 3 Check / Recommendation Trigger', 'desc' => 'Scheduler evaluates member circle participation on Day 3'],
                ['title' => 'Match Relevant Circle', 'desc' => 'Algorithm matches best industry or city circle for member'],
                ['title' => 'Template Lookup', 'desc' => 'Resolves circle_calling_day3 template from DB'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches circle recommendation payload to FlexiMSG'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives personalized circle invite on WhatsApp'],
            ],
            'variables' => [
                'first_name' => 'Member first name',
                'phone' => 'Member phone number',
                'circle_name' => 'Recommended circle title',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/circle-calling-day3',
            'default_webhook_secret' => '',
        ],
        'profile_completion_reminder' => [
            'key' => 'profile_completion_reminder',
            'name' => '48-Hour Profile Completion Reminder',
            'category' => 'Onboarding',
            'category_badge' => 'info',
            'trigger_type' => 'Profile Completion (< 70% after 48h)',
            'trigger_title' => '48 hours post-signup if profile < 70%',
            'when_sent' => 'Sent 48 hours after registration if the member\'s profile completion is below 70%.',
            'description' => 'Reminds members to finish filling out their business profile, bio, and industry details to maximize visibility.',
            'recipient' => 'Member with incomplete profile (< 70%)',
            'icon' => 'bi bi-person-lines-fill',
            'workflow_steps' => [
                ['title' => '48-Hour Inactivity Check', 'desc' => 'Scheduler evaluates member profile completion 48h after signup'],
                ['title' => 'Evaluate Threshold', 'desc' => 'Checks if completion percentage is under 70%'],
                ['title' => 'Template Lookup', 'desc' => 'Resolves profile_completion_reminder template from DB'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches profile prompt with completion percentage'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives gentle reminder to complete profile'],
            ],
            'variables' => [
                'first_name' => 'Member first name',
                'mobile' => 'Member mobile number',
                'profile_percent' => 'Current profile completion percentage (e.g. 45)',
                'profile_completion_percentage' => 'Formatted percentage string',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/profile-completion',
            'default_webhook_secret' => '',
        ],

        // ==========================================
        // 3. DAILY HABIT LOOP (30-DAY JOURNEY)
        // ==========================================
        'day_1_complete_profile' => [
            'key' => 'day_1_complete_profile',
            'name' => 'Daily Habit Day 1 — Complete Your Profile',
            'category' => 'Engagement',
            'category_badge' => 'warning',
            'trigger_type' => 'Time-based (Day 1 at 10:00 AM)',
            'trigger_title' => 'Day 1 of 30-day journey at 10:00 AM local time',
            'when_sent' => 'Sent on Day 1 of the Daily Habit Loop journey at 10:00 AM local time to guide profile completion.',
            'description' => 'Kickstarts the 30-day member engagement journey by encouraging profile polish and brand presentation.',
            'recipient' => 'Member on Day 1 of Daily Habit Loop',
            'icon' => 'bi bi-calendar-check',
            'workflow_steps' => [
                ['title' => 'Journey Initiated', 'desc' => 'User registers or becomes eligible for 30-day habit loop'],
                ['title' => 'Schedule Day 1 at 10 AM', 'desc' => 'DailyHabitLoopService sets Day 1 schedule for 10:00 AM local time'],
                ['title' => 'Template Lookup', 'desc' => 'Resolves day_1_complete_profile template from DB'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches Day 1 prompt to FlexiMSG'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives Day 1 action prompt on WhatsApp'],
            ],
            'variables' => [
                'first_name' => 'Member first name',
                'phone' => 'Member phone number',
                'ProfileLink' => 'Deep link to member profile edit screen',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/daily-habit-day1',
            'default_webhook_secret' => '',
        ],
        'business_referrals_day_2' => [
            'key' => 'business_referrals_day_2',
            'name' => 'Daily Habit Day 2 — Business Referrals',
            'category' => 'Engagement',
            'category_badge' => 'warning',
            'trigger_type' => 'Time-based (Day 2 of habit loop)',
            'trigger_title' => 'Day 2 of 30-day journey',
            'when_sent' => 'Sent on Day 2 of the Daily Habit Loop journey to guide member on sending and receiving business referrals.',
            'description' => 'Guides members on how to exchange high-trust business referrals and deals with peers in their network.',
            'recipient' => 'Member on Day 2 of Daily Habit Loop',
            'icon' => 'bi bi-briefcase',
            'workflow_steps' => [
                ['title' => 'Day 1 Completed', 'desc' => 'Scheduler advances member journey to Day 2'],
                ['title' => 'Resolve Day 2 Template', 'desc' => 'DailyHabitLoopService resolves business_referrals_day_2'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches Day 2 prompt with timeline link'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives business referral guidance message'],
            ],
            'variables' => [
                'first_name' => 'Member first name',
                'phone' => 'Member phone number',
                'TimelineLink' => 'Deep link to community timeline and deal flow',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/daily-habit-day2',
            'default_webhook_secret' => '',
        ],
        'day_4_business_referral' => [
            'key' => 'day_4_business_referral',
            'name' => 'Daily Habit Day 4 — Business Referrals & Deals',
            'category' => 'Engagement',
            'category_badge' => 'warning',
            'trigger_type' => 'Time-based (Day 4 of habit loop)',
            'trigger_title' => 'Day 4 of 30-day journey',
            'when_sent' => 'Sent on Day 4 of the Daily Habit Loop journey to follow up on referral activities.',
            'description' => 'Encourages members to log referrals, thank partners, and explore collaborative opportunities.',
            'recipient' => 'Member on Day 4 of Daily Habit Loop',
            'icon' => 'bi bi-arrow-repeat',
            'workflow_steps' => [
                ['title' => 'Day 3 Completed', 'desc' => 'Scheduler advances member journey to Day 4'],
                ['title' => 'Resolve Day 4 Template', 'desc' => 'DailyHabitLoopService resolves day_4_business_referral'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches Day 4 action payload to FlexiMSG'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives Day 4 prompt on WhatsApp'],
            ],
            'variables' => [
                'first_name' => 'Member first name',
                'phone' => 'Member phone number',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/daily-habit-day4',
            'default_webhook_secret' => '',
        ],
        'day_7_introduce_yourself_circle' => [
            'key' => 'day_7_introduce_yourself_circle',
            'name' => 'Daily Habit Day 7 — Introduce Yourself in Circle',
            'category' => 'Engagement',
            'category_badge' => 'warning',
            'trigger_type' => 'Time-based (Day 7 of habit loop)',
            'trigger_title' => 'Day 7 of 30-day journey',
            'when_sent' => 'Sent on Day 7 of the Daily Habit Loop journey prompting member to post introduction in their circle.',
            'description' => 'Prompts members to post a 60-second video or bio introduction in their circle discussion feed.',
            'recipient' => 'Member on Day 7 of Daily Habit Loop',
            'icon' => 'bi bi-chat-left-text',
            'workflow_steps' => [
                ['title' => 'Day 6 Completed', 'desc' => 'Scheduler advances member journey to Day 7'],
                ['title' => 'Resolve Day 7 Template', 'desc' => 'DailyHabitLoopService resolves day_7_introduce_yourself_circle'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches Day 7 introduction prompt to FlexiMSG'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives circle introduction call-to-action'],
            ],
            'variables' => [
                'first_name' => 'Member first name',
                'phone' => 'Member phone number',
                'CircleLink' => 'Deep link to member primary circle',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/daily-habit-day7',
            'default_webhook_secret' => '',
        ],

        // ==========================================
        // 4. EVENTS & REGISTRATIONS
        // ==========================================
        'event_registration' => [
            'key' => 'event_registration',
            'name' => 'Event Registration Confirmation & Ticket',
            'category' => 'Other',
            'category_badge' => 'secondary',
            'trigger_type' => 'Event Registration',
            'trigger_title' => 'Immediately upon event registration',
            'when_sent' => 'Sent immediately when a member or visitor registers for an event with attendance details and QR access code.',
            'description' => 'Delivers event confirmation, date/time schedule, venue location or virtual meeting link, and QR check-in ticket.',
            'recipient' => 'Event attendee (member or visitor)',
            'icon' => 'bi bi-calendar-event',
            'workflow_steps' => [
                ['title' => 'User Registers for Event', 'desc' => 'Attendee completes event registration via app or web'],
                ['title' => 'Generate QR Check-in Code', 'desc' => 'EventRegistrationQrService generates unique attendance QR code'],
                ['title' => 'Template Lookup', 'desc' => 'Resolves event_registration template configuration from DB'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches event ticket and schedule payload to FlexiMSG'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Attendee receives WhatsApp confirmation ticket with QR code'],
            ],
            'variables' => [
                'name' => 'Attendee full name',
                'event_name' => 'Title of the registered event',
                'event_date' => 'Date of the event occurrence',
                'event_time' => 'Start time of the event',
                'venue' => 'Physical location or Online Meeting link',
                'qr_code_url' => 'URL of entry pass QR code image',
                'meeting_link' => 'Direct webinar/meeting URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/event-registration',
            'default_webhook_secret' => '',
        ],

        // ==========================================
        // 5. GROWTH / REFERRAL MILESTONES (TRACK 1)
        // ==========================================
        'milestone_connector' => [
            'key' => 'milestone_connector',
            'name' => 'Growth Milestone — Connector (1 Introduction)',
            'category' => 'Growth / Referral',
            'category_badge' => 'success',
            'trigger_type' => 'Referral Milestone',
            'trigger_title' => 'When member introduces their 1st verified peer',
            'when_sent' => 'Automatically sent when a member achieves their 1st verified peer introduction.',
            'description' => 'Celebrates the first milestone achievement (Connector) with a customized honour badge and congratulatory announcement.',
            'recipient' => 'Introducing member (1 peer introduced)',
            'icon' => 'bi bi-trophy',
            'milestone_count' => 1,
            'milestone_title' => 'Connector',
            'workflow_steps' => [
                ['title' => '1st Peer Joined', 'desc' => 'Invitee completes registration using introducer referral code'],
                ['title' => 'Milestone Threshold Met (1)', 'desc' => 'MilestoneWhatsappNotificationService detects 1 introduction'],
                ['title' => 'Generate Honour Creative', 'desc' => 'IntroducedPeerCreativeGenerator renders personalized Connector award'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches milestone payload and graphic URL to FlexiMSG'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives official Connector honour on WhatsApp'],
            ],
            'variables' => [
                'phone' => 'Member mobile number',
                'name' => 'Member full name',
                'first_name' => 'Member first name',
                'milestone_title' => 'Connector',
                'badge_image_url' => 'Connector milestone badge URL',
                'creative_url' => 'Rendered award graphic URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/85295ba0-fe2c-487f-93ef-ab7bb6124a3d',
            'default_webhook_secret' => '',
        ],
        'pgu_catalyst_3' => [
            'key' => 'pgu_catalyst_3',
            'name' => 'Growth Milestone — Catalyst (3 Introductions)',
            'category' => 'Growth / Referral',
            'category_badge' => 'success',
            'trigger_type' => 'Referral Milestone',
            'trigger_title' => 'When member introduces 3 verified peers',
            'when_sent' => 'Automatically sent when a member achieves 3 verified peer introductions.',
            'description' => 'Celebrates the Catalyst milestone recognition (3 introductions) recognizing steady community growth contribution.',
            'recipient' => 'Introducing member (3 peers introduced)',
            'icon' => 'bi bi-trophy',
            'milestone_count' => 3,
            'milestone_title' => 'Catalyst',
            'workflow_steps' => [
                ['title' => '3rd Peer Joined', 'desc' => '3rd peer registers through introducer referral'],
                ['title' => 'Milestone Threshold Met (3)', 'desc' => 'MilestoneWhatsappNotificationService validates threshold'],
                ['title' => 'Generate Honour Creative', 'desc' => 'Renders personalized Catalyst award graphic'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches payload to FlexiMSG webhook'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives Catalyst award on WhatsApp'],
            ],
            'variables' => [
                'phone' => 'Member mobile number',
                'name' => 'Member full name',
                'first_name' => 'Member first name',
                'milestone_title' => 'Catalyst',
                'badge_image_url' => 'Catalyst milestone badge URL',
                'creative_url' => 'Rendered award graphic URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/5176b75b-a639-4dc0-891e-0d0e503dcc31',
            'default_webhook_secret' => '',
        ],
        'milestone_influencer_5' => [
            'key' => 'milestone_influencer_5',
            'name' => 'Growth Milestone — Influencer (5 Introductions)',
            'category' => 'Growth / Referral',
            'category_badge' => 'success',
            'trigger_type' => 'Referral Milestone',
            'trigger_title' => 'When member introduces 5 verified peers',
            'when_sent' => 'Automatically sent when a member achieves 5 verified peer introductions.',
            'description' => 'Celebrates achieving the Influencer honour for bringing 5 active founders into the community.',
            'recipient' => 'Introducing member (5 peers introduced)',
            'icon' => 'bi bi-trophy',
            'milestone_count' => 5,
            'milestone_title' => 'Influencer',
            'workflow_steps' => [
                ['title' => '5th Peer Joined', 'desc' => 'Introduced count reaches 5 verified peers'],
                ['title' => 'Milestone Evaluation', 'desc' => 'MilestoneWhatsappNotificationService confirms milestone'],
                ['title' => 'Generate Graphic', 'desc' => 'Generates Influencer honour badge creative'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches payload to FlexiMSG'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives Influencer award on WhatsApp'],
            ],
            'variables' => [
                'phone' => 'Member mobile number',
                'name' => 'Member full name',
                'first_name' => 'Member first name',
                'milestone_title' => 'Influencer',
                'badge_image_url' => 'Influencer badge URL',
                'creative_url' => 'Rendered award graphic URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/milestone-influencer-5',
            'default_webhook_secret' => '',
        ],
        'milestone_ambassador_10' => [
            'key' => 'milestone_ambassador_10',
            'name' => 'Growth Milestone — Ambassador (10 Introductions)',
            'category' => 'Growth / Referral',
            'category_badge' => 'success',
            'trigger_type' => 'Referral Milestone',
            'trigger_title' => 'When member introduces 10 verified peers',
            'when_sent' => 'Automatically sent when a member achieves 10 verified peer introductions.',
            'description' => 'Celebrates the Ambassador milestone honour for bringing 10 entrepreneurs into Peers Global.',
            'recipient' => 'Introducing member (10 peers introduced)',
            'icon' => 'bi bi-trophy',
            'milestone_count' => 10,
            'milestone_title' => 'Ambassador',
            'workflow_steps' => [
                ['title' => '10th Peer Joined', 'desc' => 'Introduced count reaches 10 verified peers'],
                ['title' => 'Milestone Evaluation', 'desc' => 'MilestoneWhatsappNotificationService confirms milestone'],
                ['title' => 'Generate Graphic', 'desc' => 'Generates Ambassador honour badge creative'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches payload to FlexiMSG'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives Ambassador award on WhatsApp'],
            ],
            'variables' => [
                'phone' => 'Member mobile number',
                'name' => 'Member full name',
                'first_name' => 'Member first name',
                'milestone_title' => 'Ambassador',
                'badge_image_url' => 'Ambassador badge URL',
                'creative_url' => 'Rendered award graphic URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/milestone-ambassador-10',
            'default_webhook_secret' => '',
        ],
        'milestone_rainmaker_20' => [
            'key' => 'milestone_rainmaker_20',
            'name' => 'Growth Milestone — Rainmaker (20 Introductions)',
            'category' => 'Growth / Referral',
            'category_badge' => 'success',
            'trigger_type' => 'Referral Milestone',
            'trigger_title' => 'When member introduces 20 verified peers',
            'when_sent' => 'Automatically sent when a member achieves 20 verified peer introductions.',
            'description' => 'Honours achieving Rainmaker status for catalyzing 20 founder introductions.',
            'recipient' => 'Introducing member (20 peers introduced)',
            'icon' => 'bi bi-trophy',
            'milestone_count' => 20,
            'milestone_title' => 'Rainmaker',
            'workflow_steps' => [
                ['title' => '20th Peer Joined', 'desc' => 'Introduced count reaches 20 verified peers'],
                ['title' => 'Milestone Evaluation', 'desc' => 'Milestone service verifies threshold and idempotency'],
                ['title' => 'Generate Graphic', 'desc' => 'Generates Rainmaker honour badge creative'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches payload to FlexiMSG'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives Rainmaker award on WhatsApp'],
            ],
            'variables' => [
                'phone' => 'Member mobile number',
                'name' => 'Member full name',
                'first_name' => 'Member first name',
                'milestone_title' => 'Rainmaker',
                'badge_image_url' => 'Rainmaker badge URL',
                'creative_url' => 'Rendered award graphic URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/milestone-rainmaker-20',
            'default_webhook_secret' => '',
        ],
        'milestone_trailblazer_35' => [
            'key' => 'milestone_trailblazer_35',
            'name' => 'Growth Milestone — Trailblazer (35 Introductions)',
            'category' => 'Growth / Referral',
            'category_badge' => 'success',
            'trigger_type' => 'Referral Milestone',
            'trigger_title' => 'When member introduces 35 verified peers',
            'when_sent' => 'Automatically sent when a member achieves 35 verified peer introductions.',
            'description' => 'Honours the Trailblazer milestone recognition for expanding the ecosystem with 35 peers.',
            'recipient' => 'Introducing member (35 peers introduced)',
            'icon' => 'bi bi-trophy',
            'milestone_count' => 35,
            'milestone_title' => 'Trailblazer',
            'workflow_steps' => [
                ['title' => '35th Peer Joined', 'desc' => 'Introduced count reaches 35 verified peers'],
                ['title' => 'Milestone Evaluation', 'desc' => 'Milestone service verifies threshold'],
                ['title' => 'Generate Graphic', 'desc' => 'Generates Trailblazer honour badge creative'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches payload to FlexiMSG'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives Trailblazer award on WhatsApp'],
            ],
            'variables' => [
                'phone' => 'Member mobile number',
                'name' => 'Member full name',
                'first_name' => 'Member first name',
                'milestone_title' => 'Trailblazer',
                'badge_image_url' => 'Trailblazer badge URL',
                'creative_url' => 'Rendered award graphic URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/milestone-trailblazer-35',
            'default_webhook_secret' => '',
        ],
        'milestone_vanguard_50' => [
            'key' => 'milestone_vanguard_50',
            'name' => 'Growth Milestone — Vanguard (50 Introductions)',
            'category' => 'Growth / Referral',
            'category_badge' => 'success',
            'trigger_type' => 'Referral Milestone',
            'trigger_title' => 'When member introduces 50 verified peers',
            'when_sent' => 'Automatically sent when a member achieves 50 verified peer introductions.',
            'description' => 'Celebrates reaching Vanguard status with 50 founders onboarded to the movement.',
            'recipient' => 'Introducing member (50 peers introduced)',
            'icon' => 'bi bi-trophy',
            'milestone_count' => 50,
            'milestone_title' => 'Vanguard',
            'workflow_steps' => [
                ['title' => '50th Peer Joined', 'desc' => 'Introduced count reaches 50 verified peers'],
                ['title' => 'Milestone Evaluation', 'desc' => 'Milestone service verifies threshold'],
                ['title' => 'Generate Graphic', 'desc' => 'Generates Vanguard honour badge creative'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches payload to FlexiMSG'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives Vanguard award on WhatsApp'],
            ],
            'variables' => [
                'phone' => 'Member mobile number',
                'name' => 'Member full name',
                'first_name' => 'Member first name',
                'milestone_title' => 'Vanguard',
                'badge_image_url' => 'Vanguard badge URL',
                'creative_url' => 'Rendered award graphic URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/milestone-vanguard-50',
            'default_webhook_secret' => '',
        ],
        'milestone_luminary_75' => [
            'key' => 'milestone_luminary_75',
            'name' => 'Growth Milestone — Luminary (75 Introductions)',
            'category' => 'Growth / Referral',
            'category_badge' => 'success',
            'trigger_type' => 'Referral Milestone',
            'trigger_title' => 'When member introduces 75 verified peers',
            'when_sent' => 'Automatically sent when a member achieves 75 verified peer introductions.',
            'description' => 'Honours achieving Luminary status for guiding 75 entrepreneurs into the community.',
            'recipient' => 'Introducing member (75 peers introduced)',
            'icon' => 'bi bi-trophy',
            'milestone_count' => 75,
            'milestone_title' => 'Luminary',
            'workflow_steps' => [
                ['title' => '75th Peer Joined', 'desc' => 'Introduced count reaches 75 verified peers'],
                ['title' => 'Milestone Evaluation', 'desc' => 'Milestone service verifies threshold'],
                ['title' => 'Generate Graphic', 'desc' => 'Generates Luminary honour badge creative'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches payload to FlexiMSG'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives Luminary award on WhatsApp'],
            ],
            'variables' => [
                'phone' => 'Member mobile number',
                'name' => 'Member full name',
                'first_name' => 'Member first name',
                'milestone_title' => 'Luminary',
                'badge_image_url' => 'Luminary badge URL',
                'creative_url' => 'Rendered award graphic URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/milestone-luminary-75',
            'default_webhook_secret' => '',
        ],
        'milestone_movement_maker_100' => [
            'key' => 'milestone_movement_maker_100',
            'name' => 'Growth Milestone — Movement Maker (100 Introductions)',
            'category' => 'Growth / Referral',
            'category_badge' => 'success',
            'trigger_type' => 'Referral Milestone',
            'trigger_title' => 'When member introduces 100 verified peers',
            'when_sent' => 'Automatically sent when a member achieves 100 verified peer introductions.',
            'description' => 'Celebrates the Movement Maker milestone honour for introducing 100 entrepreneurs.',
            'recipient' => 'Introducing member (100 peers introduced)',
            'icon' => 'bi bi-trophy',
            'milestone_count' => 100,
            'milestone_title' => 'Movement Maker',
            'workflow_steps' => [
                ['title' => '100th Peer Joined', 'desc' => 'Introduced count reaches 100 verified peers'],
                ['title' => 'Milestone Evaluation', 'desc' => 'Milestone service verifies threshold'],
                ['title' => 'Generate Graphic', 'desc' => 'Generates Movement Maker honour badge creative'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches payload to FlexiMSG'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives Movement Maker award on WhatsApp'],
            ],
            'variables' => [
                'phone' => 'Member mobile number',
                'name' => 'Member full name',
                'first_name' => 'Member first name',
                'milestone_title' => 'Movement Maker',
                'badge_image_url' => 'Movement Maker badge URL',
                'creative_url' => 'Rendered award graphic URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/milestone-movement-maker-100',
            'default_webhook_secret' => '',
        ],
        'milestone_community_titan_150' => [
            'key' => 'milestone_community_titan_150',
            'name' => 'Growth Milestone — Community Titan (150 Introductions)',
            'category' => 'Growth / Referral',
            'category_badge' => 'success',
            'trigger_type' => 'Referral Milestone',
            'trigger_title' => 'When member introduces 150 verified peers',
            'when_sent' => 'Automatically sent when a member achieves 150 verified peer introductions.',
            'description' => 'Celebrates the Community Titan milestone honour for 150 introductions.',
            'recipient' => 'Introducing member (150 peers introduced)',
            'icon' => 'bi bi-trophy',
            'milestone_count' => 150,
            'milestone_title' => 'Community Titan',
            'workflow_steps' => [
                ['title' => '150th Peer Joined', 'desc' => 'Introduced count reaches 150 verified peers'],
                ['title' => 'Milestone Evaluation', 'desc' => 'Milestone service verifies threshold'],
                ['title' => 'Generate Graphic', 'desc' => 'Generates Community Titan honour badge creative'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches payload to FlexiMSG'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives Community Titan award on WhatsApp'],
            ],
            'variables' => [
                'phone' => 'Member mobile number',
                'name' => 'Member full name',
                'first_name' => 'Member first name',
                'milestone_title' => 'Community Titan',
                'badge_image_url' => 'Community Titan badge URL',
                'creative_url' => 'Rendered award graphic URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/milestone-community-titan-150',
            'default_webhook_secret' => '',
        ],
        'milestone_network_architect_250' => [
            'key' => 'milestone_network_architect_250',
            'name' => 'Growth Milestone — Network Architect (250 Introductions)',
            'category' => 'Growth / Referral',
            'category_badge' => 'success',
            'trigger_type' => 'Referral Milestone',
            'trigger_title' => 'When member introduces 250 verified peers',
            'when_sent' => 'Automatically sent when a member achieves 250 verified peer introductions.',
            'description' => 'Celebrates the Network Architect milestone honour for 250 peer introductions.',
            'recipient' => 'Introducing member (250 peers introduced)',
            'icon' => 'bi bi-trophy',
            'milestone_count' => 250,
            'milestone_title' => 'Network Architect',
            'workflow_steps' => [
                ['title' => '250th Peer Joined', 'desc' => 'Introduced count reaches 250 verified peers'],
                ['title' => 'Milestone Evaluation', 'desc' => 'Milestone service verifies threshold'],
                ['title' => 'Generate Graphic', 'desc' => 'Generates Network Architect honour badge creative'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches payload to FlexiMSG'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives Network Architect award on WhatsApp'],
            ],
            'variables' => [
                'phone' => 'Member mobile number',
                'name' => 'Member full name',
                'first_name' => 'Member first name',
                'milestone_title' => 'Network Architect',
                'badge_image_url' => 'Network Architect badge URL',
                'creative_url' => 'Rendered award graphic URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/milestone-network-architect-250',
            'default_webhook_secret' => '',
        ],
        'milestone_global_icon_500' => [
            'key' => 'milestone_global_icon_500',
            'name' => 'Growth Milestone — Global Icon (500 Introductions)',
            'category' => 'Growth / Referral',
            'category_badge' => 'success',
            'trigger_type' => 'Referral Milestone',
            'trigger_title' => 'When member introduces 500 verified peers',
            'when_sent' => 'Automatically sent when a member achieves 500 verified peer introductions.',
            'description' => 'Pinnacle Track 1 milestone recognizing Global Icon status for 500 member introductions.',
            'recipient' => 'Introducing member (500 peers introduced)',
            'icon' => 'bi bi-trophy',
            'milestone_count' => 500,
            'milestone_title' => 'Global Icon',
            'workflow_steps' => [
                ['title' => '500th Peer Joined', 'desc' => 'Introduced count reaches 500 verified peers'],
                ['title' => 'Milestone Evaluation', 'desc' => 'Milestone service verifies threshold'],
                ['title' => 'Generate Graphic', 'desc' => 'Generates Global Icon honour badge creative'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches payload to FlexiMSG'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives Global Icon award on WhatsApp'],
            ],
            'variables' => [
                'phone' => 'Member mobile number',
                'name' => 'Member full name',
                'first_name' => 'Member first name',
                'milestone_title' => 'Global Icon',
                'badge_image_url' => 'Global Icon badge URL',
                'creative_url' => 'Rendered award graphic URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/milestone-global-icon-500',
            'default_webhook_secret' => '',
        ],
        'milestone_badge_whatsapp' => [
            'key' => 'milestone_badge_whatsapp',
            'name' => 'Milestone Badge Notification (Legacy / Fallback)',
            'category' => 'Growth / Referral',
            'category_badge' => 'success',
            'trigger_type' => 'Referral Milestone',
            'trigger_title' => 'When any referral milestone is achieved without specific key',
            'when_sent' => 'Fallback template used for milestone badge awards when specific threshold key is unconfigured.',
            'description' => 'Serves as an adaptive fallback template for milestone awards across all introduction tiers.',
            'recipient' => 'Introducing member',
            'icon' => 'bi bi-award',
            'workflow_steps' => [
                ['title' => 'Milestone Reached', 'desc' => 'Member reaches an introduction milestone'],
                ['title' => 'Fallback Resolution', 'desc' => 'System falls back to milestone_badge_whatsapp if specific key missing'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches generic milestone award payload to FlexiMSG'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives milestone award on WhatsApp'],
            ],
            'variables' => [
                'phone' => 'Member mobile number',
                'name' => 'Member full name',
                'first_name' => 'Member first name',
                'milestone_title' => 'Milestone award title',
                'badge_image_url' => 'Badge image URL',
                'creative_url' => 'Award creative graphic URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/milestone-badge-fallback',
            'default_webhook_secret' => '',
        ],

        // ==========================================
        // 6. IMPACT RECOGNITION (LIFE IMPACT TRACK)
        // ==========================================
        'impact_creator_25' => [
            'key' => 'impact_creator_25',
            'name' => 'Impact Recognition — Impact Creator (25 Impacts)',
            'category' => 'Impact Recognition',
            'category_badge' => 'danger',
            'trigger_type' => 'Impact Milestone',
            'trigger_title' => 'When member reaches 25 approved lifetime impacts',
            'when_sent' => 'Automatically sent when a member reaches 25 approved lifetime impacts and qualifies for Impact Creator recognition.',
            'description' => 'Honours achieving the Impact Creator level (25 approved lifetime impacts) supporting the 1 Million Entrepreneurs mission.',
            'recipient' => 'Member who achieved the milestone',
            'icon' => 'bi bi-heart-pulse',
            'impact_count' => 25,
            'impact_title' => 'Impact Creator',
            'workflow_steps' => [
                ['title' => '25 Approved Impacts', 'desc' => 'Member submits verified impacts reaching 25 approved total'],
                ['title' => 'Impact Creator Recognition', 'desc' => 'ImpactMilestoneWhatsappNotificationService qualifies member'],
                ['title' => 'Generate Graphic', 'desc' => 'LifeImpactCreativeGenerator creates personalized recognition graphic'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches impact payload to FlexiMSG webhook endpoint'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives Impact Creator recognition on WhatsApp'],
            ],
            'variables' => [
                'phone' => 'Member mobile number',
                'name' => 'Member full name',
                'first_name' => 'Member first name',
                'impact_count' => '25',
                'creative_url' => 'Generated Life Impact creative graphic URL',
                'badge_image_url' => 'Impact Creator badge URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/impact-creator-25',
            'default_webhook_secret' => '',
        ],
        'change_maker_50' => [
            'key' => 'change_maker_50',
            'name' => 'Impact Recognition — Change Maker (50 Impacts)',
            'category' => 'Impact Recognition',
            'category_badge' => 'danger',
            'trigger_type' => 'Impact Milestone',
            'trigger_title' => 'When member reaches 50 approved lifetime impacts',
            'when_sent' => 'Automatically sent when a member reaches 50 approved lifetime impacts and qualifies for Change Maker recognition.',
            'description' => 'Honours achieving the Change Maker level (50 approved lifetime impacts) supporting the 1 Million Entrepreneurs mission.',
            'recipient' => 'Member who achieved the milestone',
            'icon' => 'bi bi-heart-pulse',
            'impact_count' => 50,
            'impact_title' => 'Change Maker',
            'workflow_steps' => [
                ['title' => '50 Approved Impacts', 'desc' => 'Member lifetime verified impacts reach 50'],
                ['title' => 'Change Maker Recognition', 'desc' => 'Impact milestone service confirms qualification'],
                ['title' => 'Generate Graphic', 'desc' => 'Renders Change Maker recognition creative graphic'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches payload to FlexiMSG webhook endpoint'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives Change Maker recognition on WhatsApp'],
            ],
            'variables' => [
                'phone' => 'Member mobile number',
                'name' => 'Member full name',
                'first_name' => 'Member first name',
                'impact_count' => '50',
                'creative_url' => 'Generated Life Impact creative graphic URL',
                'badge_image_url' => 'Change Maker badge URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/change-maker-50',
            'default_webhook_secret' => '',
        ],
        'life_changer_100' => [
            'key' => 'life_changer_100',
            'name' => 'Impact Recognition — Life Changer (100 Impacts)',
            'category' => 'Impact Recognition',
            'category_badge' => 'danger',
            'trigger_type' => 'Impact Milestone',
            'trigger_title' => 'When member reaches 100 approved lifetime impacts',
            'when_sent' => 'Automatically sent when a member reaches 100 approved lifetime impacts and qualifies for Life Changer recognition.',
            'description' => 'Honours achieving the Life Changer level (100 approved lifetime impacts) creating transformative ripples.',
            'recipient' => 'Member who achieved the milestone',
            'icon' => 'bi bi-heart-pulse',
            'impact_count' => 100,
            'impact_title' => 'Life Changer',
            'workflow_steps' => [
                ['title' => '100 Approved Impacts', 'desc' => 'Member lifetime verified impacts reach 100'],
                ['title' => 'Life Changer Recognition', 'desc' => 'Impact milestone service confirms qualification'],
                ['title' => 'Generate Graphic', 'desc' => 'Renders Life Changer recognition creative graphic'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches payload to FlexiMSG webhook endpoint'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives Life Changer recognition on WhatsApp'],
            ],
            'variables' => [
                'phone' => 'Member mobile number',
                'name' => 'Member full name',
                'first_name' => 'Member first name',
                'impact_count' => '100',
                'creative_url' => 'Generated Life Impact creative graphic URL',
                'badge_image_url' => 'Life Changer badge URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/life-changer-100',
            'default_webhook_secret' => '',
        ],
        'impact_builder_250' => [
            'key' => 'impact_builder_250',
            'name' => 'Impact Recognition — Impact Builder (250 Impacts)',
            'category' => 'Impact Recognition',
            'category_badge' => 'danger',
            'trigger_type' => 'Impact Milestone',
            'trigger_title' => 'When member reaches 250 approved lifetime impacts',
            'when_sent' => 'Automatically sent when a member reaches 250 approved lifetime impacts and qualifies for Impact Builder recognition.',
            'description' => 'Honours achieving the Impact Builder level (250 approved lifetime impacts).',
            'recipient' => 'Member who achieved the milestone',
            'icon' => 'bi bi-heart-pulse',
            'impact_count' => 250,
            'impact_title' => 'Impact Builder',
            'workflow_steps' => [
                ['title' => '250 Approved Impacts', 'desc' => 'Member lifetime verified impacts reach 250'],
                ['title' => 'Impact Builder Recognition', 'desc' => 'Impact milestone service confirms qualification'],
                ['title' => 'Generate Graphic', 'desc' => 'Renders Impact Builder recognition creative graphic'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches payload to FlexiMSG webhook endpoint'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives Impact Builder recognition on WhatsApp'],
            ],
            'variables' => [
                'phone' => 'Member mobile number',
                'name' => 'Member full name',
                'first_name' => 'Member first name',
                'impact_count' => '250',
                'creative_url' => 'Generated Life Impact creative graphic URL',
                'badge_image_url' => 'Impact Builder badge URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/impact-builder-250',
            'default_webhook_secret' => '',
        ],
        'ecosystem_builder_500' => [
            'key' => 'ecosystem_builder_500',
            'name' => 'Impact Recognition — Ecosystem Builder (500 Impacts)',
            'category' => 'Impact Recognition',
            'category_badge' => 'danger',
            'trigger_type' => 'Impact Milestone',
            'trigger_title' => 'When member reaches 500 approved lifetime impacts',
            'when_sent' => 'Automatically sent when a member reaches 500 approved lifetime impacts and qualifies for Ecosystem Builder recognition.',
            'description' => 'Honours achieving the Ecosystem Builder level (500 approved lifetime impacts).',
            'recipient' => 'Member who achieved the milestone',
            'icon' => 'bi bi-heart-pulse',
            'impact_count' => 500,
            'impact_title' => 'Ecosystem Builder',
            'workflow_steps' => [
                ['title' => '500 Approved Impacts', 'desc' => 'Member lifetime verified impacts reach 500'],
                ['title' => 'Ecosystem Builder Recognition', 'desc' => 'Impact milestone service confirms qualification'],
                ['title' => 'Generate Graphic', 'desc' => 'Renders Ecosystem Builder recognition creative graphic'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches payload to FlexiMSG webhook endpoint'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives Ecosystem Builder recognition on WhatsApp'],
            ],
            'variables' => [
                'phone' => 'Member mobile number',
                'name' => 'Member full name',
                'first_name' => 'Member first name',
                'impact_count' => '500',
                'creative_url' => 'Generated Life Impact creative graphic URL',
                'badge_image_url' => 'Ecosystem Builder badge URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/ecosystem-builder-500',
            'default_webhook_secret' => '',
        ],
        'impact_architect_1000' => [
            'key' => 'impact_architect_1000',
            'name' => 'Impact Recognition — Impact Architect (1,000 Impacts)',
            'category' => 'Impact Recognition',
            'category_badge' => 'danger',
            'trigger_type' => 'Impact Milestone',
            'trigger_title' => 'When member reaches 1,000 approved lifetime impacts',
            'when_sent' => 'Automatically sent when a member reaches 1,000 approved lifetime impacts and qualifies for Impact Architect recognition.',
            'description' => 'Honours achieving the Impact Architect level (1,000 approved lifetime impacts).',
            'recipient' => 'Member who achieved the milestone',
            'icon' => 'bi bi-heart-pulse',
            'impact_count' => 1000,
            'impact_title' => 'Impact Architect',
            'workflow_steps' => [
                ['title' => '1,000 Approved Impacts', 'desc' => 'Member lifetime verified impacts reach 1,000'],
                ['title' => 'Impact Architect Recognition', 'desc' => 'Impact milestone service confirms qualification'],
                ['title' => 'Generate Graphic', 'desc' => 'Renders Impact Architect recognition creative graphic'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches payload to FlexiMSG webhook endpoint'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives Impact Architect recognition on WhatsApp'],
            ],
            'variables' => [
                'phone' => 'Member mobile number',
                'name' => 'Member full name',
                'first_name' => 'Member first name',
                'impact_count' => '1,000',
                'creative_url' => 'Generated Life Impact creative graphic URL',
                'badge_image_url' => 'Impact Architect badge URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/impact-architect-1000',
            'default_webhook_secret' => '',
        ],
        'legacy_maker_2500' => [
            'key' => 'legacy_maker_2500',
            'name' => 'Impact Recognition — Legacy Maker (2,500 Impacts)',
            'category' => 'Impact Recognition',
            'category_badge' => 'danger',
            'trigger_type' => 'Impact Milestone',
            'trigger_title' => 'When member reaches 2,500 approved lifetime impacts',
            'when_sent' => 'Automatically sent when a member reaches 2,500 approved lifetime impacts and qualifies for Legacy Maker recognition.',
            'description' => 'Honours achieving the Legacy Maker level (2,500 approved lifetime impacts).',
            'recipient' => 'Member who achieved the milestone',
            'icon' => 'bi bi-heart-pulse',
            'impact_count' => 2500,
            'impact_title' => 'Legacy Maker',
            'workflow_steps' => [
                ['title' => '2,500 Approved Impacts', 'desc' => 'Member lifetime verified impacts reach 2,500'],
                ['title' => 'Legacy Maker Recognition', 'desc' => 'Impact milestone service confirms qualification'],
                ['title' => 'Generate Graphic', 'desc' => 'Renders Legacy Maker recognition creative graphic'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches payload to FlexiMSG webhook endpoint'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives Legacy Maker recognition on WhatsApp'],
            ],
            'variables' => [
                'phone' => 'Member mobile number',
                'name' => 'Member full name',
                'first_name' => 'Member first name',
                'impact_count' => '2,500',
                'creative_url' => 'Generated Life Impact creative graphic URL',
                'badge_image_url' => 'Legacy Maker badge URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/legacy-maker-2500',
            'default_webhook_secret' => '',
        ],
        'torchbearer_5000' => [
            'key' => 'torchbearer_5000',
            'name' => 'Impact Recognition — Torchbearer (5,000 Impacts)',
            'category' => 'Impact Recognition',
            'category_badge' => 'danger',
            'trigger_type' => 'Impact Milestone',
            'trigger_title' => 'When member reaches 5,000 approved lifetime impacts',
            'when_sent' => 'Automatically sent when a member reaches 5,000 approved lifetime impacts and qualifies for Torchbearer recognition.',
            'description' => 'Honours achieving the Torchbearer level (5,000 approved lifetime impacts).',
            'recipient' => 'Member who achieved the milestone',
            'icon' => 'bi bi-heart-pulse',
            'impact_count' => 5000,
            'impact_title' => 'Torchbearer',
            'workflow_steps' => [
                ['title' => '5,000 Approved Impacts', 'desc' => 'Member lifetime verified impacts reach 5,000'],
                ['title' => 'Torchbearer Recognition', 'desc' => 'Impact milestone service confirms qualification'],
                ['title' => 'Generate Graphic', 'desc' => 'Renders Torchbearer recognition creative graphic'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches payload to FlexiMSG webhook endpoint'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives Torchbearer recognition on WhatsApp'],
            ],
            'variables' => [
                'phone' => 'Member mobile number',
                'name' => 'Member full name',
                'first_name' => 'Member first name',
                'impact_count' => '5,000',
                'creative_url' => 'Generated Life Impact creative graphic URL',
                'badge_image_url' => 'Torchbearer badge URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/torchbearer-5000',
            'default_webhook_secret' => '',
        ],
        'world_changer_10000' => [
            'key' => 'world_changer_10000',
            'name' => 'Impact Recognition — World Changer (10,000 Impacts)',
            'category' => 'Impact Recognition',
            'category_badge' => 'danger',
            'trigger_type' => 'Impact Milestone',
            'trigger_title' => 'When member reaches 10,000 approved lifetime impacts',
            'when_sent' => 'Automatically sent when a member reaches 10,000 approved lifetime impacts and qualifies for World Changer recognition.',
            'description' => 'Honours achieving the World Changer level (10,000 approved lifetime impacts).',
            'recipient' => 'Member who achieved the milestone',
            'icon' => 'bi bi-heart-pulse',
            'impact_count' => 10000,
            'impact_title' => 'World Changer',
            'workflow_steps' => [
                ['title' => '10,000 Approved Impacts', 'desc' => 'Member lifetime verified impacts reach 10,000'],
                ['title' => 'World Changer Recognition', 'desc' => 'Impact milestone service confirms qualification'],
                ['title' => 'Generate Graphic', 'desc' => 'Renders World Changer recognition creative graphic'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches payload to FlexiMSG webhook endpoint'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives World Changer recognition on WhatsApp'],
            ],
            'variables' => [
                'phone' => 'Member mobile number',
                'name' => 'Member full name',
                'first_name' => 'Member first name',
                'impact_count' => '10,000',
                'creative_url' => 'Generated Life Impact creative graphic URL',
                'badge_image_url' => 'World Changer badge URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/world-changer-10000',
            'default_webhook_secret' => '',
        ],
        'humanitarian_25000' => [
            'key' => 'humanitarian_25000',
            'name' => 'Impact Recognition — Humanitarian (25,000 Impacts)',
            'category' => 'Impact Recognition',
            'category_badge' => 'danger',
            'trigger_type' => 'Impact Milestone',
            'trigger_title' => 'When member reaches 25,000 approved lifetime impacts',
            'when_sent' => 'Automatically sent when a member reaches 25,000 approved lifetime impacts and qualifies for Humanitarian recognition.',
            'description' => 'Honours achieving the Humanitarian level (25,000 approved lifetime impacts).',
            'recipient' => 'Member who achieved the milestone',
            'icon' => 'bi bi-heart-pulse',
            'impact_count' => 25000,
            'impact_title' => 'Humanitarian',
            'workflow_steps' => [
                ['title' => '25,000 Approved Impacts', 'desc' => 'Member lifetime verified impacts reach 25,000'],
                ['title' => 'Humanitarian Recognition', 'desc' => 'Impact milestone service confirms qualification'],
                ['title' => 'Generate Graphic', 'desc' => 'Renders Humanitarian recognition creative graphic'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches payload to FlexiMSG webhook endpoint'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives Humanitarian recognition on WhatsApp'],
            ],
            'variables' => [
                'phone' => 'Member mobile number',
                'name' => 'Member full name',
                'first_name' => 'Member first name',
                'impact_count' => '25,000',
                'creative_url' => 'Generated Life Impact creative graphic URL',
                'badge_image_url' => 'Humanitarian badge URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/humanitarian-25000',
            'default_webhook_secret' => '',
        ],
        'history_maker_50000' => [
            'key' => 'history_maker_50000',
            'name' => 'Impact Recognition — History Maker (50,000 Impacts)',
            'category' => 'Impact Recognition',
            'category_badge' => 'danger',
            'trigger_type' => 'Impact Milestone',
            'trigger_title' => 'When member reaches 50,000 approved lifetime impacts',
            'when_sent' => 'Automatically sent when a member reaches 50,000 approved lifetime impacts and qualifies for History Maker recognition.',
            'description' => 'Honours achieving the History Maker level (50,000 approved lifetime impacts).',
            'recipient' => 'Member who achieved the milestone',
            'icon' => 'bi bi-heart-pulse',
            'impact_count' => 50000,
            'impact_title' => 'History Maker',
            'workflow_steps' => [
                ['title' => '50,000 Approved Impacts', 'desc' => 'Member lifetime verified impacts reach 50,000'],
                ['title' => 'History Maker Recognition', 'desc' => 'Impact milestone service confirms qualification'],
                ['title' => 'Generate Graphic', 'desc' => 'Renders History Maker recognition creative graphic'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches payload to FlexiMSG webhook endpoint'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives History Maker recognition on WhatsApp'],
            ],
            'variables' => [
                'phone' => 'Member mobile number',
                'name' => 'Member full name',
                'first_name' => 'Member first name',
                'impact_count' => '50,000',
                'creative_url' => 'Generated Life Impact creative graphic URL',
                'badge_image_url' => 'History Maker badge URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/history-maker-50000',
            'default_webhook_secret' => '',
        ],
        'peers_global_legend_100000' => [
            'key' => 'peers_global_legend_100000',
            'name' => 'Impact Recognition — Peers Global Legend (100,000 Impacts)',
            'category' => 'Impact Recognition',
            'category_badge' => 'danger',
            'trigger_type' => 'Impact Milestone',
            'trigger_title' => 'When member reaches 100,000 approved lifetime impacts',
            'when_sent' => 'Automatically sent when a member reaches 100,000 approved lifetime impacts and qualifies for Peers Global Legend recognition.',
            'description' => 'Pinnacle recognition honouring Peers Global Legend status for 100,000 approved lifetime impacts.',
            'recipient' => 'Member who achieved the milestone',
            'icon' => 'bi bi-heart-pulse',
            'impact_count' => 100000,
            'impact_title' => 'Peers Global Legend',
            'workflow_steps' => [
                ['title' => '100,000 Approved Impacts', 'desc' => 'Member lifetime verified impacts reach 100,000'],
                ['title' => 'Legend Recognition', 'desc' => 'Impact milestone service confirms qualification'],
                ['title' => 'Generate Graphic', 'desc' => 'Renders Peers Global Legend recognition creative graphic'],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches payload to FlexiMSG webhook endpoint'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Member receives Legend recognition on WhatsApp'],
            ],
            'variables' => [
                'phone' => 'Member mobile number',
                'name' => 'Member full name',
                'first_name' => 'Member first name',
                'impact_count' => '100,000',
                'creative_url' => 'Generated Life Impact creative graphic URL',
                'badge_image_url' => 'Peers Global Legend badge URL',
            ],
            'default_webhook_url' => 'https://fleximsg.com/api/webhooks/peers-global-legend-100000',
            'default_webhook_secret' => '',
        ],
    ];

    /**
     * Return all catalog items.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getCatalog(): array
    {
        return self::CATALOG;
    }

    /**
     * Safely synchronize catalog items into the database without overwriting existing customized values.
     */
    public function syncCatalogWithDatabase(): void
    {
        foreach (self::CATALOG as $key => $item) {
            $existing = WhatsappTemplate::query()->where('template_key', $key)->first();

            if (! $existing) {
                try {
                    WhatsappTemplate::query()->create([
                        'id' => (string) Str::uuid(),
                        'template_key' => $key,
                        'template_name' => $item['name'],
                        'webhook_url' => $item['default_webhook_url'] ?? 'https://fleximsg.com/api/webhooks/'.Str::slug($key),
                        'webhook_secret' => $item['default_webhook_secret'] ?? '',
                        'description' => $item['description'],
                        'is_active' => true,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning("[WhatsappTemplateCatalogService] Could not auto-sync template '{$key}': ".$e->getMessage());
                }
            }
        }
    }

    /**
     * Get merged templates list with search and filtering applied strictly from the database.
     * The database (whatsapp_templates) is the single source of truth for records.
     * The catalog provides supplemental metadata (triggers, workflows, variables, categories).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllTemplates(
        ?string $search = null,
        ?string $status = null,
        ?string $category = null,
        ?string $triggerType = null
    ): array {
        /** @var Collection<int, WhatsappTemplate> $dbTemplates */
        $dbTemplates = WhatsappTemplate::query()->get();

        $result = [];

        foreach ($dbTemplates as $dbModel) {
            $key = $dbModel->template_key;
            $catItem = self::CATALOG[$key] ?? $this->deriveMetadataForUnknownTemplate($dbModel);

            $item = $this->buildUnifiedTemplateItem($key, $catItem, $dbModel);

            if ($this->matchesFilters($item, $search, $status, $category, $triggerType)) {
                $result[] = $item;
            }
        }

        // Sort by category priority, then by milestone/impact count or name
        usort($result, function (array $a, array $b): int {
            $catOrder = [
                'Authentication' => 1,
                'Onboarding' => 2,
                'Engagement' => 3,
                'Growth / Referral' => 4,
                'Impact Recognition' => 5,
                'Other' => 6,
            ];

            $orderA = $catOrder[$a['category']] ?? 99;
            $orderB = $catOrder[$b['category']] ?? 99;

            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }

            // Sub-order for milestones and impacts by count
            if (! empty($a['milestone_count']) && ! empty($b['milestone_count'])) {
                return $a['milestone_count'] <=> $b['milestone_count'];
            }
            if (! empty($a['impact_count']) && ! empty($b['impact_count'])) {
                return $a['impact_count'] <=> $b['impact_count'];
            }

            return strcasecmp((string) $a['name'], (string) $b['name']);
        });

        return $result;
    }

    /**
     * Get full details for a single template strictly from the database by ID or template key.
     *
     * @return array<string, mixed>|null
     */
    public function getTemplateDetails(string $idOrKey): ?array
    {
        $dbModel = WhatsappTemplate::query()
            ->where('id', $idOrKey)
            ->orWhere('template_key', $idOrKey)
            ->first();

        if (! $dbModel) {
            return null;
        }

        $key = $dbModel->template_key;
        $catItem = self::CATALOG[$key] ?? $this->deriveMetadataForUnknownTemplate($dbModel);

        return $this->buildUnifiedTemplateItem($key, $catItem, $dbModel);
    }

    /**
     * Compute dynamic statistics across all templates strictly from the database.
     * Only categories that actually contain existing database records are tracked.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        /** @var Collection<int, WhatsappTemplate> $dbTemplates */
        $dbTemplates = WhatsappTemplate::query()->get();

        $categories = [];
        $active = 0;
        $inactive = 0;

        foreach ($dbTemplates as $dbModel) {
            if ((bool) $dbModel->is_active) {
                $active++;
            } else {
                $inactive++;
            }

            $key = $dbModel->template_key;
            $catItem = self::CATALOG[$key] ?? $this->deriveMetadataForUnknownTemplate($dbModel);
            $category = $catItem['category'] ?? 'Other';
            $categories[$category] = ($categories[$category] ?? 0) + 1;
        }

        return [
            'total' => $dbTemplates->count(),
            'active' => $active,
            'inactive' => $inactive,
            'category_count' => count($categories),
            'categories' => $categories,
        ];
    }

    /**
     * Build unified template item combining database attributes and catalog metadata.
     *
     * @param  array<string, mixed>  $catItem
     * @return array<string, mixed>
     */
    private function buildUnifiedTemplateItem(string $key, array $catItem, WhatsappTemplate $dbModel): array
    {
        $secret = (string) ($dbModel->webhook_secret ?? '');
        $hasSecret = trim($secret) !== '';

        return [
            'id' => $dbModel->id,
            'key' => $key,
            'template_key' => $key,
            'name' => $dbModel->template_name ?: ($catItem['name'] ?? Str::headline($key)),
            'display_name' => $dbModel->template_name ?: ($catItem['name'] ?? Str::headline($key)),
            'category' => $catItem['category'] ?? 'Other',
            'category_badge' => $catItem['category_badge'] ?? 'secondary',
            'trigger_type' => $catItem['trigger_type'] ?? 'Other',
            'trigger_title' => $catItem['trigger_title'] ?? "Triggered for {$key}",
            'when_sent' => $catItem['when_sent'] ?? ($dbModel->description ?: ($catItem['trigger_title'] ?? 'Trigger information is not currently documented.')),
            'description' => $dbModel->description ?: ($catItem['description'] ?? ''),
            'recipient' => $catItem['recipient'] ?? 'Registered Member',
            'icon' => $catItem['icon'] ?? 'bi bi-chat-dots',
            'webhook_url' => (string) ($dbModel->webhook_url ?? ''),
            'has_secret' => $hasSecret,
            'masked_secret' => $hasSecret ? str_repeat('•', 16) : 'Not Configured',
            'is_active' => (bool) $dbModel->is_active,
            'updated_at' => $dbModel->updated_at ? $dbModel->updated_at->toIso8601String() : null,
            'updated_at_formatted' => $dbModel->updated_at ? $dbModel->updated_at->diffForHumans() : 'Recently',
            'workflow_steps' => $catItem['workflow_steps'] ?? [
                ['title' => 'System Trigger', 'desc' => 'Application event dispatched'],
                ['title' => 'Template Lookup', 'desc' => "Resolves {$key} configuration from database"],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches payload to configured webhook'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Delivered to recipient on WhatsApp'],
            ],
            'variables' => $catItem['variables'] ?? [
                'phone' => 'Recipient mobile number',
                'first_name' => 'Recipient name',
            ],
            'milestone_count' => $catItem['milestone_count'] ?? null,
            'milestone_title' => $catItem['milestone_title'] ?? null,
            'impact_count' => $catItem['impact_count'] ?? null,
            'impact_title' => $catItem['impact_title'] ?? null,
            'db_record' => $dbModel,
        ];
    }

    /**
     * Check if a template item matches the requested filter parameters.
     *
     * @param  array<string, mixed>  $item
     */
    private function matchesFilters(
        array $item,
        ?string $search,
        ?string $status,
        ?string $category,
        ?string $triggerType
    ): bool {
        if ($search !== null && trim($search) !== '') {
            $q = strtolower(trim($search));
            $name = strtolower((string) ($item['name'] ?? ''));
            $key = strtolower((string) ($item['key'] ?? ''));
            $desc = strtolower((string) ($item['description'] ?? ''));
            $when = strtolower((string) ($item['when_sent'] ?? ''));

            if (! str_contains($name, $q) && ! str_contains($key, $q) && ! str_contains($desc, $q) && ! str_contains($when, $q)) {
                return false;
            }
        }

        if ($status !== null && $status !== '' && $status !== 'all') {
            if ($status === 'active' && empty($item['is_active'])) {
                return false;
            }
            if ($status === 'inactive' && ! empty($item['is_active'])) {
                return false;
            }
        }

        if ($category !== null && $category !== '' && $category !== 'all') {
            if (strcasecmp((string) $item['category'], $category) !== 0) {
                return false;
            }
        }

        if ($triggerType !== null && $triggerType !== '' && $triggerType !== 'all') {
            if (strcasecmp((string) $item['trigger_type'], $triggerType) !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Derive reasonable metadata for custom or uncataloged DB templates.
     *
     * @return array<string, mixed>
     */
    private function deriveMetadataForUnknownTemplate(WhatsappTemplate $model): array
    {
        $key = $model->template_key;
        $name = $model->template_name ?: Str::headline($key);

        $category = 'Other';
        $badge = 'secondary';
        $trigger = 'System Event';
        $icon = 'bi bi-chat-dots';

        if (str_starts_with($key, 'milestone_') || str_starts_with($key, 'pgu_catalyst')) {
            $category = 'Growth / Referral';
            $badge = 'success';
            $trigger = 'Referral Milestone';
            $icon = 'bi bi-trophy';
        } elseif (str_starts_with($key, 'impact_') || str_starts_with($key, 'change_maker') || str_starts_with($key, 'life_changer') || str_starts_with($key, 'ecosystem_') || str_starts_with($key, 'legacy_') || str_starts_with($key, 'torchbearer') || str_starts_with($key, 'world_changer') || str_starts_with($key, 'humanitarian') || str_starts_with($key, 'history_maker') || str_starts_with($key, 'peers_global_legend')) {
            $category = 'Impact Recognition';
            $badge = 'danger';
            $trigger = 'Impact Milestone';
            $icon = 'bi bi-heart-pulse';
        } elseif (str_starts_with($key, 'day_') || str_starts_with($key, 'business_referrals_day')) {
            $category = 'Engagement';
            $badge = 'warning';
            $trigger = 'Time-based';
            $icon = 'bi bi-calendar-check';
        } elseif (str_contains($key, 'otp') || str_contains($key, 'auth')) {
            $category = 'Authentication';
            $badge = 'primary';
            $trigger = 'Authentication';
            $icon = 'bi bi-shield-lock';
        } elseif (str_contains($key, 'welcome') || str_contains($key, 'badge') || str_contains($key, 'profile')) {
            $category = 'Onboarding';
            $badge = 'info';
            $trigger = 'Registration';
            $icon = 'bi bi-person-check';
        }

        return [
            'key' => $key,
            'name' => $name,
            'category' => $category,
            'category_badge' => $badge,
            'trigger_type' => $trigger,
            'trigger_title' => "Triggered for {$key}",
            'when_sent' => $model->description ?: 'Trigger information is not currently documented.',
            'description' => $model->description ?: 'Custom WhatsApp template record.',
            'recipient' => 'Target Member',
            'icon' => $icon,
            'workflow_steps' => [
                ['title' => 'Custom Trigger', 'desc' => 'Workflow initiates on trigger condition'],
                ['title' => 'Template Lookup', 'desc' => "Resolves {$key} template from DB"],
                ['title' => 'FlexiMSG Webhook', 'desc' => 'Dispatches payload to configured webhook'],
                ['title' => 'WhatsApp Delivery', 'desc' => 'Message delivered to user on WhatsApp'],
            ],
            'variables' => [
                'phone' => 'Target phone number',
            ],
        ];
    }
}
