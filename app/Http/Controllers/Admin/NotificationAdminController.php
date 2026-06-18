<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Notifications\SendNotificationChannelJob;
use App\Models\Notifications\AppNotification;
use App\Models\Notifications\NotificationCampaign;
use App\Models\Notifications\NotificationCampaignRun;
use App\Models\Notifications\NotificationDeliveryLog;
use App\Models\User;
use App\Models\UserPushToken;
use App\Services\Notifications\CampaignService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class NotificationAdminController extends Controller
{
    private const CHANNELS = ['push', 'email', 'push_email', 'in_app_only'];
    private const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    public function dashboard(): View
    {
        $hasNotifications = Schema::hasTable('app_notifications');
        $hasCampaigns = Schema::hasTable('notification_campaigns');
        $hasPushTokens = Schema::hasTable('user_push_tokens');
        $hasDeliveryLogs = Schema::hasTable('notification_delivery_logs');

        $stats = [
            'total_notifications' => $hasNotifications ? AppNotification::count() : 0,
            'sent_notifications' => $hasNotifications ? AppNotification::where('status', 'sent')->count() : 0,
            'failed_notifications' => $hasNotifications ? AppNotification::where('status', 'failed')->count() : 0,
            'pending_notifications' => $hasNotifications ? AppNotification::where('status', 'pending')->count() : 0,
            'read_notifications' => $hasNotifications ? AppNotification::whereNotNull('read_at')->count() : 0,
            'clicked_notifications' => $hasNotifications ? AppNotification::whereNotNull('clicked_at')->count() : 0,
            'active_campaigns' => $hasCampaigns ? NotificationCampaign::where('is_active', true)->count() : 0,
            'inactive_campaigns' => $hasCampaigns ? NotificationCampaign::where('is_active', false)->count() : 0,
            'total_push_tokens' => $hasPushTokens ? UserPushToken::count() : 0,
            'active_push_tokens' => ($hasPushTokens && Schema::hasColumn('user_push_tokens', 'is_active')) ? UserPushToken::where('is_active', true)->count() : 0,
            'today_sent' => $hasNotifications ? AppNotification::whereDate('sent_at', today())->count() : 0,
            'today_failed' => $hasNotifications ? AppNotification::whereDate('failed_at', today())->count() : 0,
            'today_read' => $hasNotifications ? AppNotification::whereDate('read_at', today())->count() : 0,
            'today_clicked' => $hasNotifications ? AppNotification::whereDate('clicked_at', today())->count() : 0,
        ];

        $recentNotifications = $hasNotifications ? AppNotification::with('user')->latest()->limit(10)->get() : collect();
        $failedLogs = $hasDeliveryLogs ? NotificationDeliveryLog::with(['notification.user', 'user'])
            ->where('status', 'failed')
            ->latest()
            ->limit(10)
            ->get() : collect();

        return view('admin.notifications.dashboard', compact('stats', 'recentNotifications', 'failedLogs'));
    }

    public function campaigns(Request $request): View
    {
        if (! Schema::hasTable('notification_campaigns')) {
            return view('admin.notifications.campaigns.index', [
                'campaigns' => $this->emptyPaginator($request),
                'filters' => ['categories' => collect(), 'channels' => collect(), 'priorities' => self::PRIORITIES],
            ]);
        }

        $campaigns = NotificationCampaign::query()
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = '%' . $request->string('search')->toString() . '%';
                $query->where(fn (Builder $q) => $q->where('name', 'ilike', $search)->orWhere('code', 'ilike', $search));
            })
            ->when($request->filled('category'), fn (Builder $q) => $q->where('category', $request->category))
            ->when($request->filled('channel'), fn (Builder $q) => $q->where('channel', $request->channel))
            ->when($request->filled('priority'), fn (Builder $q) => $q->where('priority', $request->priority))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('is_active', $request->status === 'active'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $filters = [
            'categories' => NotificationCampaign::query()->distinct()->whereNotNull('category')->orderBy('category')->pluck('category'),
            'channels' => NotificationCampaign::query()->distinct()->whereNotNull('channel')->orderBy('channel')->pluck('channel'),
            'priorities' => self::PRIORITIES,
        ];

        return view('admin.notifications.campaigns.index', compact('campaigns', 'filters'));
    }

    public function createCampaign(): View
    {
        return view('admin.notifications.campaigns.create', [
            'campaign' => new NotificationCampaign(['channel' => 'push', 'priority' => 'medium', 'is_active' => true, 'config' => []]),
            'screens' => $this->screens(),
        ]);
    }

    public function storeCampaign(Request $request): RedirectResponse
    {
        $campaign = NotificationCampaign::create($this->campaignData($request));

        return $request->input('action') === 'preview'
            ? redirect()->route('admin.notifications.campaigns.edit', $campaign->id)->with('success', 'Campaign saved. Use Preview to render sample content.')
            : redirect()->route('admin.notifications.campaigns')->with('success', 'Campaign created successfully.');
    }

    public function editCampaign(string $id): View
    {
        return view('admin.notifications.campaigns.edit', [
            'campaign' => NotificationCampaign::findOrFail($id),
            'screens' => $this->screens(),
        ]);
    }

    public function updateCampaign(Request $request, string $id): RedirectResponse
    {
        $campaign = NotificationCampaign::findOrFail($id);
        $campaign->update($this->campaignData($request, $campaign->id));

        return redirect()->route('admin.notifications.campaigns.edit', $campaign->id)->with('success', 'Campaign updated successfully.');
    }

    public function toggleCampaign(string $id): RedirectResponse
    {
        $campaign = NotificationCampaign::findOrFail($id);
        $campaign->update(['is_active' => ! $campaign->is_active]);

        return back()->with('success', 'Campaign status updated successfully.');
    }

    public function previewCampaign(Request $request, string $id): RedirectResponse
    {
        $campaign = NotificationCampaign::findOrFail($id);
        $placeholders = $request->validate([
            'person' => ['nullable', 'string'],
            'requirement_title' => ['nullable', 'string'],
            'event_title' => ['nullable', 'string'],
            'circle_name' => ['nullable', 'string'],
            'date' => ['nullable', 'string'],
            'amount' => ['nullable', 'string'],
            'x' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'badge_name' => ['nullable', 'string'],
        ]);

        return back()->with('preview', $this->renderPreview($campaign, $placeholders));
    }

    public function runCampaign(string $id, CampaignService $campaignService): RedirectResponse
    {
        $campaign = NotificationCampaign::findOrFail($id);

        try {
            $run = $campaignService->runCampaign($campaign);
            $run->update(['run_type' => 'manual']);
        } catch (Throwable $throwable) {
            report($throwable);
            NotificationCampaignRun::create([
                'campaign_id' => $campaign->id,
                'run_type' => 'manual',
                'status' => 'queued',
                'started_at' => now(),
                'meta' => ['queued_after_error' => $throwable->getMessage()],
            ]);
        }

        return back()->with('success', 'Campaign run started successfully.');
    }

    public function seedDefaults(): RedirectResponse
    {
        foreach ($this->defaultCampaigns() as $campaign) {
            NotificationCampaign::updateOrCreate(['code' => $campaign['code']], $campaign);
        }

        return back()->with('success', 'Default notification campaigns seeded successfully.');
    }

    public function sendTestForm(Request $request): View
    {
        $userQuery = User::query()
            ->when($request->filled('user_search'), function (Builder $query) use ($request): void {
                $search = '%' . $request->string('user_search')->toString() . '%';
                $query->where(fn (Builder $q) => $q
                    ->where('display_name', 'ilike', $search)
                    ->orWhere('first_name', 'ilike', $search)
                    ->orWhere('last_name', 'ilike', $search)
                    ->orWhere('email', 'ilike', $search)
                    ->orWhere('phone', 'ilike', $search));
            });

        if (Schema::hasTable('user_push_tokens') && Schema::hasColumn('user_push_tokens', 'is_active')) {
            $userQuery->withCount(['pushTokens as active_push_tokens_count' => fn (Builder $q) => $q->where('is_active', true)]);
        }

        $users = $userQuery->orderBy('first_name')->limit(50)->get();

        $recentTests = Schema::hasTable('app_notifications')
            ? AppNotification::with('user')->where('category', 'admin_test')->latest()->limit(10)->get()
            : collect();

        return view('admin.notifications.send-test', compact('users', 'recentTests'));
    }

    public function sendTest(Request $request): RedirectResponse
    {
        $data = $this->testNotificationData($request);
        $payload = json_decode($data['data'] ?: '{}', true) ?: [];
        $channel = $data['channel'];

        if (! Schema::hasTable('app_notifications')) {
            return back()->withInput()->with('error', 'The app_notifications table is not available. Please run migrations.');
        }

        try {
            $hasActiveToken = Schema::hasTable('user_push_tokens')
                && Schema::hasColumn('user_push_tokens', 'is_active')
                && UserPushToken::where('user_id', $data['user_id'])->where('is_active', true)->exists();

            $notification = AppNotification::create([
                'user_id' => $data['user_id'],
                'type' => $data['type'],
                'category' => $data['category'],
                'title' => $data['title'],
                'body' => $data['body'],
                'channel' => $channel,
                'priority' => $data['priority'],
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'screen' => $data['screen'],
                'data' => array_merge($payload, ['screen' => $data['screen'], 'reference_id' => $data['reference_id'] ?? null]),
                'status' => ($channel === 'in_app_only' || (in_array($channel, ['push', 'push_email'], true) && ! $hasActiveToken)) ? ($channel === 'in_app_only' ? 'sent' : 'failed') : 'pending',
                'sent_at' => $channel === 'in_app_only' ? now() : null,
                'failed_at' => in_array($channel, ['push', 'push_email'], true) && ! $hasActiveToken ? now() : null,
                'failure_reason' => in_array($channel, ['push', 'push_email'], true) && ! $hasActiveToken ? 'No active push token found.' : null,
            ]);

            if (in_array($channel, ['push', 'push_email'], true) && $hasActiveToken) {
                SendNotificationChannelJob::dispatch($notification->id, 'push');
            }

            if (in_array($channel, ['email', 'push_email'], true)) {
                SendNotificationChannelJob::dispatch($notification->id, 'email');
            }

            if (Schema::hasTable('notification_delivery_logs')) {
                NotificationDeliveryLog::create([
                    'notification_id' => $notification->id,
                    'user_id' => $notification->user_id,
                    'channel' => $channel,
                    'provider' => $channel === 'in_app_only' ? 'in_app' : null,
                    'status' => in_array($channel, ['push', 'push_email'], true) && ! $hasActiveToken ? 'failed' : ($channel === 'in_app_only' ? 'sent' : 'queued'),
                    'request_payload' => $notification->dataPayload(),
                    'error_message' => in_array($channel, ['push', 'push_email'], true) && ! $hasActiveToken ? 'No active push token found.' : null,
                    'attempted_at' => now(),
                    'delivered_at' => $channel === 'in_app_only' ? now() : null,
                ]);
            }
        } catch (Throwable $throwable) {
            report($throwable);
            return back()->withInput()->with('error', 'Test notification could not be created: ' . $throwable->getMessage());
        }

        return redirect()->route('admin.notifications.send-test')->with('success', 'Test notification created/sent successfully.');
    }

    public function logs(Request $request): View
    {
        if (! Schema::hasTable('notification_delivery_logs')) {
            return view('admin.notifications.logs', [
                'logs' => $this->emptyPaginator($request),
                'campaigns' => Schema::hasTable('notification_campaigns') ? NotificationCampaign::orderBy('name')->get() : collect(),
            ]);
        }

        $logs = NotificationDeliveryLog::with(['notification.user', 'notification.campaign', 'user', 'campaign'])
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->status))
            ->when($request->filled('channel'), fn (Builder $q) => $q->where('channel', $request->channel))
            ->when($request->filled('provider'), fn (Builder $q) => $q->where('provider', $request->provider))
            ->when($request->filled('campaign_id'), fn (Builder $q) => $q->whereHas('notification', fn (Builder $n) => $n->where('campaign_id', $request->campaign_id)))
            ->when($request->filled('user_search'), fn (Builder $q) => $q->whereHas('notification.user', fn (Builder $u) => $this->applyUserSearch($u, $request->string('user_search')->toString())))
            ->when($request->filled('date_from'), fn (Builder $q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn (Builder $q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.notifications.logs', ['logs' => $logs, 'campaigns' => Schema::hasTable('notification_campaigns') ? NotificationCampaign::orderBy('name')->get() : collect()]);
    }

    public function pushTokens(Request $request): View
    {
        if (! Schema::hasTable('user_push_tokens')) {
            return view('admin.notifications.push-tokens', ['tokens' => $this->emptyPaginator($request)]);
        }

        $hasIsActive = Schema::hasColumn('user_push_tokens', 'is_active');
        $tokens = UserPushToken::with('user')
            ->when($request->filled('platform') && Schema::hasColumn('user_push_tokens', 'platform'), fn (Builder $q) => $q->where('platform', $request->platform))
            ->when($request->filled('active') && $hasIsActive, fn (Builder $q) => $q->where('is_active', $request->active === '1'))
            ->when($request->filled('app_version') && Schema::hasColumn('user_push_tokens', 'app_version'), fn (Builder $q) => $q->where('app_version', 'ilike', '%' . $request->app_version . '%'))
            ->when($request->filled('user_search'), fn (Builder $q) => $q->whereHas('user', fn (Builder $u) => $this->applyUserSearch($u, $request->string('user_search')->toString())))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.notifications.push-tokens', compact('tokens'));
    }

    public function deactivatePushToken(string $id): RedirectResponse
    {
        if (Schema::hasColumn('user_push_tokens', 'is_active')) {
            UserPushToken::findOrFail($id)->update(['is_active' => false]);
        }

        return back()->with('success', 'Push token deactivated successfully.');
    }

    public function userNotifications(Request $request): View
    {
        if (! Schema::hasTable('app_notifications')) {
            return view('admin.notifications.user-notifications', ['notifications' => $this->emptyPaginator($request)]);
        }

        $notifications = AppNotification::with('user')
            ->when($request->filled('type'), fn (Builder $q) => $q->where('type', $request->type))
            ->when($request->filled('category'), fn (Builder $q) => $q->where('category', $request->category))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->status))
            ->when($request->filled('priority'), fn (Builder $q) => $q->where('priority', $request->priority))
            ->when($request->filled('read'), fn (Builder $q) => $request->read === 'read' ? $q->whereNotNull('read_at') : $q->whereNull('read_at'))
            ->when($request->filled('clicked'), fn (Builder $q) => $request->clicked === 'clicked' ? $q->whereNotNull('clicked_at') : $q->whereNull('clicked_at'))
            ->when($request->filled('user_search'), fn (Builder $q) => $q->whereHas('user', fn (Builder $u) => $this->applyUserSearch($u, $request->string('user_search')->toString())))
            ->when($request->filled('date_from'), fn (Builder $q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn (Builder $q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.notifications.user-notifications', compact('notifications'));
    }

    public function markNotificationRead(string $id): RedirectResponse
    {
        if (Schema::hasTable('app_notifications')) {
            AppNotification::findOrFail($id)->update(['read_at' => now()]);
        }

        return back()->with('success', 'Notification marked as read.');
    }

    public function deleteNotification(string $id): RedirectResponse
    {
        if (Schema::hasTable('app_notifications')) {
            AppNotification::findOrFail($id)->delete();
        }

        return back()->with('success', 'Notification deleted successfully.');
    }

    public function clearUserNotifications(string $userId): RedirectResponse
    {
        if (Schema::hasTable('app_notifications')) {
            AppNotification::where('user_id', $userId)->delete();
        }

        return back()->with('success', 'User notifications cleared successfully.');
    }

    private function campaignData(Request $request, ?string $ignoreId = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:255', Rule::unique('notification_campaigns', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'channel' => ['required', Rule::in(['push', 'email', 'push_email'])],
            'priority' => ['required', Rule::in(self::PRIORITIES)],
            'trigger_type' => ['required', 'string', 'max:255'],
            'frequency' => ['nullable', 'string', 'max:255'],
            'audience_type' => ['nullable', 'string', 'max:255'],
            'title_template' => ['required', 'string', 'max:255'],
            'body_template' => ['required', 'string'],
            'email_subject_template' => ['nullable', 'string', 'max:255'],
            'email_body_template' => ['nullable', 'string'],
            'tap_screen' => ['nullable', 'string', 'max:255'],
            'daily_limit' => ['nullable', 'integer', 'min:0'],
            'cooldown_hours' => ['nullable', 'integer', 'min:0'],
            'stop_rule' => ['nullable', 'string'],
            'config' => ['nullable', 'json'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['config'] = json_decode($data['config'] ?: '{}', true) ?: [];
        // notification_campaigns.created_by_user_id references app users, while this Blade module is authenticated by the admin guard.
        // Keep it null to avoid cross-guard foreign-key violations.
        $data['created_by_user_id'] = null;

        return $data;
    }

    private function testNotificationData(Request $request): array
    {
        return $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'channel' => ['required', Rule::in(self::CHANNELS)],
            'type' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'priority' => ['required', Rule::in(self::PRIORITIES)],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'screen' => ['required', 'string', 'max:255'],
            'reference_type' => ['nullable', 'string', 'max:255'],
            'reference_id' => ['nullable', 'uuid'],
            'data' => ['nullable', 'json'],
        ]);
    }

    private function applyUserSearch(Builder $query, string $search): void
    {
        $like = '%' . $search . '%';
        $query->where(fn (Builder $q) => $q
            ->where('display_name', 'ilike', $like)
            ->orWhere('first_name', 'ilike', $like)
            ->orWhere('last_name', 'ilike', $like)
            ->orWhere('email', 'ilike', $like)
            ->orWhere('phone', 'ilike', $like));
    }

    private function renderPreview(NotificationCampaign $campaign, array $placeholders): array
    {
        $map = [
            '<person>' => $placeholders['person'] ?? 'Rajesh Kumar',
            '<date>' => $placeholders['date'] ?? now()->format('d M Y'),
            '[Requirement Title]' => $placeholders['requirement_title'] ?? 'Website Development',
            '[Event Title]' => $placeholders['event_title'] ?? 'Unity Networking Meet',
            '[Circle Name]' => $placeholders['circle_name'] ?? 'Greenpreneur Circle',
            '[Status]' => $placeholders['status'] ?? 'Approved',
            '[Amount]' => $placeholders['amount'] ?? '₹10,000',
            '[X]' => $placeholders['x'] ?? '3',
            '[Badge Name]' => $placeholders['badge_name'] ?? 'Connector',
        ];

        return [
            'push_title' => strtr($campaign->title_template, $map),
            'push_body' => strtr($campaign->body_template, $map),
            'email_subject' => strtr((string) $campaign->email_subject_template, $map),
            'email_body' => strtr((string) $campaign->email_body_template, $map),
            'tap_screen' => $campaign->tap_screen,
        ];
    }

    private function screens(): array
    {
        return ['home','feed','post_details','member_profile','private_chat','chat_details','circle_chat','event_details','live_meeting','event_feedback','circle_join_requests','circle_details','circular_details','announcement_details','p2p_meetings','p2p_outcome_form','business_deals_history','referrals_history','testimonials','write_testimonial','visitor_history','membership_application','requirement_details','suggested_connections','coins_wallet','leaderboard','performance','subscription_plans','renew_subscription','badges'];
    }


    private function emptyPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 30, LengthAwarePaginator::resolveCurrentPage(), [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }

    private function defaultCampaigns(): array
    {
        $rows = [
            ['requirement_lead', 'New requirement / lead available', 'New requirement / lead available', 'requirement_match', 'daily', 'matching_requirements', 'Potential Business Match Found!', '<person> is looking for: "[Requirement Title]"', 'requirement_details'],
            ['pending_requirement_reminder', 'Pending requirement reminder', 'New requirement / lead available', 'requirement_match', 'hourly', 'matching_requirements', 'Reminder: respond to pending requirements', 'You have [X] pending requirement matches.', 'requirement_details'],
            ['circle_activity', 'New post / activity in circle', 'New post / activity in circle', 'new_post', 'daily', 'same_circle', 'New activity in [Circle Name]', '<person> shared an update in [Circle Name].', 'feed'],
            ['people_to_connect', 'People to connect with', 'People to connect with', 'inactive_connection_nudge', 'daily', 'mutual_connections', 'People you should connect with', 'Meet [X] relevant peers this week.', 'suggested_connections'],
            ['upcoming_event_reminder', 'Upcoming event reminder', 'Upcoming event reminder', 'new_event_announcement', 'every-five-minutes', 'event_attendees', '[Event Title] is coming up', 'Your event starts on <date>.', 'event_details'],
            ['event_starting_now', 'Event starting now', 'Upcoming event reminder', 'event_live_reminder', 'every-five-minutes', 'event_attendees', '[Event Title] is live now', 'Tap to join the live meeting.', 'live_meeting'],
            ['post_event_feedback', 'Post-event feedback request', 'Upcoming event reminder', 'post_event_feedback', 'every-five-minutes', 'event_attendees', 'Share feedback for [Event Title]', 'How was your event experience?', 'event_feedback'],
            ['unclaimed_coins', 'Unclaimed coins reminder', 'Unclaimed coins / reward reminder', 'unclaimed_coins', 'hourly', 'unclaimed_coins', 'You have unclaimed coins', 'Claim [X] coins in your wallet.', 'coins_wallet'],
            ['referral_testimonial_reward', 'Referral / testimonial reward reminder', 'Unclaimed coins / reward reminder', 'testimonial_request_after_deal', 'daily', 'all_members', 'Earn rewards with referrals', 'Complete referral/testimonial actions to unlock rewards.', 'referrals_history'],
            ['weekly_digest', 'Weekly activity digest', 'People to connect with', 'weekly_digest', 'weekly', 'all_members', 'Your Unity weekly digest', 'Here are your top updates from this week.', 'performance'],
        ];

        return collect($rows)->map(fn (array $row): array => [
            'code' => $row[0], 'name' => $row[1], 'category' => $row[2], 'channel' => 'push', 'trigger_type' => $row[3],
            'frequency' => $row[4], 'priority' => $row[0] === 'event_starting_now' ? 'urgent' : 'medium', 'audience_type' => $row[5],
            'title_template' => $row[6], 'body_template' => $row[7], 'tap_screen' => $row[8], 'is_active' => true,
            'daily_limit' => 3, 'cooldown_hours' => 24, 'config' => [],
        ])->all();
    }
}
