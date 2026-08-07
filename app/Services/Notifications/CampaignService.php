<?php

namespace App\Services\Notifications;

use App\Models\Event;
use App\Models\Notifications\NotificationCampaign;
use App\Models\Notifications\NotificationCampaignRun;
use App\Models\Post;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Support\Str;

class CampaignService
{
    public function __construct(private NotificationService $notifications, private NotificationAudienceService $audiences) {}

    public function runCampaign(NotificationCampaign $campaign): NotificationCampaignRun
    {
        $run = NotificationCampaignRun::create(['campaign_id' => $campaign->id, 'run_type' => 'scheduled', 'status' => 'running', 'started_at' => now()]);
        if (! $campaign->is_active) {
            $run->update(['status' => 'skipped', 'finished_at' => now()]);

            return $run;
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

            // Resolve dynamic placeholders based on campaign code
            if ($campaign->code === 'requirement_lead' || $campaign->code === 'pending_requirement_reminder') {
                $latestRequirement = Requirement::where('status', 'active')
                    ->where('user_id', '!=', $user->id)
                    ->latest()
                    ->first();
                if ($latestRequirement) {
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
            } elseif (in_array($campaign->code, ['upcoming_event_reminder', 'event_starting_now', 'post_event_feedback'], true)) {
                $latestEvent = Event::where('start_at', '>=', now())
                    ->orderBy('start_at', 'asc')
                    ->first();
                if (! $latestEvent) {
                    $latestEvent = Event::latest()->first();
                }
                if ($latestEvent) {
                    $eventTitle = $latestEvent->title;
                    $eventDate = $latestEvent->start_at->format('d M Y');
                    $eventId = $latestEvent->id;

                    $bannerUrl = $latestEvent->banner_url;
                    if (is_string($bannerUrl) && trim($bannerUrl) !== '') {
                        $bannerUrl = trim($bannerUrl);
                        if (! str_starts_with($bannerUrl, 'http://') && ! str_starts_with($bannerUrl, 'https://')) {
                            if (str_starts_with($bannerUrl, '/')) {
                                $bannerUrl = url($bannerUrl);
                            } else {
                                $bannerUrl = url('/api/v1/files/'.$bannerUrl);
                            }
                        }
                    } else {
                        $bannerUrl = null;
                    }
                    $eventBannerUrl = $bannerUrl;
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

            $payloadData = ['screen' => $campaign->tap_screen, 'campaign_id' => $campaign->id];
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

            $n = $this->notifications->sendToUser($user, $campaign->code, $title, $body, $payloadData, ['campaign' => $campaign, 'channel' => $campaign->channel, 'priority' => $campaign->priority, 'screen' => $campaign->tap_screen, 'dedupe_key' => $campaign->code.':'.$user->id.':'.now()->toDateString()]);
            $n ? $sent++ : $skipped++;
        }
        $run->update(['status' => 'finished', 'audience_count' => $users->count(), 'sent_count' => $sent, 'skipped_count' => $skipped, 'finished_at' => now()]);

        return $run;
    }

    private function audienceFor(NotificationCampaign $campaign)
    {
        return match ($campaign->audience_type) {
            'inactive_users' => $this->audiences->inactiveUsers(), 'incomplete_profile' => $this->audiences->incompleteProfileUsers(), 'non_pro_users' => $this->audiences->nonProUsers(), 'subscription_expiring' => $this->audiences->subscriptionExpiringUsers(), 'unclaimed_coins' => $this->audiences->usersWithUnclaimedCoins(), default => User::limit(100)->get()
        };
    }

    public function runRequirementLeadCampaign()
    {
        return $this->runByCode('requirement_lead');
    }

    public function runPendingRequirementReminder()
    {
        return $this->runByCode('pending_requirement_reminder');
    }

    public function runCircleActivityCampaign()
    {
        return $this->runByCode('circle_activity');
    }

    public function runPeopleToConnectCampaign()
    {
        return $this->runByCode('people_to_connect');
    }

    public function runUpcomingEventReminderCampaign()
    {
        return $this->runByCode('upcoming_event_reminder');
    }

    public function runEventStartingNowCampaign()
    {
        return $this->runByCode('event_starting_now');
    }

    public function runPostEventFeedbackCampaign()
    {
        return $this->runByCode('post_event_feedback');
    }

    public function runUnclaimedCoinsCampaign()
    {
        return $this->runByCode('unclaimed_coins');
    }

    public function runReferralTestimonialRewardCampaign()
    {
        return $this->runByCode('referral_testimonial_reward');
    }

    public function runWeeklyDigestCampaign()
    {
        return $this->runByCode('weekly_digest');
    }

    private function runByCode(string $code)
    {
        $c = NotificationCampaign::where('code', $code)->first();

        return $c ? $this->runCampaign($c) : null;
    }
}
