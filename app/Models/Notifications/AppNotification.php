<?php

namespace App\Models\Notifications;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class AppNotification extends Model
{
    use HasUuids;

    protected $table = 'app_notifications';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id', 'campaign_id', 'type', 'category', 'title', 'message', 'body', 'channel', 'priority',
        'reference_type', 'reference_id', 'screen', 'data', 'dedupe_key', 'status', 'sent_at',
        'read_at', 'clicked_at', 'failed_at', 'failure_reason', 'payload',
    ];

    protected $casts = [
        'data' => 'array',
        'payload' => 'array',
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
        'clicked_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function dataPayload(): array
    {
        $data = (array) ($this->data ?? []);
        $type = (string) ($this->type ?? $data['type'] ?? $data['notification_type'] ?? $data['activity_type'] ?? 'system');

        $userId = $data['user_id'] ?? $data['member_id'] ?? $data['author_id'] ?? $data['actor_id'] ?? $data['from_user_id'] ?? (string) ($this->user_id ?? '');
        $postId = $data['post_id'] ?? ($this->reference_type === 'post' ? (string) $this->reference_id : null);
        $requirementId = $data['requirement_id'] ?? ($this->reference_type === 'requirement' ? (string) $this->reference_id : null);
        $partnerId = $data['partner_id'] ?? $data['brand_partner_id'] ?? ($this->reference_type === 'brand_partner' ? (string) $this->reference_id : null);
        $circleId = $data['circle_id'] ?? ($this->reference_type === 'circle' ? (string) $this->reference_id : null);

        $navScreen = $data['navigation_screen'] ?? null;
        if (! $navScreen) {
            $navScreen = match ($type) {
                'new_post' => '/member-profile',
                'requirement', 'requirement_created', 'requirement_posted' => '/post-details',
                'brand_partner_offer', 'brand_offer', 'brand_partner_joined', 'brand_offer_added' => '/brand-partner-details',
                'membership_expiry', 'membership_expired', 'circle_membership_expiry_reminder', 'upcoming_membership_expired' => '/profile',
                'trending_circle', 'circle_highlight', 'circle_details', 'join_circle' => '/join-circle',
                default => null,
            };
        }

        if (! $navScreen) {
            $rawScreen = (string) ($data['screen'] ?? $data['tap_destination'] ?? $this->screen ?? 'home');
            $navScreen = str_starts_with($rawScreen, '/') ? $rawScreen : '/'.ltrim($rawScreen, '/');
        }

        $activityType = $data['activity_type'] ?? match ($type) {
            'requirement', 'requirement_created', 'requirement_posted' => 'requirement',
            'membership_expiry', 'membership_expired' => 'membership_expiry',
            default => $type,
        };

        $merged = array_merge([
            'notification_id' => (string) $this->id,
            'title' => (string) ($this->title ?? $data['title'] ?? ''),
            'body' => (string) ($this->body ?? $data['body'] ?? $this->message ?? ''),
            'navigation_screen' => (string) $navScreen,
            'type' => $type,
            'activity_type' => (string) $activityType,
            'category' => (string) ($this->category ?? ''),
            'screen' => (string) ($this->screen ?? $navScreen),
            'tap_destination' => (string) (($data['tap_destination'] ?? null) ?: $navScreen),
            'reference_type' => (string) ($this->reference_type ?? ''),
            'reference_id' => (string) ($this->reference_id ?? ''),
            'campaign_id' => (string) ($this->campaign_id ?? ''),
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ], $data);

        // For event notifications: always override event_date from the live events table
        // so that changes to start_at after the notification was created are reflected.
        if (in_array($type, ['event', 'event_created', 'new_event_announcement'], true)) {
            $liveEventId = $data['event_id'] ?? ($this->reference_type === 'event' ? (string) $this->reference_id : null);
            if (filled($liveEventId)) {
                $liveRow = DB::table('events')
                    ->where('id', $liveEventId)
                    ->whereNull('deleted_at')
                    ->select('start_at')
                    ->first();

                if ($liveRow && filled($liveRow->start_at)) {
                    // start_at is stored in UTC in the DB; keep the date/time in UTC
                    // to stay consistent with what the admin UI saves/displays.
                    $liveStartAt = Carbon::parse($liveRow->start_at, 'UTC');
                    $merged['event_date'] = $liveStartAt->toDateString();
                    $merged['event_time'] = $liveStartAt->format('H:i');
                    $merged['event_start_at'] = $liveStartAt->toISOString();
                }
            }
        }

        if (filled($userId)) {
            $merged['user_id'] = (string) $userId;
        }
        if (filled($postId)) {
            $merged['post_id'] = (string) $postId;
        }
        if (filled($requirementId)) {
            $merged['requirement_id'] = (string) $requirementId;
        }
        if (filled($partnerId)) {
            $merged['partner_id'] = (string) $partnerId;
            $merged['brand_partner_id'] = (string) $partnerId;
        }
        if (filled($circleId)) {
            $merged['circle_id'] = (string) $circleId;
        }

        return array_filter($merged, fn ($value) => $value !== null && $value !== '');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NotificationCampaign::class, 'campaign_id');
    }

    public function deliveryLogs(): HasMany
    {
        return $this->hasMany(NotificationDeliveryLog::class, 'notification_id');
    }
}
