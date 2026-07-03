<?php

namespace App\Enums;

enum NotificationType: string
{
    case NEW_POST = 'new_post'; case USER_MENTION = 'user_mention'; case SHARE_POST = 'share_post'; case PEER_PROFILE_COMPLETION = 'peer_profile_completion'; case BIRTHDAY_ANNIVERSARY = 'birthday_anniversary'; case INACTIVE_CONNECTION_NUDGE = 'inactive_connection_nudge';
    case NEW_MEMBER_REGISTRATION = 'new_member_registration'; case NEW_EVENT_ANNOUNCEMENT = 'new_event_announcement'; case EVENT_LIVE_REMINDER = 'event_live_reminder'; case POST_EVENT_FEEDBACK = 'post_event_feedback'; case CHAT_MESSAGE = 'chat_message'; case CIRCLE_JOIN_REQUEST = 'circle_join_request'; case CIRCLE_JOIN_APPROVED = 'circle_join_approved'; case OFFICIAL_CIRCLE_CIRCULAR = 'official_circle_circular'; case URGENT_CIRCLE_BROADCAST = 'urgent_circle_broadcast'; case CIRCLE_LEADERSHIP_STATUS = 'circle_leadership_status';
    case P2P_MEETING_REQUEST = 'p2p_meeting_request'; case P2P_MEETING_CONFIRMED = 'p2p_meeting_confirmed'; case P2P_MEETING_DECLINED = 'p2p_meeting_declined'; case P2P_POST_MEETING_OUTCOME = 'p2p_post_meeting_outcome'; case BUSINESS_DEAL_LOGGED = 'business_deal_logged'; case REFERRAL_RECEIVED = 'referral_received'; case REFERRAL_STATUS_UPDATED = 'referral_status_updated'; case TESTIMONIAL_RECEIVED = 'testimonial_received'; case TESTIMONIAL_REQUEST_AFTER_DEAL = 'testimonial_request_after_deal'; case VISITOR_REGISTRATION = 'visitor_registration'; case VISITOR_CONVERSION_NUDGE = 'visitor_conversion_nudge'; case REQUIREMENT_MATCH = 'requirement_match';
    case INACTIVITY_DAY_3 = 'inactivity_day_3'; case INACTIVITY_DAY_7 = 'inactivity_day_7'; case INACTIVITY_DAY_10 = 'inactivity_day_10'; case PROFILE_COMPLETION_REMINDER = 'profile_completion_reminder'; case PROFILE_PHOTO_NUDGE = 'profile_photo_nudge'; case NON_PRO_UPSELL = 'non_pro_upsell'; case PRO_EXPIRY = 'pro_expiry'; case LEADERBOARD_RANK_CHANGE = 'leaderboard_rank_change'; case WEEKLY_DIGEST = 'weekly_digest'; case UNCLAIMED_COINS = 'unclaimed_coins'; case COIN_MILESTONE = 'coin_milestone'; case BADGE_UNLOCKED = 'badge_unlocked';
}
