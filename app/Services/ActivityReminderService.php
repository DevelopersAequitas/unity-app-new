<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\UserNotificationCreated;
use App\Jobs\SendFcmNotificationJob;
use App\Models\ActivityReminderSetting;
use App\Models\AppNotification;
use App\Models\Ask\Ask;
use App\Models\BusinessDeal;
use App\Models\CircleChatMessage;
use App\Models\Connection;
use App\Models\ContactInvitation;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\P2pMeeting;
use App\Models\Post;
use App\Models\Referral;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ActivityReminderService
{
    /**
     * Process inactivity reminders for all active users or a specific user.
     *
     * @return array{
     *     processed_users: int,
     *     dispatched_reminders: int,
     *     skipped_new_users: int,
     *     skipped_cooldown: int,
     *     skipped_active: int,
     *     details: array<int, array<string, mixed>>
     * }
     */
    public function processInactivityReminders(
        ?string $targetUserId = null,
        bool $dryRun = false,
        int $cooldownDays = 3,
        int $newUserDays = 3
    ): array {
        $stats = [
            'processed_users' => 0,
            'dispatched_reminders' => 0,
            'skipped_new_users' => 0,
            'skipped_cooldown' => 0,
            'skipped_active' => 0,
            'details' => [],
        ];

        $settings = $this->getActiveSettings();
        if ($settings->isEmpty()) {
            Log::info('ActivityReminderService: No active reminder settings found.');

            return $stats;
        }

        $usersQuery = User::query()
            ->where('status', 'active');

        if ($targetUserId !== null) {
            $usersQuery->where('id', $targetUserId);
        }

        $users = $usersQuery->get();

        foreach ($users as $user) {
            $stats['processed_users']++;

            // 1. Anti-spam: New User Rule (< $newUserDays old)
            if ($this->isNewUser($user, $newUserDays)) {
                $stats['skipped_new_users']++;

                continue;
            }

            // 2. Anti-spam: Cooldown Window (received reminder within $cooldownDays)
            if ($this->isInCooldownWindow($user, $cooldownDays)) {
                $stats['skipped_cooldown']++;

                continue;
            }

            // 3. Find highest-priority pending activity where user is inactive
            $inactiveSetting = $this->findHighestPriorityInactiveActivity($user, $settings);

            if ($inactiveSetting === null) {
                $stats['skipped_active']++;

                continue;
            }

            // 4. Dispatch the reminder
            $dispatched = $this->dispatchReminder($user, $inactiveSetting, $dryRun);

            if ($dispatched) {
                $stats['dispatched_reminders']++;
                $stats['details'][] = [
                    'user_id' => $user->id,
                    'user_name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
                    'activity_key' => $inactiveSetting->activity_key,
                    'activity_type' => $inactiveSetting->activity_type,
                    'target_screen' => $inactiveSetting->target_screen,
                    'dry_run' => $dryRun,
                ];
            }
        }

        Log::info('ActivityReminderService: execution finished.', $stats);

        return $stats;
    }

    /**
     * Retrieve all active settings ordered by priority ascending (1 = highest).
     *
     * @return EloquentCollection<int, ActivityReminderSetting>
     */
    public function getActiveSettings(): EloquentCollection
    {
        return ActivityReminderSetting::query()
            ->enabled()
            ->ordered()
            ->get();
    }

    /**
     * Check if user registered recently.
     */
    public function isNewUser(User $user, int $newUserDays = 3): bool
    {
        if ($user->created_at === null) {
            return false;
        }

        return $user->created_at->greaterThan(now()->subDays($newUserDays));
    }

    /**
     * Check if user received any activity reminder within the cooldown window.
     */
    public function isInCooldownWindow(User $user, int $cooldownDays = 3): bool
    {
        return AppNotification::query()
            ->where('user_id', $user->id)
            ->where('type', 'activity_reminder')
            ->where('created_at', '>=', now()->subDays($cooldownDays))
            ->exists();
    }

    /**
     * Find the single highest-priority pending inactive activity for this user.
     */
    public function findHighestPriorityInactiveActivity(
        User $user,
        EloquentCollection $settings
    ): ?ActivityReminderSetting {
        foreach ($settings as $setting) {
            if ($this->isUserInactiveForActivity($user, $setting)) {
                return $setting;
            }
        }

        return null;
    }

    /**
     * Determine whether the user is inactive for a specific activity module.
     */
    public function isUserInactiveForActivity(User $user, ActivityReminderSetting $setting): bool
    {
        $thresholdDays = max(1, (int) $setting->threshold_days);
        $since = now()->subDays($thresholdDays);

        return match ($setting->activity_key) {
            'p2p_meeting' => ! $this->hasRecentP2pMeeting($user, $since),
            'business_referral' => ! $this->hasRecentBusinessReferral($user, $since),
            'business_deal' => ! $this->hasRecentBusinessDeal($user, $since),
            'testimonial' => ! $this->hasRecentTestimonial($user, $since),
            'success_story' => ! $this->hasRecentPost($user, $since),
            'ask' => ! $this->hasRecentAsk($user, $since),
            'circle_participation' => ! $this->hasRecentCircleParticipation($user, $since),
            'upcoming_event' => $this->isInactiveForUpcomingEvents($user, $since),
            'member_connection' => ! $this->hasRecentMemberConnection($user, $since),
            'refer_and_earn' => ! $this->hasRecentContactInvitation($user, $since),
            default => false,
        };
    }

    /**
     * Dispatch notification record, event and FCM push notification.
     */
    public function dispatchReminder(User $user, ActivityReminderSetting $setting, bool $dryRun = false): bool
    {
        if ($dryRun) {
            return true;
        }

        try {
            $dataPayload = [
                'type' => 'activity_reminder',
                'activity_type' => $setting->activity_type,
                'category' => 'engagement',
                'screen' => $setting->target_screen,
                'target_route' => $setting->target_screen,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ];

            // 1. Create in-app AppNotification record
            $appNotification = AppNotification::create([
                'user_id' => $user->id,
                'type' => 'activity_reminder',
                'category' => 'engagement',
                'title' => $setting->notification_title,
                'body' => $setting->notification_body,
                'message' => $setting->notification_body,
                'channel' => 'push',
                'priority' => 'high',
                'screen' => $setting->target_screen,
                'data' => $dataPayload,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            // 2. Broadcast real-time websocket event
            event(new UserNotificationCreated((string) $user->id, [
                'id' => (string) $appNotification->id,
                'type' => 'activity_reminder',
                'category' => 'engagement',
                'title' => (string) $appNotification->title,
                'body' => (string) $appNotification->body,
                'message' => (string) $appNotification->body,
                'payload' => $appNotification->data,
                'created_at' => optional($appNotification->created_at)->toISOString(),
            ]));

            // 3. Dispatch async FCM push notification delivery job
            $fcmData = array_merge($dataPayload, [
                'notification_id' => (string) $appNotification->id,
            ]);

            SendFcmNotificationJob::dispatch(
                (string) $user->id,
                $setting->notification_title,
                $setting->notification_body,
                $fcmData
            );

            return true;
        } catch (Throwable $e) {
            Log::error('failed_to_dispatch_activity_reminder', [
                'user_id' => $user->id,
                'setting_id' => $setting->id,
                'activity_key' => $setting->activity_key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Calculate count of inactive users per activity setting.
     *
     * @return array<string, int>
     */
    public function calculateEligibleCounts(): array
    {
        $settings = $this->getActiveSettings();
        $counts = [];

        foreach ($settings as $setting) {
            $thresholdDays = max(1, (int) $setting->threshold_days);
            $since = now()->subDays($thresholdDays);

            $count = match ($setting->activity_key) {
                'p2p_meeting' => User::query()
                    ->where('status', 'active')
                    ->where('created_at', '<=', now()->subDays(3))
                    ->whereNotExists(function ($sub) use ($since) {
                        $sub->select(DB::raw(1))
                            ->from('p2p_meetings')
                            ->where(function ($q) {
                                $q->whereColumn('p2p_meetings.initiator_user_id', 'users.id')
                                    ->orWhereColumn('p2p_meetings.peer_user_id', 'users.id');
                            })
                            ->where('created_at', '>=', $since);
                    })
                    ->count(),

                'business_referral' => User::query()
                    ->where('status', 'active')
                    ->where('created_at', '<=', now()->subDays(3))
                    ->whereNotExists(function ($sub) use ($since) {
                        $sub->select(DB::raw(1))
                            ->from('referrals')
                            ->where(function ($q) {
                                $q->whereColumn('referrals.from_user_id', 'users.id')
                                    ->orWhereColumn('referrals.to_user_id', 'users.id');
                            })
                            ->where('created_at', '>=', $since);
                    })
                    ->count(),

                'business_deal' => User::query()
                    ->where('status', 'active')
                    ->where('created_at', '<=', now()->subDays(3))
                    ->whereNotExists(function ($sub) use ($since) {
                        $sub->select(DB::raw(1))
                            ->from('business_deals')
                            ->where(function ($q) {
                                $q->whereColumn('business_deals.from_user_id', 'users.id')
                                    ->orWhereColumn('business_deals.to_user_id', 'users.id');
                            })
                            ->where('created_at', '>=', $since);
                    })
                    ->count(),

                'testimonial' => User::query()
                    ->where('status', 'active')
                    ->where('created_at', '<=', now()->subDays(3))
                    ->whereNotExists(function ($sub) use ($since) {
                        $sub->select(DB::raw(1))
                            ->from('testimonials')
                            ->whereColumn('testimonials.from_user_id', 'users.id')
                            ->where('created_at', '>=', $since);
                    })
                    ->count(),

                'success_story' => User::query()
                    ->where('status', 'active')
                    ->where('created_at', '<=', now()->subDays(3))
                    ->whereNotExists(function ($sub) use ($since) {
                        $sub->select(DB::raw(1))
                            ->from('posts')
                            ->whereColumn('posts.user_id', 'users.id')
                            ->where('created_at', '>=', $since);
                    })
                    ->count(),

                'ask' => User::query()
                    ->where('status', 'active')
                    ->where('created_at', '<=', now()->subDays(3))
                    ->whereNotExists(function ($sub) use ($since) {
                        $sub->select(DB::raw(1))
                            ->from('asks')
                            ->whereColumn('asks.user_id', 'users.id')
                            ->where('created_at', '>=', $since);
                    })
                    ->count(),

                'circle_participation' => User::query()
                    ->where('status', 'active')
                    ->where('created_at', '<=', now()->subDays(3))
                    ->whereNotExists(function ($sub) use ($since) {
                        $sub->select(DB::raw(1))
                            ->from('circle_chat_messages')
                            ->whereColumn('circle_chat_messages.sender_id', 'users.id')
                            ->where('created_at', '>=', $since);
                    })
                    ->whereNotExists(function ($sub) use ($since) {
                        $sub->select(DB::raw(1))
                            ->from('posts')
                            ->whereColumn('posts.user_id', 'users.id')
                            ->whereNotNull('circle_id')
                            ->where('created_at', '>=', $since);
                    })
                    ->count(),

                'upcoming_event' => User::query()
                    ->where('status', 'active')
                    ->where('created_at', '<=', now()->subDays(3))
                    ->whereNotExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('event_registrations')
                            ->join('events', 'events.id', '=', 'event_registrations.event_id')
                            ->whereColumn('event_registrations.user_id', 'users.id')
                            ->where('events.start_at', '>=', now())
                            ->where('events.start_at', '<=', now()->addDays(14));
                    })
                    ->count(),

                'member_connection' => User::query()
                    ->where('status', 'active')
                    ->where('created_at', '<=', now()->subDays(3))
                    ->whereNotExists(function ($sub) use ($since) {
                        $sub->select(DB::raw(1))
                            ->from('connections')
                            ->where(function ($q) {
                                $q->whereColumn('connections.requester_id', 'users.id')
                                    ->orWhereColumn('connections.addressee_id', 'users.id');
                            })
                            ->where('created_at', '>=', $since);
                    })
                    ->count(),

                'refer_and_earn' => User::query()
                    ->where('status', 'active')
                    ->where('created_at', '<=', now()->subDays(3))
                    ->whereNotExists(function ($sub) use ($since) {
                        $sub->select(DB::raw(1))
                            ->from('contact_invitations')
                            ->whereColumn('contact_invitations.user_id', 'users.id')
                            ->where('created_at', '>=', $since);
                    })
                    ->count(),

                default => 0,
            };

            $counts[$setting->id] = $count;
        }

        return $counts;
    }

    // -------------------------------------------------------------
    // Activity source checks
    // -------------------------------------------------------------

    private function hasRecentP2pMeeting(User $user, Carbon $since): bool
    {
        return P2pMeeting::query()
            ->where(function ($q) use ($user) {
                $q->where('initiator_user_id', $user->id)
                    ->orWhere('peer_user_id', $user->id);
            })
            ->where('created_at', '>=', $since)
            ->exists();
    }

    private function hasRecentBusinessReferral(User $user, Carbon $since): bool
    {
        return Referral::query()
            ->where(function ($q) use ($user) {
                $q->where('from_user_id', $user->id)
                    ->orWhere('to_user_id', $user->id);
            })
            ->where('created_at', '>=', $since)
            ->exists();
    }

    private function hasRecentBusinessDeal(User $user, Carbon $since): bool
    {
        return BusinessDeal::query()
            ->where(function ($q) use ($user) {
                $q->where('from_user_id', $user->id)
                    ->orWhere('to_user_id', $user->id);
            })
            ->where('created_at', '>=', $since)
            ->exists();
    }

    private function hasRecentTestimonial(User $user, Carbon $since): bool
    {
        return Testimonial::query()
            ->where('from_user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->exists();
    }

    private function hasRecentPost(User $user, Carbon $since): bool
    {
        return Post::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('is_deleted', false)
            ->where('created_at', '>=', $since)
            ->exists();
    }

    private function hasRecentAsk(User $user, Carbon $since): bool
    {
        return Ask::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->exists();
    }

    private function hasRecentCircleParticipation(User $user, Carbon $since): bool
    {
        $hasMessage = CircleChatMessage::query()
            ->where('sender_id', $user->id)
            ->where('created_at', '>=', $since)
            ->exists();

        if ($hasMessage) {
            return true;
        }

        return Post::query()
            ->where('user_id', $user->id)
            ->whereNotNull('circle_id')
            ->where('status', 'active')
            ->where('is_deleted', false)
            ->where('created_at', '>=', $since)
            ->exists();
    }

    private function isInactiveForUpcomingEvents(User $user, Carbon $since): bool
    {
        // 1. If registered for an upcoming event in next 14 days, user is active
        $hasUpcomingRegistration = EventRegistration::query()
            ->where('user_id', $user->id)
            ->whereHas('event', function ($q) {
                $q->where('start_at', '>=', now())
                    ->where('start_at', '<=', now()->addDays(14));
            })
            ->exists();

        if ($hasUpcomingRegistration) {
            return false;
        }

        // 2. If registered for any event recently within threshold days, user is active
        $hasRecentRegistration = EventRegistration::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->exists();

        return ! $hasRecentRegistration;
    }

    private function hasRecentMemberConnection(User $user, Carbon $since): bool
    {
        return Connection::query()
            ->where(function ($q) use ($user) {
                $q->where('requester_id', $user->id)
                    ->orWhere('addressee_id', $user->id);
            })
            ->where('created_at', '>=', $since)
            ->exists();
    }

    private function hasRecentContactInvitation(User $user, Carbon $since): bool
    {
        return ContactInvitation::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->exists();
    }
}
