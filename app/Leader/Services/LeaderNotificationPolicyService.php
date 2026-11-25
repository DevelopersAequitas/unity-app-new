<?php

declare(strict_types=1);

namespace App\Leader\Services;

use App\Models\AdminUser;
use App\Models\Circle;
use App\Models\District;
use App\Models\User;
use App\Shared\Services\UserRoleResolverService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LeaderNotificationPolicyService
{
    public const CONSUMER_EXCLUDED_TYPES = [
        'new_post',
        'post_created',
        'post_like',
        'post_liked',
        'post_comment',
        'post_commented',
        'comment_like',
        'comment_liked',
        'post_tagged',
        'post_shared',
        'feed_post',
        'peer_post',
        'social_post',
        'feed_update',
        'event',
        'new_event',
        'event_created',
        'upcoming_event_reminder',
        'event_starting_now',
        'event_reminder',
        'live_meeting',
        'speed_networking',
        'connection_request',
        'connection_accepted',
        'chat_message',
        'chat',
        'direct_message',
        'badge_earned',
        'badge_unlocked',
        'profile_view',
        'profile_viewed',
        'daily_reminder',
        'daily_engagement_reminder',
        'engagement_reminder',
        'engagement',
        'engagement_founder',
        'streak_reminder',
        'streak',
        'birthday_anniversary',
        'peer_wish',
        'wish_received',
        'p2p_meeting_request',
        'p2p_meeting_confirmed',
        'p2p_meeting_declined',
        'p2p_post_meeting_outcome',
        'coin_milestone',
        'unclaimed_coins',
        'non_pro_upsell',
        'pro_expiry',
        'leaderboard_rank_change',
        'weekly_digest',
    ];

    public const CONSUMER_EXCLUDED_SCREENS = [
        'event_detail',
        'event_details',
        'live_meeting',
        'post_detail',
        '/post-detail',
        'feed',
        '/feed',
        'chat_conversation',
        'connections',
        'member_feed',
        '/member-profile',
        '/my-connections',
        '/connection-requests',
    ];

    public const FINANCIAL_SENSITIVE_TYPES = [
        'withdrawal_request',
        'leader_bonus_payout_request',
        'leader_bonus_milestone',
        'milestone_award_approval',
        'offline_payment_verification',
        'commission_credited',
        'commission_payout',
        'joining_cut_credited',
        'ledger_entry',
        'finance_reconciliation_discrepancy',
        'circle_financial_irregularity',
    ];

    public const CRITICAL_NON_DISABLEABLE_TYPES = [
        'role_matrix_modified',
        'security_alert',
        'account_deletion_request',
        'leader_account_status_changed',
        'regional_sla_escalation',
        'overdue_report_compliance',
        'support_ticket_sla_breach',
        'post_reported',
        'notification_delivery_failure',
        'admin_broadcast',
    ];

    /**
     * Canonical 13 scoped leadership role keys (snake_case for config storage).
     */
    public const CANONICAL_CONFIG_ROLES = [
        'super_admin' => 'superAdmin',
        'global_founder' => 'globalFounder',
        'country_director' => 'countryDirector',
        'district_exec_director' => 'districtExecDirector',
        'industry_director' => 'industryDirector',
        'circle_founder' => 'circleFounder',
        'circle_director' => 'circleDirector',
        'circle_chair' => 'circleChair',
        'chair_business_growth' => 'chairBusinessGrowth',
        'chair_membership' => 'chairMembership',
        'chair_events_programs' => 'chairEventsPrograms',
        'vice_chair' => 'viceChair',
        'secretary' => 'secretary',
        'committee_leader' => 'committeeLeader',
    ];

    public function __construct(
        private readonly UserRoleResolverService $roleResolver,
        private readonly LeaderPermissionService $permissionService,
    ) {}

    /**
     * Authoritative catalogue of approved Leadership App events.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getEventCatalogue(): array
    {
        return [
            // Domain L — Leadership Governance
            'leader_interest_submitted' => [
                'id' => 'L-01',
                'category' => 'governance',
                'delivery_tier' => 'important',
                'priority' => 'high',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/leader-activities',
                'entity_type' => 'approval',
                'primary_roles' => ['countryDirector'],
                'backup_roles' => ['superAdmin'],
                'info_roles' => ['districtExecDirector'],
                'global_founder' => false,
                'super_admin_push' => false,
            ],
            'website_nomination_submitted' => [
                'id' => 'L-02',
                'category' => 'governance',
                'delivery_tier' => 'important',
                'priority' => 'high',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/leader-activities',
                'entity_type' => 'approval',
                'primary_roles' => ['superAdmin'],
                'backup_roles' => ['countryDirector'],
                'info_roles' => [],
                'global_founder' => true,
                'super_admin_push' => true,
            ],
            'jury_evaluation_assigned' => [
                'id' => 'L-03',
                'category' => 'governance',
                'delivery_tier' => 'reminder',
                'priority' => 'high',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/leader-activities',
                'entity_type' => 'governance',
                'primary_roles' => [],
                'backup_roles' => [],
                'info_roles' => ['superAdmin'],
                'global_founder' => true,
                'super_admin_push' => false,
            ],
            'leadership_tenure_appointed' => [
                'id' => 'L-04',
                'category' => 'governance',
                'delivery_tier' => 'informational',
                'priority' => 'medium',
                'requires_action' => false,
                'financial_sensitive' => false,
                'screen' => '/role-management',
                'entity_type' => 'role_assignment',
                'primary_roles' => [],
                'backup_roles' => [],
                'info_roles' => ['countryDirector', 'districtExecDirector', 'circleDirector', 'circleFounder'],
                'global_founder' => true,
                'super_admin_push' => false,
            ],
            'leadership_tenure_expiring' => [
                'id' => 'L-05',
                'category' => 'governance',
                'delivery_tier' => 'reminder',
                'priority' => 'medium',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/role-management',
                'entity_type' => 'role_assignment',
                'primary_roles' => ['countryDirector', 'districtExecDirector'],
                'backup_roles' => ['superAdmin'],
                'info_roles' => [],
                'global_founder' => false,
                'super_admin_push' => false,
            ],
            'leader_bonus_milestone' => [
                'id' => 'L-06',
                'category' => 'finance',
                'delivery_tier' => 'informational',
                'priority' => 'medium',
                'requires_action' => false,
                'financial_sensitive' => true,
                'screen' => '/finance',
                'entity_type' => 'finance',
                'primary_roles' => [],
                'backup_roles' => [],
                'info_roles' => ['countryDirector'],
                'global_founder' => false,
                'super_admin_push' => false,
            ],
            'leader_bonus_payout_request' => [
                'id' => 'L-07',
                'category' => 'finance',
                'delivery_tier' => 'critical',
                'priority' => 'urgent',
                'requires_action' => true,
                'financial_sensitive' => true,
                'screen' => '/finance',
                'entity_type' => 'finance',
                'primary_roles' => ['superAdmin'],
                'backup_roles' => ['countryDirector'],
                'info_roles' => [],
                'global_founder' => false,
                'super_admin_push' => true,
            ],
            'role_matrix_modified' => [
                'id' => 'L-08',
                'category' => 'security',
                'delivery_tier' => 'critical',
                'priority' => 'urgent',
                'requires_action' => false,
                'financial_sensitive' => false,
                'screen' => '/role-management',
                'entity_type' => 'security',
                'primary_roles' => ['superAdmin'],
                'backup_roles' => [],
                'info_roles' => [],
                'global_founder' => true,
                'super_admin_push' => true,
            ],
            'official_circle_circular' => [
                'id' => 'L-09',
                'category' => 'governance',
                'delivery_tier' => 'informational',
                'priority' => 'medium',
                'requires_action' => false,
                'financial_sensitive' => false,
                'screen' => '/circulars',
                'entity_type' => 'circular',
                'primary_roles' => [],
                'backup_roles' => [],
                'info_roles' => [],
                'global_founder' => true,
                'super_admin_push' => false,
            ],

            // Domain M — Membership & Operations
            'circle_join_request' => [
                'id' => 'M-01',
                'category' => 'membership',
                'delivery_tier' => 'important',
                'priority' => 'high',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/leader-activities',
                'entity_type' => 'approval',
                'primary_roles' => ['circleDirector'],
                'backup_roles' => ['circleFounder'],
                'info_roles' => ['districtExecDirector', 'chairMembership'],
                'global_founder' => false,
                'super_admin_push' => false,
            ],
            'circle_join_stage2_clearance' => [
                'id' => 'M-02',
                'category' => 'membership',
                'delivery_tier' => 'important',
                'priority' => 'high',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/leader-activities',
                'entity_type' => 'approval',
                'primary_roles' => ['industryDirector'],
                'backup_roles' => ['districtExecDirector'],
                'info_roles' => ['circleDirector', 'circleFounder'],
                'global_founder' => false,
                'super_admin_push' => false,
            ],
            'new_peer_chartered' => [
                'id' => 'M-03',
                'category' => 'membership',
                'delivery_tier' => 'informational',
                'priority' => 'medium',
                'requires_action' => false,
                'financial_sensitive' => false,
                'screen' => '/peer-profile',
                'entity_type' => 'peer',
                'primary_roles' => ['circleDirector', 'chairMembership'],
                'backup_roles' => [],
                'info_roles' => ['countryDirector', 'districtExecDirector', 'industryDirector', 'circleFounder', 'circleChair', 'viceChair', 'secretary'],
                'global_founder' => false,
                'super_admin_push' => false,
            ],
            'offline_payment_verification' => [
                'id' => 'M-04',
                'category' => 'finance',
                'delivery_tier' => 'critical',
                'priority' => 'urgent',
                'requires_action' => true,
                'financial_sensitive' => true,
                'screen' => '/finance',
                'entity_type' => 'offline_payment',
                'primary_roles' => ['superAdmin', 'countryDirector'],
                'backup_roles' => ['districtExecDirector'],
                'info_roles' => [],
                'global_founder' => false,
                'super_admin_push' => true,
            ],
            'peer_renewal_due' => [
                'id' => 'M-05',
                'category' => 'membership',
                'delivery_tier' => 'reminder',
                'priority' => 'medium',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/peer-profile',
                'entity_type' => 'peer',
                'primary_roles' => ['circleDirector', 'chairMembership'],
                'backup_roles' => ['circleFounder'],
                'info_roles' => ['viceChair'],
                'global_founder' => false,
                'super_admin_push' => false,
            ],
            'peer_membership_expired' => [
                'id' => 'M-06',
                'category' => 'membership',
                'delivery_tier' => 'important',
                'priority' => 'high',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/peer-profile',
                'entity_type' => 'peer',
                'primary_roles' => ['circleDirector'],
                'backup_roles' => [],
                'info_roles' => ['districtExecDirector', 'circleFounder', 'chairMembership'],
                'global_founder' => false,
                'super_admin_push' => false,
            ],
            'peer_health_flagged' => [
                'id' => 'M-07',
                'category' => 'performance',
                'delivery_tier' => 'important',
                'priority' => 'high',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/peer-profile',
                'entity_type' => 'peer',
                'primary_roles' => ['circleDirector', 'chairMembership'],
                'backup_roles' => ['circleFounder'],
                'info_roles' => ['viceChair'],
                'global_founder' => false,
                'super_admin_push' => false,
            ],
            'introduction_claim_pending' => [
                'id' => 'M-09',
                'category' => 'membership',
                'delivery_tier' => 'important',
                'priority' => 'high',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/leader-activities',
                'entity_type' => 'approval',
                'primary_roles' => ['countryDirector'],
                'backup_roles' => ['superAdmin', 'circleDirector'],
                'info_roles' => [],
                'global_founder' => false,
                'super_admin_push' => false,
            ],
            'account_deletion_request' => [
                'id' => 'M-10',
                'category' => 'security',
                'delivery_tier' => 'critical',
                'priority' => 'urgent',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/leader-activities',
                'entity_type' => 'security',
                'primary_roles' => ['superAdmin'],
                'backup_roles' => [],
                'info_roles' => [],
                'global_founder' => false,
                'super_admin_push' => true,
            ],

            // Domain C — Circles & Compliance
            'circle_proposal_submitted' => [
                'id' => 'C-01',
                'category' => 'governance',
                'delivery_tier' => 'important',
                'priority' => 'high',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/leader-activities',
                'entity_type' => 'approval',
                'primary_roles' => ['countryDirector', 'districtExecDirector'],
                'backup_roles' => ['superAdmin'],
                'info_roles' => [],
                'global_founder' => true,
                'super_admin_push' => false,
            ],
            'circle_chartered' => [
                'id' => 'C-02',
                'category' => 'governance',
                'delivery_tier' => 'informational',
                'priority' => 'medium',
                'requires_action' => false,
                'financial_sensitive' => false,
                'screen' => '/circle-details',
                'entity_type' => 'circle',
                'primary_roles' => ['circleFounder', 'circleDirector'],
                'backup_roles' => [],
                'info_roles' => ['countryDirector', 'districtExecDirector', 'industryDirector'],
                'global_founder' => true,
                'super_admin_push' => false,
            ],
            'open_category_nomination' => [
                'id' => 'C-03',
                'category' => 'membership',
                'delivery_tier' => 'important',
                'priority' => 'medium',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/leader-activities',
                'entity_type' => 'approval',
                'primary_roles' => ['circleDirector', 'chairMembership'],
                'backup_roles' => ['circleFounder'],
                'info_roles' => ['industryDirector'],
                'global_founder' => false,
                'super_admin_push' => false,
            ],
            'weekly_report_due' => [
                'id' => 'C-04',
                'category' => 'compliance',
                'delivery_tier' => 'reminder',
                'priority' => 'medium',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/reports',
                'entity_type' => 'report',
                'primary_roles' => ['circleDirector', 'secretary'],
                'backup_roles' => ['circleFounder', 'viceChair'],
                'info_roles' => ['circleChair', 'chairBusinessGrowth', 'chairMembership', 'chairEventsPrograms'],
                'global_founder' => false,
                'super_admin_push' => false,
            ],
            'circle_report_submitted' => [
                'id' => 'C-05',
                'category' => 'performance',
                'delivery_tier' => 'informational',
                'priority' => 'medium',
                'requires_action' => false,
                'financial_sensitive' => false,
                'screen' => '/report-details',
                'entity_type' => 'report',
                'primary_roles' => ['districtExecDirector'],
                'backup_roles' => [],
                'info_roles' => ['countryDirector', 'industryDirector', 'circleFounder', 'circleDirector'],
                'global_founder' => false,
                'super_admin_push' => false,
            ],
            'circle_attendance_dip' => [
                'id' => 'C-06',
                'category' => 'performance',
                'delivery_tier' => 'important',
                'priority' => 'high',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/circle-details',
                'entity_type' => 'circle',
                'primary_roles' => ['circleDirector', 'chairEventsPrograms'],
                'backup_roles' => ['circleFounder', 'viceChair'],
                'info_roles' => ['districtExecDirector', 'chairMembership', 'secretary'],
                'global_founder' => false,
                'super_admin_push' => false,
            ],
            'overdue_report_compliance' => [
                'id' => 'C-07',
                'category' => 'compliance',
                'delivery_tier' => 'critical',
                'priority' => 'urgent',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/reports',
                'entity_type' => 'report',
                'primary_roles' => ['districtExecDirector', 'circleFounder', 'circleDirector'],
                'backup_roles' => ['countryDirector', 'superAdmin', 'secretary'],
                'info_roles' => [],
                'global_founder' => true,
                'super_admin_push' => true,
            ],

            // Domain D — District & Regional Governance
            'regional_sla_escalation' => [
                'id' => 'D-01',
                'category' => 'compliance',
                'delivery_tier' => 'critical',
                'priority' => 'urgent',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/leader-activities',
                'entity_type' => 'approval',
                'primary_roles' => ['districtExecDirector', 'industryDirector'],
                'backup_roles' => ['countryDirector', 'superAdmin'],
                'info_roles' => [],
                'global_founder' => true,
                'super_admin_push' => true,
            ],
            'vacant_leadership_seat' => [
                'id' => 'D-02',
                'category' => 'governance',
                'delivery_tier' => 'important',
                'priority' => 'high',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/circle-details',
                'entity_type' => 'circle',
                'primary_roles' => ['districtExecDirector'],
                'backup_roles' => ['circleFounder', 'circleDirector'],
                'info_roles' => ['countryDirector'],
                'global_founder' => false,
                'super_admin_push' => false,
            ],
            'territory_scale_milestone' => [
                'id' => 'D-03',
                'category' => 'performance',
                'delivery_tier' => 'informational',
                'priority' => 'low',
                'requires_action' => false,
                'financial_sensitive' => false,
                'screen' => '/dashboard',
                'entity_type' => 'circle',
                'primary_roles' => [],
                'backup_roles' => [],
                'info_roles' => ['countryDirector', 'districtExecDirector'],
                'global_founder' => true,
                'super_admin_push' => false,
            ],

            // Domain I — Industry & Business Operations
            'industry_category_conflict' => [
                'id' => 'I-01',
                'category' => 'industry',
                'delivery_tier' => 'important',
                'priority' => 'high',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/leader-activities',
                'entity_type' => 'industry_op',
                'primary_roles' => ['industryDirector'],
                'backup_roles' => ['districtExecDirector'],
                'info_roles' => ['circleDirector'],
                'global_founder' => false,
                'super_admin_push' => false,
            ],
            'cross_circle_deal' => [
                'id' => 'I-02',
                'category' => 'industry',
                'delivery_tier' => 'informational',
                'priority' => 'medium',
                'requires_action' => false,
                'financial_sensitive' => false,
                'screen' => '/business-deals',
                'entity_type' => 'industry_op',
                'primary_roles' => [],
                'backup_roles' => [],
                'info_roles' => ['industryDirector', 'circleDirector', 'circleChair', 'chairBusinessGrowth'],
                'global_founder' => true,
                'super_admin_push' => false,
            ],
            'unmatched_industry_ask' => [
                'id' => 'I-03',
                'category' => 'industry',
                'delivery_tier' => 'reminder',
                'priority' => 'medium',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/peer-collaboration',
                'entity_type' => 'industry_op',
                'primary_roles' => ['industryDirector', 'circleChair', 'chairBusinessGrowth'],
                'backup_roles' => [],
                'info_roles' => ['circleDirector'],
                'global_founder' => false,
                'super_admin_push' => false,
            ],

            // Domain A — Activities, Events & Support
            'visitor_registration_pending' => [
                'id' => 'A-01',
                'category' => 'events',
                'delivery_tier' => 'important',
                'priority' => 'high',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/leader-activities',
                'entity_type' => 'approval',
                'primary_roles' => ['circleDirector', 'chairEventsPrograms'],
                'backup_roles' => ['districtExecDirector', 'circleFounder'],
                'info_roles' => ['viceChair', 'secretary'],
                'global_founder' => false,
                'super_admin_push' => false,
            ],
            'event_join_request_pending' => [
                'id' => 'A-02',
                'category' => 'events',
                'delivery_tier' => 'important',
                'priority' => 'high',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/circle-events',
                'entity_type' => 'event',
                'primary_roles' => ['circleDirector', 'chairEventsPrograms'],
                'backup_roles' => ['districtExecDirector', 'circleFounder'],
                'info_roles' => [],
                'global_founder' => false,
                'super_admin_push' => false,
            ],
            'life_impact_pending' => [
                'id' => 'A-03',
                'category' => 'events',
                'delivery_tier' => 'important',
                'priority' => 'high',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/leader-activities',
                'entity_type' => 'approval',
                'primary_roles' => ['circleDirector'],
                'backup_roles' => ['districtExecDirector', 'circleFounder'],
                'info_roles' => ['chairEventsPrograms'],
                'global_founder' => false,
                'super_admin_push' => false,
            ],
            'coin_claim_pending' => [
                'id' => 'A-04',
                'category' => 'membership',
                'delivery_tier' => 'important',
                'priority' => 'medium',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/leader-activities',
                'entity_type' => 'approval',
                'primary_roles' => ['countryDirector', 'districtExecDirector'],
                'backup_roles' => ['superAdmin', 'circleDirector'],
                'info_roles' => [],
                'global_founder' => false,
                'super_admin_push' => false,
            ],
            'certification_submitted' => [
                'id' => 'A-05',
                'category' => 'membership',
                'delivery_tier' => 'important',
                'priority' => 'medium',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/leader-activities',
                'entity_type' => 'approval',
                'primary_roles' => ['countryDirector'],
                'backup_roles' => ['superAdmin'],
                'info_roles' => ['circleDirector'],
                'global_founder' => false,
                'super_admin_push' => false,
            ],
            'lead_or_ad_submitted' => [
                'id' => 'A-06',
                'category' => 'industry',
                'delivery_tier' => 'informational',
                'priority' => 'medium',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/leader-activities',
                'entity_type' => 'approval',
                'primary_roles' => ['superAdmin', 'countryDirector'],
                'backup_roles' => [],
                'info_roles' => [],
                'global_founder' => false,
                'super_admin_push' => false,
            ],
            'post_reported' => [
                'id' => 'A-07',
                'category' => 'security',
                'delivery_tier' => 'critical',
                'priority' => 'urgent',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/peer-profile',
                'entity_type' => 'peer',
                'primary_roles' => ['superAdmin'],
                'backup_roles' => ['countryDirector', 'circleDirector'],
                'info_roles' => [],
                'global_founder' => false,
                'super_admin_push' => true,
            ],
            'support_ticket_sla_breach' => [
                'id' => 'A-09',
                'category' => 'compliance',
                'delivery_tier' => 'critical',
                'priority' => 'urgent',
                'requires_action' => true,
                'financial_sensitive' => false,
                'screen' => '/notifications',
                'entity_type' => 'support',
                'primary_roles' => ['superAdmin'],
                'backup_roles' => ['countryDirector'],
                'info_roles' => [],
                'global_founder' => true,
                'super_admin_push' => true,
            ],
            'admin_broadcast' => [
                'id' => 'SYS-01',
                'category' => 'system',
                'delivery_tier' => 'critical',
                'priority' => 'high',
                'requires_action' => false,
                'financial_sensitive' => false,
                'screen' => '/dashboard',
                'entity_type' => 'system',
                'primary_roles' => [],
                'backup_roles' => [],
                'info_roles' => [],
                'global_founder' => true,
                'super_admin_push' => true,
            ],
        ];
    }

    /**
     * Determine if a notification is explicitly classified for the Leadership App.
     */
    public function isLeadershipAudiencePayload(array $data, string $type, string $category, string $screen): bool
    {
        $audience = strtolower((string) ($data['audience'] ?? ''));
        if ($audience === 'member' || $audience === 'consumer') {
            return false;
        }

        $lowerType = strtolower($type);
        $lowerCat = strtolower($category);
        $lowerScreen = strtolower($screen);

        if (in_array($lowerType, self::CONSUMER_EXCLUDED_TYPES, true)
            || in_array($lowerCat, self::CONSUMER_EXCLUDED_TYPES, true)
            || in_array($lowerScreen, self::CONSUMER_EXCLUDED_SCREENS, true)) {
            return false;
        }

        if ($audience === 'leadership' || ($data['app'] ?? '') === 'leader' || ($data['app_name'] ?? '') === 'leader_app') {
            return true;
        }

        $catalogue = $this->getEventCatalogue();
        if (isset($catalogue[$lowerType]) || isset($catalogue[(string) ($data['event_key'] ?? '')])) {
            return true;
        }

        $approvedCategories = [
            'governance', 'membership', 'finance', 'performance', 'events',
            'industry', 'compliance', 'security', 'system', 'circle', 'reports', 'milestones',
        ];

        return in_array($lowerCat, $approvedCategories, true) && $lowerType !== 'activity_update';
    }

    public function isFinancialSensitive(string $type, string $category, array $data = []): bool
    {
        if (! empty($data['financial_sensitive'])) {
            return true;
        }

        $lowerType = strtolower($type);
        $lowerCat = strtolower($category);

        return $lowerCat === 'finance' || in_array($lowerType, self::FINANCIAL_SENSITIVE_TYPES, true);
    }

    public function canUserReceiveFinancialNotification(User|AdminUser $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        $roleInfo = $this->permissionService->resolveUserRole($user);
        $canonical = (string) ($roleInfo['role'] ?? '');
        if ($canonical === 'superAdmin') {
            return true;
        }

        return $this->permissionService->hasCapability($user, 'access_finance');
    }

    public function isCriticalNotification(string $type, string $priority, string $deliveryTier = ''): bool
    {
        if (strtolower($deliveryTier) === 'critical' || strtolower($priority) === 'urgent') {
            return true;
        }

        return in_array(strtolower($type), self::CRITICAL_NON_DISABLEABLE_TYPES, true);
    }

    /**
     * Load dynamic active & historical roles from the `roles` table merged with canonical defaults.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDynamicRolesCatalog(): array
    {
        $defaults = $this->getDefaultRoleConfig();
        $roles = [];
        $seen = [];

        if (Schema::hasTable('roles')) {
            $cols = Schema::getColumnListing('roles');
            $dbRoles = DB::table('roles')->orderBy('name')->get();
            foreach ($dbRoles as $r) {
                $rawKey = (string) ($r->key ?? $r->name ?? $r->id);
                $canon = $this->roleResolver->normalizeRoleKey($rawKey);
                $snake = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1_$2', $canon));
                $isActive = in_array('is_active', $cols, true) ? (bool) ($r->is_active ?? true) : true;
                $seen[$snake] = true;
                $roles[] = [
                    'id' => (string) $r->id,
                    'role_key' => $snake,
                    'canonical_role' => $canon,
                    'role_label' => (string) ($r->name ?? ($defaults[$snake]['role_label'] ?? $this->roleResolver->getRoleLabel($canon))),
                    'description' => (string) ($defaults[$snake]['description'] ?? 'Dynamically configured leadership role.'),
                    'is_active' => $isActive,
                    'is_system' => in_array($snake, array_keys($defaults), true),
                ];
            }
        }

        foreach ($defaults as $snake => $cfg) {
            if (! isset($seen[$snake])) {
                $canon = self::CANONICAL_CONFIG_ROLES[$snake] ?? $snake;
                $roles[] = [
                    'id' => $snake,
                    'role_key' => $snake,
                    'canonical_role' => $canon,
                    'role_label' => (string) ($cfg['role_label'] ?? $canon),
                    'description' => (string) ($cfg['description'] ?? ''),
                    'is_active' => true,
                    'is_system' => true,
                ];
            }
        }

        return $roles;
    }

    /**
     * Get per-event flow enabled/disabled states stored in app_configs.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getFlowStates(): array
    {
        if (! Schema::hasTable('app_configs')) {
            return [];
        }

        $raw = DB::table('app_configs')
            ->where('key', 'leader_notification_flow_states')
            ->value('value');

        $decoded = $raw ? json_decode((string) $raw, true) : [];

        return is_array($decoded) ? $decoded : [];
    }

    public function isFlowEnabled(string $eventKey): bool
    {
        $catalogue = $this->getEventCatalogue();
        $spec = $catalogue[$eventKey] ?? null;
        if ($spec && $this->isCriticalNotification($eventKey, (string) ($spec['priority'] ?? ''), (string) ($spec['delivery_tier'] ?? ''))) {
            return true;
        }

        $states = $this->getFlowStates();
        if (isset($states[$eventKey]) && array_key_exists('enabled', $states[$eventKey])) {
            return (bool) $states[$eventKey]['enabled'];
        }

        return true;
    }

    /**
     * Append an immutable administrative audit entry for notification governance actions.
     *
     * @param  array<string, mixed>  $entry
     */
    public function recordAdminAudit(array $entry): void
    {
        if (! Schema::hasTable('app_configs')) {
            return;
        }

        $raw = DB::table('app_configs')
            ->where('key', 'leader_notification_audit_logs')
            ->value('value');

        $logs = $raw ? json_decode((string) $raw, true) : [];
        if (! is_array($logs)) {
            $logs = [];
        }

        array_unshift($logs, array_merge([
            'id' => (string) Str::uuid(),
            'created_at' => now()->toIso8601String(),
        ], $entry));

        $logs = array_slice($logs, 0, 500);

        DB::table('app_configs')->updateOrInsert(
            ['key' => 'leader_notification_audit_logs'],
            [
                'value' => json_encode($logs),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Resolve global Super Admin user IDs (excludes Country Directors & Global Founders).
     *
     * @return array<int, string>
     */
    public function resolveSuperAdminUserIds(): array
    {
        $ids = [];

        if (Schema::hasTable('users')) {
            User::query()->whereNull('deleted_at')->orderBy('id')->chunkById(500, function ($users) use (&$ids): void {
                foreach ($users as $u) {
                    $roleInfo = $this->roleResolver->resolveUserRole($u);
                    if (($roleInfo['role'] ?? '') === 'superAdmin') {
                        $ids[] = (string) $u->id;
                    }
                }
            });
        }

        if (Schema::hasTable('admin_users')) {
            foreach (AdminUser::query()->get() as $au) {
                $roleInfo = $this->roleResolver->resolveUserRole($au);
                if (($roleInfo['role'] ?? '') === 'superAdmin') {
                    $ids[] = (string) $au->id;
                }
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Resolve Global Founder user IDs.
     *
     * @return array<int, string>
     */
    public function resolveGlobalFounderUserIds(): array
    {
        $ids = [];

        if (Schema::hasTable('users')) {
            User::query()->whereNull('deleted_at')->orderBy('id')->chunkById(500, function ($users) use (&$ids): void {
                foreach ($users as $u) {
                    $roleInfo = $this->roleResolver->resolveUserRole($u);
                    if (($roleInfo['role'] ?? '') === 'globalFounder') {
                        $ids[] = (string) $u->id;
                    }
                }
            });
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Resolve Country Director user IDs strictly scoped to the given country.
     *
     * @return array<int, string>
     */
    public function resolveCountryDirectorUserIds(?string $countryId = null, ?string $countryName = null): array
    {
        $ids = [];
        $countryId = trim((string) $countryId);
        $countryName = strtolower(trim((string) $countryName));

        if ($countryId === '' && $countryName === '') {
            return [];
        }

        if (Schema::hasTable('user_organizational_assignments')) {
            $q = DB::table('user_organizational_assignments')
                ->where('is_active', true);
            if ($countryId !== '' && Schema::hasColumn('user_organizational_assignments', 'country_id')) {
                $q->where('country_id', $countryId);
            }
            $rows = $q->get();
            foreach ($rows as $row) {
                $canon = $this->roleResolver->normalizeRoleKey((string) ($row->role_key ?? ''));
                if ($canon === 'countryDirector' && ! empty($row->user_id)) {
                    $ids[] = (string) $row->user_id;
                }
            }
        }

        if (Schema::hasTable('users')) {
            User::query()->whereNull('deleted_at')->orderBy('id')->chunkById(500, function ($users) use (&$ids, $countryId, $countryName): void {
                foreach ($users as $u) {
                    $roleInfo = $this->roleResolver->resolveUserRole($u);
                    if (($roleInfo['role'] ?? '') !== 'countryDirector') {
                        continue;
                    }

                    $userCountryId = (string) ($u->country_id ?? '');
                    $userCountryName = strtolower(trim((string) ($u->country ?? '')));
                    if (($countryId !== '' && $userCountryId === $countryId)
                        || ($countryName !== '' && $userCountryName === $countryName)) {
                        $ids[] = (string) $u->id;
                    }
                }
            });
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Resolve District Executive Director (DED) user IDs scoped to a district.
     *
     * @return array<int, string>
     */
    public function resolveDistrictDirectorUserIds(string $districtId): array
    {
        $ids = [];
        if ($districtId === '') {
            return [];
        }

        if (Schema::hasTable('districts')) {
            $district = District::query()->find($districtId);
            if ($district) {
                $cols = Schema::getColumnListing('districts');
                foreach (['ded_user_id', 'executive_director_user_id', 'director_user_id'] as $col) {
                    if (in_array($col, $cols, true) && ! empty($district->{$col})) {
                        $ids[] = (string) $district->{$col};
                    }
                }
            }
        }

        if (Schema::hasTable('admin_ded_districts')) {
            $rows = DB::table('admin_ded_districts')->where('district_id', $districtId)->get();
            foreach ($rows as $row) {
                if (! empty($row->user_id)) {
                    $ids[] = (string) $row->user_id;
                }
                if (! empty($row->admin_user_id)) {
                    $resolved = $this->resolveUserIdFromAdminId((string) $row->admin_user_id);
                    if ($resolved !== null) {
                        $ids[] = $resolved;
                    }
                }
            }
        }

        if (Schema::hasTable('user_organizational_assignments') && Schema::hasColumn('user_organizational_assignments', 'district_id')) {
            $rows = DB::table('user_organizational_assignments')
                ->where('district_id', $districtId)
                ->where('is_active', true)
                ->get();
            foreach ($rows as $row) {
                $canon = $this->roleResolver->normalizeRoleKey((string) ($row->role_key ?? ''));
                if ($canon === 'districtExecDirector' && ! empty($row->user_id)) {
                    $ids[] = (string) $row->user_id;
                }
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Resolve Industry Director user IDs strictly via industry_director_assignments & circle assignments.
     *
     * @return array<int, string>
     */
    public function resolveIndustryDirectorUserIds(string $industryId): array
    {
        $ids = [];
        if ($industryId === '') {
            return [];
        }

        if (Schema::hasTable('industry_director_assignments')) {
            $cols = Schema::getColumnListing('industry_director_assignments');
            $q = DB::table('industry_director_assignments');
            if (in_array('is_active', $cols, true)) {
                $q->where('is_active', true);
            }
            $q->where(function ($sub) use ($industryId, $cols): void {
                foreach (['industry_id', 'industry_category_id', 'main_business_category_id'] as $col) {
                    if (in_array($col, $cols, true)) {
                        $sub->orWhere($col, $industryId);
                    }
                }
            });

            foreach ($q->get() as $row) {
                if (! empty($row->user_id)) {
                    $ids[] = (string) $row->user_id;
                }
                if (! empty($row->admin_user_id)) {
                    $resolved = $this->resolveUserIdFromAdminId((string) $row->admin_user_id);
                    if ($resolved !== null) {
                        $ids[] = $resolved;
                    }
                }
            }
        }

        if (Schema::hasTable('user_organizational_assignments') && Schema::hasColumn('user_organizational_assignments', 'industry_id')) {
            $rows = DB::table('user_organizational_assignments')
                ->where('industry_id', $industryId)
                ->where('is_active', true)
                ->get();
            foreach ($rows as $row) {
                $canon = $this->roleResolver->normalizeRoleKey((string) ($row->role_key ?? ''));
                if ($canon === 'industryDirector' && ! empty($row->user_id)) {
                    $ids[] = (string) $row->user_id;
                }
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Resolve Circle-scoped leadership user IDs filtered by allowed canonical roles.
     *
     * @param  array<int, string>  $allowedCanonicalRoles
     * @return array<int, string>
     */
    public function resolveCircleLeaderUserIds(string $circleId, array $allowedCanonicalRoles): array
    {
        if ($circleId === '' || empty($allowedCanonicalRoles) || ! Schema::hasTable('circles')) {
            return [];
        }

        $circle = Circle::query()->find($circleId);
        if (! $circle) {
            return [];
        }

        $ids = [];
        $cols = Schema::getColumnListing('circles');

        if (in_array('circleDirector', $allowedCanonicalRoles, true)) {
            foreach (['circle_director_user_id', 'director_user_id'] as $col) {
                if (in_array($col, $cols, true) && ! empty($circle->{$col})) {
                    $ids[] = (string) $circle->{$col};
                }
            }
        }

        if (in_array('circleFounder', $allowedCanonicalRoles, true)) {
            foreach (['founder_user_id', 'circle_founder_user_id'] as $col) {
                if (in_array($col, $cols, true) && ! empty($circle->{$col})) {
                    $ids[] = (string) $circle->{$col};
                }
            }
        }

        if (in_array('circleChair', $allowedCanonicalRoles, true) && in_array('chair_user_id', $cols, true) && ! empty($circle->chair_user_id)) {
            $ids[] = (string) $circle->chair_user_id;
        }

        if (Schema::hasTable('circle_members')) {
            $members = DB::table('circle_members')
                ->where('circle_id', $circleId)
                ->where('status', 'approved')
                ->whereNull('deleted_at')
                ->get(['user_id', 'role']);

            foreach ($members as $m) {
                $canon = $this->roleResolver->normalizeRoleKey((string) ($m->role ?? ''));
                if (in_array($canon, $allowedCanonicalRoles, true) && ! empty($m->user_id)) {
                    $ids[] = (string) $m->user_id;
                }
            }
        }

        if (Schema::hasTable('user_organizational_assignments')) {
            $rows = DB::table('user_organizational_assignments')
                ->where('circle_id', $circleId)
                ->where('is_active', true)
                ->get();
            foreach ($rows as $row) {
                $canon = $this->roleResolver->normalizeRoleKey((string) ($row->role_key ?? ''));
                if (in_array($canon, $allowedCanonicalRoles, true) && ! empty($row->user_id)) {
                    $ids[] = (string) $row->user_id;
                }
            }
        }

        if (Schema::hasTable('finance_leader_tenures')) {
            $tenures = DB::table('finance_leader_tenures')
                ->where('circle_id', $circleId)
                ->where('status', 'active')
                ->get(['user_id', 'role']);
            foreach ($tenures as $t) {
                $canon = $this->roleResolver->normalizeRoleKey((string) ($t->role ?? 'circleDirector'));
                if (in_array($canon, $allowedCanonicalRoles, true) && ! empty($t->user_id)) {
                    $ids[] = (string) $t->user_id;
                }
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Build complete 14-role notification flow configuration with backward compatibility.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getDefaultRoleConfig(): array
    {
        return [
            'super_admin' => [
                'role_label' => 'Super Admin (App Owner)',
                'description' => 'Global governance, critical approvals, security alerts, and L2 escalations.',
                'events' => [
                    ['key' => 'withdrawal_requests', 'label' => 'Leader Wallet Withdrawal Approvals', 'category' => 'finance', 'enabled' => true, 'critical' => true],
                    ['key' => 'milestone_awards', 'label' => 'Milestone Cash Bonus Approvals', 'category' => 'finance', 'enabled' => true],
                    ['key' => 'offline_payments', 'label' => 'Offline Payment Verifications', 'category' => 'finance', 'enabled' => true, 'critical' => true],
                    ['key' => 'role_matrix_modified', 'label' => 'Role Capability Matrix Changes', 'category' => 'security', 'enabled' => true, 'critical' => true],
                    ['key' => 'account_deletion_request', 'label' => 'Account Deletion Requests', 'category' => 'security', 'enabled' => true, 'critical' => true],
                    ['key' => 'compliance_warnings', 'label' => 'L2 Escalations & Compliance Warnings', 'category' => 'compliance', 'enabled' => true, 'critical' => true],
                    ['key' => 'circle_charters', 'label' => 'New Circle Chartered Anywhere', 'category' => 'governance', 'enabled' => true],
                ],
            ],
            'global_founder' => [
                'role_label' => 'Global Founder',
                'description' => 'Global strategic performance, major escalations, leadership appointments, and official announcements.',
                'events' => [
                    ['key' => 'website_nomination_submitted', 'label' => 'Website Leadership Nominations', 'category' => 'governance', 'enabled' => true],
                    ['key' => 'leadership_tenure_appointed', 'label' => 'Major Leadership Appointments', 'category' => 'governance', 'enabled' => true],
                    ['key' => 'circle_charters', 'label' => 'New Circle Chartered Globally', 'category' => 'governance', 'enabled' => true],
                    ['key' => 'compliance_warnings', 'label' => 'Critical Compliance & SLA Escalations', 'category' => 'compliance', 'enabled' => true, 'critical' => true],
                    ['key' => 'territory_scale_milestone', 'label' => 'Territory & Platform Scale Milestones', 'category' => 'performance', 'enabled' => true],
                ],
            ],
            'country_director' => [
                'role_label' => 'Country Director (EED)',
                'description' => 'Country-scoped governance, offline payments, certifications, and regional escalations.',
                'events' => [
                    ['key' => 'leader_interest_submitted', 'label' => 'Leader Interest Submissions in Country', 'category' => 'governance', 'enabled' => true],
                    ['key' => 'offline_payments', 'label' => 'Country Offline Payment Verifications', 'category' => 'finance', 'enabled' => true, 'critical' => true],
                    ['key' => 'circle_proposal_submitted', 'label' => 'Build Your Own Circle Proposals', 'category' => 'governance', 'enabled' => true],
                    ['key' => 'coin_claim_pending', 'label' => 'Coin Claim & Intro Claim Approvals', 'category' => 'membership', 'enabled' => true],
                    ['key' => 'compliance_warnings', 'label' => 'Country SLA & Report Escalations', 'category' => 'compliance', 'enabled' => true, 'critical' => true],
                ],
            ],
            'district_exec_director' => [
                'role_label' => 'District Executive Director (DED)',
                'description' => 'District circles, leadership vacancies, attendance dips, and L1 escalations.',
                'events' => [
                    ['key' => 'district_circle_chartered', 'label' => 'New Circle in District', 'category' => 'governance', 'enabled' => true],
                    ['key' => 'cd_appointed', 'label' => 'Circle Director Appointed in District', 'category' => 'governance', 'enabled' => true],
                    ['key' => 'district_scale_milestone', 'label' => 'District Scale Milestone', 'category' => 'performance', 'enabled' => true],
                    ['key' => 'circle_attendance_warning', 'label' => 'District Circle Low Attendance Warning', 'category' => 'compliance', 'enabled' => true],
                    ['key' => 'regional_sla_escalation', 'label' => 'District SLA Escalation', 'category' => 'compliance', 'enabled' => true, 'critical' => true],
                ],
            ],
            'industry_director' => [
                'role_label' => 'Industry Director (ID)',
                'description' => 'Assigned L1-L4 industry verticals, Stage-2 clearances, and cross-circle deals.',
                'events' => [
                    ['key' => 'industry_category_conflict', 'label' => 'Stage-2 Industry Clearance Requests', 'category' => 'industry', 'enabled' => true],
                    ['key' => 'cross_circle_deal', 'label' => 'Cross-Circle Industry Collaboration Deal', 'category' => 'industry', 'enabled' => true],
                    ['key' => 'unmatched_industry_ask', 'label' => 'Unmatched Industry Ask (>72h)', 'category' => 'industry', 'enabled' => true],
                ],
            ],
            'circle_founder' => [
                'role_label' => 'Circle Founder (CF)',
                'description' => 'Founded circle growth, compliance, and backup leadership approvals.',
                'events' => [
                    ['key' => 'founder_speed_milestone', 'label' => 'Founder Speed & Scale Bonus Milestones', 'category' => 'finance', 'enabled' => true],
                    ['key' => 'circle_scale_milestone', 'label' => 'Circle Scale Milestone', 'category' => 'performance', 'enabled' => true],
                    ['key' => 'peer_join_request', 'label' => 'New Peer Join Request in Circle', 'category' => 'membership', 'enabled' => true],
                    ['key' => 'overdue_report_compliance', 'label' => 'Overdue Circle Report Compliance', 'category' => 'compliance', 'enabled' => true, 'critical' => true],
                ],
            ],
            'circle_director' => [
                'role_label' => 'Circle Director (CD)',
                'description' => 'Circle operations, Stage-1 join approvals, visitor/impact verifications, and reports.',
                'events' => [
                    ['key' => 'peer_join_request', 'label' => 'Stage-1 Peer Join Requests', 'category' => 'membership', 'enabled' => true],
                    ['key' => 'visitor_registration_pending', 'label' => 'Visitor Registration Approvals', 'category' => 'events', 'enabled' => true],
                    ['key' => 'life_impact_pending', 'label' => 'Life Impact Verifications', 'category' => 'events', 'enabled' => true],
                    ['key' => 'attendance_health_alert', 'label' => 'Circle Attendance Health Alert', 'category' => 'performance', 'enabled' => true],
                    ['key' => 'weekly_report_due', 'label' => 'Weekly Leadership Report Due', 'category' => 'compliance', 'enabled' => true],
                ],
            ],
            'circle_chair' => [
                'role_label' => 'Circle Chair',
                'description' => 'Circle cross-committee oversight, unmatched asks, and report reminders (non-financial).',
                'events' => [
                    ['key' => 'unmatched_industry_ask', 'label' => 'Unmatched Circle Collaboration/Referral Ask', 'category' => 'industry', 'enabled' => true],
                    ['key' => 'weekly_report_due', 'label' => 'Weekly Circle Report Window', 'category' => 'compliance', 'enabled' => true],
                    ['key' => 'cross_circle_deal', 'label' => 'High-Value Circle Collaboration Deal', 'category' => 'industry', 'enabled' => true],
                ],
            ],
            'chair_business_growth' => [
                'role_label' => 'Chair - Business Growth Committee',
                'description' => 'Business growth asks, cross-circle deals, and report awareness (non-financial).',
                'events' => [
                    ['key' => 'unmatched_industry_ask', 'label' => 'Unmatched Collaboration/Referral Ask', 'category' => 'industry', 'enabled' => true],
                    ['key' => 'cross_circle_deal', 'label' => 'Cross-Circle Collaboration Deal', 'category' => 'industry', 'enabled' => true],
                    ['key' => 'weekly_report_due', 'label' => 'Weekly Circle Report Window', 'category' => 'compliance', 'enabled' => true],
                ],
            ],
            'chair_membership' => [
                'role_label' => 'Chair - Membership Growth Committee',
                'description' => 'Membership renewals, peer health flags, open category nominations (non-financial).',
                'events' => [
                    ['key' => 'peer_renewal_due', 'label' => 'Peer Renewal Due (30d/7d)', 'category' => 'membership', 'enabled' => true],
                    ['key' => 'peer_health_flagged', 'label' => 'Peer Health Flagged (Needs Attention)', 'category' => 'performance', 'enabled' => true],
                    ['key' => 'open_category_nomination', 'label' => 'Open Category Peer Nomination', 'category' => 'membership', 'enabled' => true],
                ],
            ],
            'chair_events_programs' => [
                'role_label' => 'Chair - Events & Impacts Committee',
                'description' => 'Visitor approvals, cross-circle event requests, attendance dips (non-financial).',
                'events' => [
                    ['key' => 'visitor_registration_pending', 'label' => 'Visitor Registration Pending', 'category' => 'events', 'enabled' => true],
                    ['key' => 'event_join_request_pending', 'label' => 'Cross-Circle Event Join Requests', 'category' => 'events', 'enabled' => true],
                    ['key' => 'circle_attendance_dip', 'label' => 'Circle Attendance Dip Alert', 'category' => 'performance', 'enabled' => true],
                ],
            ],
            'vice_chair' => [
                'role_label' => 'Vice Chair',
                'description' => 'Deputy circle operations: report windows, attendance dips, and retention awareness.',
                'events' => [
                    ['key' => 'weekly_report_due', 'label' => 'Weekly Report Backup Window', 'category' => 'compliance', 'enabled' => true],
                    ['key' => 'circle_attendance_dip', 'label' => 'Circle Attendance Dip Alert', 'category' => 'performance', 'enabled' => true],
                    ['key' => 'peer_renewal_due', 'label' => 'Peer Renewal Awareness', 'category' => 'membership', 'enabled' => true],
                ],
            ],
            'secretary' => [
                'role_label' => 'Secretary',
                'description' => 'Circle report compliance, meeting roster, and visitor awareness (non-financial).',
                'events' => [
                    ['key' => 'weekly_report_due', 'label' => 'Weekly Leadership Report Due', 'category' => 'compliance', 'enabled' => true],
                    ['key' => 'overdue_report_compliance', 'label' => 'Overdue Report Compliance Alert', 'category' => 'compliance', 'enabled' => true, 'critical' => true],
                    ['key' => 'visitor_registration_pending', 'label' => 'Visitor Roster Awareness', 'category' => 'events', 'enabled' => true],
                ],
            ],
            'committee_leader' => [
                'role_label' => 'Committee Leader',
                'description' => 'Assigned committee outreach, tenure alerts, and targeted circulars (non-financial).',
                'events' => [
                    ['key' => 'official_circle_circular', 'label' => 'Targeted Leadership Circulars', 'category' => 'governance', 'enabled' => true],
                    ['key' => 'unmatched_industry_ask', 'label' => 'Committee Matched Ask Outreach', 'category' => 'industry', 'enabled' => true],
                ],
            ],
        ];
    }

    /**
     * Check if a user's quiet hours suppress non-critical push notifications right now.
     */
    public function isWithinQuietHours(string $userId, string $type, string $priority, string $deliveryTier = ''): bool
    {
        if ($this->isCriticalNotification($type, $priority, $deliveryTier)) {
            return false;
        }

        if (! Schema::hasTable('app_configs')) {
            return false;
        }

        $raw = DB::table('app_configs')
            ->where('key', 'leader_notification_user_prefs_'.$userId)
            ->value('value');

        if (! $raw) {
            return false;
        }

        $prefs = json_decode((string) $raw, true);
        if (! is_array($prefs) || empty($prefs['quiet_hours_enabled'])) {
            return false;
        }

        $tz = (string) ($prefs['timezone'] ?? config('app.timezone', 'Asia/Kolkata'));
        $start = (string) ($prefs['quiet_hours_start'] ?? '22:00');
        $end = (string) ($prefs['quiet_hours_end'] ?? '07:00');

        try {
            $nowHm = now($tz)->format('H:i');
            if ($start <= $end) {
                return $nowHm >= $start && $nowHm <= $end;
            }

            return $nowHm >= $start || $nowHm <= $end;
        } catch (\Throwable) {
            return false;
        }
    }

    private function resolveUserIdFromAdminId(string $adminUserId): ?string
    {
        if ($adminUserId === '') {
            return null;
        }

        if (Schema::hasTable('users') && User::query()->where('id', $adminUserId)->exists()) {
            return $adminUserId;
        }

        if (Schema::hasTable('admin_users') && Schema::hasTable('users')) {
            $admin = AdminUser::query()->find($adminUserId);
            if ($admin && ! empty($admin->email)) {
                $user = User::query()->whereRaw('LOWER(email) = ?', [strtolower(trim((string) $admin->email))])->first();
                if ($user) {
                    return (string) $user->id;
                }
            }
        }

        return null;
    }
}
