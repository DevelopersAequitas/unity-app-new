<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Event;
use App\Models\Notifications\NotificationCampaign;
use App\Models\Notifications\NotificationCampaignRun;
use App\Models\Post;
use App\Models\Requirement;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Models\User;
use App\Services\Events\EventFeedbackService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CampaignService
{
    public function __construct(
        private NotificationService $notifications,
        private NotificationAudienceService $audiences
    ) {}

    public function runCampaign(NotificationCampaign $campaign): NotificationCampaignRun
    {
        $run = NotificationCampaignRun::create([
            'campaign_id' => $campaign->id,
            'run_type' => 'scheduled',
            'status' => 'running',
            'started_at' => now(),
        ]);

        if (! $campaign->is_active) {
            $run->update(['status' => 'skipped', 'finished_at' => now()]);

            return $run;
        }

        if ($campaign->code === 'post_event_feedback') {
            $feedbackService = app(EventFeedbackService::class);
            $res = $feedbackService->sendPostEventFeedbackNotifications();
            $run->update([
                'status' => 'finished',
                'audience_count' => $res['notifications_sent'] + $res['skipped'],
                'sent_count' => $res['notifications_sent'],
                'skipped_count' => $res['skipped'],
                'finished_at' => now(),
            ]);

            return $run;
        }

        // When campaign is 'event_starting_now' (title: "[Event Title] is live now"),
        // ONLY execute and send if an event is ACTUALLY live or in progress right now.
        // If the event is in the future or not currently live, STOP immediately and send zero notifications.
        $liveEvent = null;
        if ($campaign->code === 'event_starting_now') {
            $liveEvent = $this->findCurrentLiveEvent();
            if (! $liveEvent) {
                $run->update([
                    'status' => 'skipped',
                    'audience_count' => 0,
                    'sent_count' => 0,
                    'skipped_count' => 0,
                    'finished_at' => now(),
                ]);

                return $run;
            }
        }

        $users = $this->audienceFor($campaign);
        $sent = 0;
        $skipped = 0;

        foreach ($users as $user) {
            $displayName = trim((string) ($user->display_name ?? '')) ?: trim(((string) ($user->first_name ?? '')).' '.((string) ($user->last_name ?? ''))) ?: (string) ($user->name ?? 'Peer');
            // Initialize dynamic values
            $personName = $displayName;
            $requirementTitle = 'a relevant requirement';
            $eventTitle = 'Upcoming Event';
            $circleName = 'your Circle';
            $eventDate = now()->format('d M Y');
            $xVal = '1';
            $postPreview = 'Check out the latest updates';

            $eventBannerUrl = null;
            $eventId = null;
            $requirementId = null;
            $requirementOwnerId = null;

            // Resolve dynamic placeholders based on campaign code
            if ($campaign->code === 'requirement_lead' || $campaign->code === 'pending_requirement_reminder') {
                $latestRequirement = Requirement::where('status', 'active')
                    ->where('user_id', '!=', $user->id)
                    ->latest()
                    ->first();
                if ($latestRequirement) {
                    $requirementId = (string) $latestRequirement->id;
                    $requirementOwnerId = (string) $latestRequirement->user_id;
                    $creator = $latestRequirement->user;
                    if ($creator) {
                        $personName = trim((string) ($creator->display_name ?? '')) ?: trim(((string) ($creator->first_name ?? '')).' '.((string) ($creator->last_name ?? ''))) ?: (string) ($creator->name ?? 'A member');
                    }
                    $requirementTitle = $latestRequirement->subject;
                }
                if ($campaign->code === 'pending_requirement_reminder') {
                    $pendingCount = Requirement::where('status', 'active')
                        ->where('user_id', '!=', $user->id)
                        ->count();
                    $xVal = (string) ($pendingCount ?: 1);
                }
            } elseif ($campaign->code === 'new_post_activity_circle') {
                $latestPost = Post::where('user_id', '!=', $user->id)
                    ->where('visibility', 'public')
                    ->where('status', 'active')
                    ->where('is_deleted', false)
                    ->latest()
                    ->first();
                if ($latestPost) {
                    $author = $latestPost->user;
                    if ($author) {
                        $personName = trim((string) ($author->display_name ?? '')) ?: trim(((string) ($author->first_name ?? '')).' '.((string) ($author->last_name ?? ''))) ?: (string) ($author->name ?? 'A member');
                    }
                    $postPreview = Str::limit(strip_tags($latestPost->content_text ?? ''), 50) ?: 'published a new post';
                }
            } elseif ($campaign->code === 'circle_activity') {
                $latestCirclePost = Post::whereNotNull('circle_id')
                    ->where('user_id', '!=', $user->id)
                    ->where('status', 'active')
                    ->where('is_deleted', false)
                    ->latest()
                    ->first();
                if ($latestCirclePost) {
                    $author = $latestCirclePost->user;
                    if ($author) {
                        $personName = trim((string) ($author->display_name ?? '')) ?: trim(((string) ($author->first_name ?? '')).' '.((string) ($author->last_name ?? ''))) ?: (string) ($author->name ?? 'A member');
                    }
                    if ($latestCirclePost->circle) {
                        $circleName = $latestCirclePost->circle->name;
                    }
                }
            } elseif ($campaign->code === 'people_to_connect') {
                $connectionCount = User::where('id', '!=', $user->id)
                    ->where('status', 'active')
                    ->where('city', $user->city)
                    ->count();
                if ($connectionCount === 0) {
                    $connectionCount = User::where('id', '!=', $user->id)
                        ->where('status', 'active')
                        ->count();
                }
                $xVal = (string) min(10, max(3, $connectionCount));
            } elseif ($campaign->code === 'upcoming_event_reminder') {
                // Upcoming event reminder ONLY targets future/upcoming events
                $latestEvent = Event::where('start_at', '>=', now())
                    ->orderBy('start_at', 'asc')
                    ->first();
                if (! $latestEvent) {
                    $latestEvent = Event::latest()->first();
                }
                if ($latestEvent) {
                    $eventTitle = $latestEvent->title;
                    $eventDate = $latestEvent->start_at instanceof Carbon
                        ? $latestEvent->start_at->format('d M Y')
                        : ($latestEvent->start_at ? Carbon::parse($latestEvent->start_at)->format('d M Y') : now()->format('d M Y'));
                    $eventId = $latestEvent->id;
                    $eventBannerUrl = $this->resolveBannerUrl($latestEvent->banner_url);
                }
            } elseif ($campaign->code === 'event_starting_now') {
                // Event Starting Now ONLY targets an event that is currently live right now
                $targetEvent = $liveEvent ?? $this->findCurrentLiveEvent();
                if ($targetEvent) {
                    $eventTitle = $targetEvent->title;
                    $eventDate = $targetEvent->start_at instanceof Carbon
                        ? $targetEvent->start_at->format('d M Y')
                        : ($targetEvent->start_at ? Carbon::parse($targetEvent->start_at)->format('d M Y') : now()->format('d M Y'));
                    $eventId = $targetEvent->id;
                    $eventBannerUrl = $this->resolveBannerUrl($targetEvent->banner_url);
                } else {
                    // Event is not live: skip sending to user immediately
                    $skipped++;

                    continue;
                }
            } elseif ($campaign->code === 'unclaimed_coins') {
                $xVal = (string) max(10, (int) ($user->coin_balance ?? 0));
            }

            $placeholders = [
                'name' => $displayName,
                'person' => $personName,
                'requirement_title' => $requirementTitle,
                'event_title' => $eventTitle,
                'circle_name' => $circleName,
                'date' => $eventDate,
                'x' => $xVal,
                'post_preview_content' => $postPreview,
                'amount' => '₹0',
                'status' => 'Active',
                'badge_name' => 'Member',
            ];
            $title = $this->notifications->renderTemplate($campaign->title_template, $placeholders);
            $body = $this->notifications->renderTemplate($campaign->body_template, $placeholders);

            $isRequirementMatch = in_array($campaign->code, ['requirement_lead', 'pending_requirement_reminder'], true);
            $notificationScreen = $isRequirementMatch ? '/post-details' : $campaign->tap_screen;
            $payloadData = [
                'navigation_screen' => $notificationScreen,
                'screen' => $notificationScreen,
                'tap_destination' => $notificationScreen,
                'campaign_id' => $campaign->id,
            ];
            if ($isRequirementMatch) {
                $payloadData['notification_type'] = 'requirement_match';
                $payloadData['activity_type'] = 'requirement';
                if ($requirementId !== null) {
                    $payloadData['requirement_id'] = $requirementId;
                }
                if ($requirementOwnerId !== null) {
                    $payloadData['profile_id'] = $requirementOwnerId;
                    $payloadData['member_id'] = $requirementOwnerId;
                    $payloadData['profile_screen'] = '/member-profile';
                    $payloadData['post_screen'] = '/post-details';
                    $payloadData['notification_flow'] = 'profile_then_post';
                }
            }
            if ($eventBannerUrl !== null) {
                $payloadData['event_banner'] = $eventBannerUrl;
                $payloadData['image_url'] = $eventBannerUrl;
                $payloadData['banner_url'] = $eventBannerUrl;
            }
            if ($eventId !== null) {
                $payloadData['event_id'] = (string) $eventId;
                $payloadData['reference_type'] = 'event';
                $payloadData['reference_id'] = (string) $eventId;
            }

            $dedupeKey = ($campaign->code === 'event_starting_now' && $eventId !== null)
                ? $campaign->code.':'.$user->id.':'.$eventId
                : $campaign->code.':'.$user->id.':'.now()->toDateString();

            $n = $this->notifications->sendToUser(
                $user,
                $campaign->code,
                $title,
                $body,
                $payloadData,
                [
                    'campaign' => $campaign,
                    'channel' => $campaign->channel,
                    'priority' => $campaign->priority,
                    'screen' => $notificationScreen,
                    'dedupe_key' => $dedupeKey,
                ]
            );
            $n ? $sent++ : $skipped++;
        }

        $run->update([
            'status' => 'finished',
            'audience_count' => $users->count(),
            'sent_count' => $sent,
            'skipped_count' => $skipped,
            'finished_at' => now(),
        ]);

        return $run;
    }

    /**
     * Locate the event that is currently live or in progress right now.
     * Checks speed networking active sessions first, then general live events.
     * Returns null if no event is currently live.
     */
    public function findCurrentLiveEvent(): ?Event
    {
        $now = Carbon::now();

        // 1. Check for live Speed Networking / Speed Collaboration events
        if (class_exists(SpeedNetworkingEvent::class) && Schema::hasTable('speed_networking_events')) {
            try {
                $snLive = SpeedNetworkingEvent::query()
                    ->whereNotIn('status', [
                        SpeedNetworkingEvent::STATUS_COMPLETED,
                        'ended',
                        'cancelled',
                        'canceled',
                        SpeedNetworkingEvent::STATUS_DRAFT,
                    ])
                    ->whereNull('actual_end_time')
                    ->where(function ($q) use ($now): void {
                        $q->where('status', SpeedNetworkingEvent::STATUS_IN_PROGRESS)
                            ->orWhere('status', SpeedNetworkingEvent::STATUS_LOBBY_OPEN)
                            ->orWhere(function ($sub) use ($now): void {
                                $sub->whereNotNull('actual_start_time')
                                    ->where('actual_start_time', '<=', $now);
                            })
                            ->orWhere(function ($sub) use ($now): void {
                                $sub->whereNotNull('scheduled_start_time')
                                    ->where('scheduled_start_time', '<=', $now)
                                    ->where(function ($endSub) use ($now): void {
                                        $endSub->whereNull('scheduled_end_time')
                                            ->orWhere('scheduled_end_time', '>', $now);
                                    });
                            });
                    })
                    ->orderByRaw("CASE WHEN status = 'in_progress' THEN 1 WHEN status = 'lobby_open' THEN 2 ELSE 3 END")
                    ->latest('actual_start_time')
                    ->first();

                if ($snLive) {
                    $baseEvent = Event::find($snLive->id);
                    if (! $baseEvent) {
                        $baseEvent = Event::where('title', $snLive->title)->latest()->first();
                    }
                    if ($baseEvent) {
                        return $baseEvent;
                    }

                    $synthetic = new Event([
                        'title' => $snLive->title,
                        'start_at' => $snLive->scheduled_start_time ?? $snLive->actual_start_time ?? $now,
                        'banner_url' => $snLive->banner_url,
                    ]);
                    $synthetic->id = (string) $snLive->id;

                    return $synthetic;
                }
            } catch (\Throwable) {
                // Ignore and proceed to fallback
            }
        }

        // 2. Check general Event records in events table
        try {
            $hasStatusCol = Schema::hasColumn('events', 'status');
            $hasActiveCol = Schema::hasColumn('events', 'is_active');
            $candidates = Event::query()
                ->when($hasActiveCol, function ($q): void {
                    $q->where('is_active', true);
                })
                ->when($hasStatusCol, function ($q): void {
                    $q->whereNotIn('status', ['cancelled', 'completed', 'ended', 'draft']);
                })
                ->whereNotNull('start_at')
                ->where('start_at', '<=', $now)
                ->where(function ($q) use ($now): void {
                    $q->where(function ($sub) use ($now): void {
                        $sub->whereNotNull('end_at')
                            ->where('end_at', '>', $now);
                    })->orWhere(function ($sub) use ($now): void {
                        $sub->whereNull('end_at')
                            ->where('start_at', '>=', $now->copy()->subHours(2));
                    });
                })
                ->latest('start_at')
                ->get();

            foreach ($candidates as $candidate) {
                // If it is a speed networking event, verify it's not still waiting / scheduled in the future
                if ($candidate->isSpeedNetworking()) {
                    if (class_exists(SpeedNetworkingEvent::class) && Schema::hasTable('speed_networking_events')) {
                        $sn = SpeedNetworkingEvent::find($candidate->id);
                        if ($sn) {
                            $isLive = in_array(strtolower((string) $sn->status), [
                                SpeedNetworkingEvent::STATUS_IN_PROGRESS,
                                SpeedNetworkingEvent::STATUS_LOBBY_OPEN,
                            ], true) || ($sn->actual_start_time && $sn->actual_start_time->lte($now) && empty($sn->actual_end_time));

                            if (! $isLive) {
                                continue;
                            }
                        }
                    }
                }

                return $candidate;
            }
        } catch (\Throwable) {
            // Ignore and fall through
        }

        return null;
    }

    private function resolveBannerUrl(?string $bannerUrl): ?string
    {
        if (! is_string($bannerUrl) || trim($bannerUrl) === '') {
            return null;
        }

        $bannerUrl = trim($bannerUrl);
        if (! str_starts_with($bannerUrl, 'http://') && ! str_starts_with($bannerUrl, 'https://')) {
            if (str_starts_with($bannerUrl, '/')) {
                return url($bannerUrl);
            }

            return url('/api/v1/files/'.$bannerUrl);
        }

        return $bannerUrl;
    }

    private function audienceFor(NotificationCampaign $campaign): Collection
    {
        return match ($campaign->audience_type) {
            'inactive_users' => $this->audiences->inactiveUsers(),
            'incomplete_profile' => $this->audiences->incompleteProfileUsers(),
            'non_pro_users' => $this->audiences->nonProUsers(),
            'subscription_expiring' => $this->audiences->subscriptionExpiringUsers(),
            'unclaimed_coins' => $this->audiences->usersWithUnclaimedCoins(),
            default => User::limit(100)->get()
        };
    }

    public function runRequirementLeadCampaign(): ?NotificationCampaignRun
    {
        return $this->runByCode('requirement_lead');
    }

    public function runPendingRequirementReminder(): ?NotificationCampaignRun
    {
        return $this->runByCode('pending_requirement_reminder');
    }

    public function runCircleActivityCampaign(): ?NotificationCampaignRun
    {
        return $this->runByCode('circle_activity');
    }

    public function runPeopleToConnectCampaign(): ?NotificationCampaignRun
    {
        return $this->runByCode('people_to_connect');
    }

    public function runUpcomingEventReminderCampaign(): ?NotificationCampaignRun
    {
        return $this->runByCode('upcoming_event_reminder');
    }

    public function runEventStartingNowCampaign(): ?NotificationCampaignRun
    {
        return $this->runByCode('event_starting_now');
    }

    public function runPostEventFeedbackCampaign(): ?NotificationCampaignRun
    {
        return $this->runByCode('post_event_feedback');
    }

    public function runUnclaimedCoinsCampaign(): ?NotificationCampaignRun
    {
        return $this->runByCode('unclaimed_coins');
    }

    public function runReferralTestimonialRewardCampaign(): ?NotificationCampaignRun
    {
        return $this->runByCode('referral_testimonial_reward');
    }

    public function runWeeklyDigestCampaign(): ?NotificationCampaignRun
    {
        return $this->runByCode('weekly_digest');
    }

    private function runByCode(string $code): ?NotificationCampaignRun
    {
        $c = NotificationCampaign::where('code', $code)->first();

        return $c ? $this->runCampaign($c) : null;
    }
}
