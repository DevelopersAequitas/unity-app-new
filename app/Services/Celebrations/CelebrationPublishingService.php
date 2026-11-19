<?php

declare(strict_types=1);

namespace App\Services\Celebrations;

use App\Models\CelebrationPublishLog;
use App\Models\CelebrationSchedule;
use App\Models\Post;
use App\Models\PostMention;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CelebrationPublishingService
{
    public function __construct(
        private readonly CelebrationCreativeService $creativeService,
        private readonly CelebrationScheduleSyncService $syncService,
        private readonly CelebrationDateResolver $dateResolver,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Build a personalized celebration message including the peer's name and age/years.
     */
    public function buildPersonalizedMessage(User $user, string $eventType, ?int $milestoneYears = null): string
    {
        $displayName = $this->creativeService->resolvePeerDisplayName($user);
        $years = $milestoneYears !== null && $milestoneYears > 0 ? $milestoneYears : null;

        if ($eventType === CelebrationSchedule::TYPE_ANNIVERSARY) {
            $milestoneText = $years !== null
                ? " Celebrating {$years} wonderful ".($years === 1 ? 'year' : 'years').' of love and togetherness!'
                : '';

            return "💍 Happy Wedding Anniversary, {$displayName}! 🥂{$milestoneText} Wishing you both a lifetime of love, happiness, and continued prosperity in the Peers Global community! 💐";
        }

        $ageText = $years !== null
            ? " Turning {$years} today — celebrating {$years} wonderful ".($years === 1 ? 'year' : 'years').'!'
            : '';

        return "🎂 Happy Birthday, {$displayName}! 🎉{$ageText} Wishing you a fantastic year ahead filled with new opportunities, continued growth, and great success in the Peers Global community! ✨";
    }

    /**
     * Publish a scheduled celebration to the actual app Timeline and send an in-app notification.
     * Guarantees:
     * - Duplicate prevention per (user_id, event_type, event_year)
     * - Row-level locking against concurrent scheduler/worker execution
     * - Creative generation (approved birthday template or separate anniversary template)
     * - Public, active, approved Timeline post visible to all eligible members
     * - In-app notification dispatched after successful Timeline publishing
     * - Full logging & retryable failure tracking
     *
     * @return array{
     *   status: string,
     *   duplicate: bool,
     *   schedule: CelebrationSchedule,
     *   post: ?Post,
     *   notification_id: ?string,
     *   message: string
     * }
     */
    public function publishSchedule(CelebrationSchedule $schedule, ?string $customMessageOverride = null, bool $forceManual = false): array
    {
        // Lock the schedule row inside a short check to prevent concurrent double execution
        $locked = DB::transaction(function () use ($schedule, $customMessageOverride): array {
            /** @var CelebrationSchedule $fresh */
            $fresh = CelebrationSchedule::query()->whereKey($schedule->id)->lockForUpdate()->firstOrFail();

            if ($fresh->status === CelebrationSchedule::STATUS_PUBLISHED && ! empty($fresh->post_id)) {
                return ['action' => 'already_published', 'schedule' => $fresh];
            }

            $user = User::withTrashed()->find($fresh->user_id);
            if (! $user || ! $this->syncService->isUserEligible($user)) {
                $reason = (! $user || $user->trashed()) ? 'account_deleted' : 'account_deactivated';
                $this->syncService->cancelSchedule($fresh, $reason, 'publish_guard');

                return ['action' => 'ineligible', 'schedule' => $fresh->fresh()];
            }

            // Secondary database-level duplicate check against posts table for the same user + event_type + calendar year (IST)
            $existingPost = $this->findExistingPostForYear($user, $fresh->event_type, (int) $fresh->event_year);
            if ($existingPost) {
                $fresh->update([
                    'status' => CelebrationSchedule::STATUS_PUBLISHED,
                    'post_id' => $existingPost->id,
                    'creative_url' => $existingPost->image ?: $existingPost->media_url,
                    'published_at' => $fresh->published_at ?? $existingPost->created_at ?? now(),
                    'last_error' => null,
                ]);

                $this->writeLog(
                    $fresh,
                    'duplicate_skipped',
                    'warning',
                    "Skipped duplicate {$fresh->event_type} post for {$user->display_name} ({$fresh->event_year}); linked existing post {$existingPost->id}."
                );

                return ['action' => 'existing_post_found', 'schedule' => $fresh->fresh(), 'post' => $existingPost];
            }

            $nextAttempt = ((int) $fresh->attempts) + 1;
            $fresh->update([
                'status' => CelebrationSchedule::STATUS_PROCESSING,
                'attempts' => $nextAttempt,
                'last_attempted_at' => now(),
                'custom_message' => $customMessageOverride !== null && trim($customMessageOverride) !== ''
                    ? trim($customMessageOverride)
                    : $fresh->custom_message,
            ]);

            $this->writeLog(
                $fresh,
                'publish_started',
                'info',
                "Started publishing {$fresh->event_type} celebration (attempt {$nextAttempt}/{$fresh->max_attempts})."
            );

            return ['action' => 'proceed', 'schedule' => $fresh->fresh(), 'user' => $user];
        });

        if ($locked['action'] === 'already_published') {
            $post = ! empty($locked['schedule']->post_id) ? Post::withTrashed()->find($locked['schedule']->post_id) : null;

            return [
                'status' => 'duplicate',
                'duplicate' => true,
                'schedule' => $locked['schedule'],
                'post' => $post,
                'notification_id' => $locked['schedule']->notification_id,
                'message' => "This {$locked['schedule']->event_type} celebration has already been published for {$locked['schedule']->event_year}.",
            ];
        }

        if ($locked['action'] === 'existing_post_found') {
            return [
                'status' => 'duplicate',
                'duplicate' => true,
                'schedule' => $locked['schedule'],
                'post' => $locked['post'],
                'notification_id' => $locked['schedule']->notification_id,
                'message' => "A {$locked['schedule']->event_type} post already exists on the Timeline for {$locked['schedule']->event_year}.",
            ];
        }

        if ($locked['action'] === 'ineligible') {
            return [
                'status' => 'cancelled',
                'duplicate' => false,
                'schedule' => $locked['schedule'],
                'post' => null,
                'notification_id' => null,
                'message' => 'Peer account is inactive or deleted; celebration schedule cancelled.',
            ];
        }

        /** @var CelebrationSchedule $activeSchedule */
        $activeSchedule = $locked['schedule'];
        /** @var User $user */
        $user = $locked['user'];

        try {
            // 1. Generate creative image (birthday template OR separate anniversary template)
            $creative = $this->creativeService->generate($user, $activeSchedule->event_type);
            $fileModel = $creative['file'];
            $fileUrl = $creative['url'];

            $this->writeLog(
                $activeSchedule,
                'creative_generated',
                'success',
                "Generated {$activeSchedule->event_type} creative ({$creative['template_used']}).",
                [
                    'creative_file_id' => (string) $fileModel->id,
                    'template_used' => $creative['template_used'],
                    'used_initials' => $creative['used_initials'],
                ],
                creativeFileId: (string) $fileModel->id
            );

            // 2. Build personalized caption with name and age / years
            $displayName = $this->creativeService->resolvePeerDisplayName($user);
            $milestoneYears = $activeSchedule->milestone_years;
            $caption = filled($activeSchedule->custom_message)
                ? (string) $activeSchedule->custom_message
                : $this->buildPersonalizedMessage($user, $activeSchedule->event_type, $milestoneYears);

            // 3. Publish post to the existing app Timeline (`posts` table)
            $post = $this->createTimelinePost($user, $activeSchedule, $fileModel->id, $fileUrl, $displayName, $caption);

            $this->writeLog(
                $activeSchedule,
                'post_published',
                'success',
                "Published {$activeSchedule->event_type} post {$post->id} to Community Timeline.",
                ['post_id' => (string) $post->id, 'force_manual' => $forceManual],
                postId: (string) $post->id,
                creativeFileId: (string) $fileModel->id
            );

            // 4. Send in-app notification to celebrating member AFTER successful publishing
            $notificationId = $this->dispatchMemberNotification($user, $activeSchedule, $post, $displayName, $fileUrl);

            // 5. Mark schedule as published
            $activeSchedule->update([
                'status' => CelebrationSchedule::STATUS_PUBLISHED,
                'post_id' => (string) $post->id,
                'creative_file_id' => (string) $fileModel->id,
                'creative_url' => $fileUrl,
                'notification_id' => $notificationId,
                'published_at' => now(),
                'last_error' => null,
                'meta' => array_merge($activeSchedule->meta ?? [], [
                    'template_used' => $creative['template_used'],
                    'used_initials' => $creative['used_initials'],
                    'published_via' => $forceManual ? 'admin_manual' : 'automated_scheduler',
                ]),
            ]);

            return [
                'status' => 'published',
                'duplicate' => false,
                'schedule' => $activeSchedule->fresh(),
                'post' => $post,
                'notification_id' => $notificationId,
                'message' => ucfirst($activeSchedule->event_type)." celebration published to Timeline for {$displayName}!",
            ];
        } catch (Throwable $e) {
            $activeSchedule->update([
                'status' => CelebrationSchedule::STATUS_FAILED,
                'last_error' => $e->getMessage(),
            ]);

            $this->writeLog(
                $activeSchedule,
                'publish_failed',
                'failed',
                "Publishing failed on attempt {$activeSchedule->attempts}: {$e->getMessage()}",
                ['exception' => get_class($e)],
                errorDetails: $e->getMessage()
            );

            Log::error("[CelebrationPublishingService] Failed publishing schedule {$activeSchedule->id}: {$e->getMessage()}", [
                'schedule_id' => $activeSchedule->id,
                'user_id' => $activeSchedule->user_id,
                'event_type' => $activeSchedule->event_type,
                'attempt' => $activeSchedule->attempts,
            ]);

            throw $e;
        }
    }

    /**
     * Find or create a schedule for a specific peer and event type, then publish it immediately.
     */
    public function publishForUser(User $user, string $eventType, ?string $customMessage = null, bool $forceManual = true): array
    {
        $this->syncService->syncForUser($user, $forceManual ? 'admin_manual_publish' : 'automated');

        $rawDate = $eventType === CelebrationSchedule::TYPE_ANNIVERSARY ? $user->anniversary_date : $user->dob;
        $parsedDate = $this->dateResolver->parseDate($rawDate);
        if (! $parsedDate) {
            throw new \InvalidArgumentException("Peer does not have a valid {$eventType} date configured.");
        }

        $occurrence = $this->dateResolver->resolveNextOccurrence($parsedDate);
        $schedule = CelebrationSchedule::query()
            ->where('user_id', $user->id)
            ->where('event_type', $eventType)
            ->where('event_year', $occurrence['event_year'])
            ->firstOrFail();

        return $this->publishSchedule($schedule, $customMessage, $forceManual);
    }

    /**
     * Create the actual Timeline post in the existing `posts` table so it appears
     * on BOTH the mobile app Timeline (`GET /api/v1/posts/feed`) and the Admin Panel (`GET /api/v1/admin/posts`).
     */
    protected function createTimelinePost(
        User $user,
        CelebrationSchedule $schedule,
        string $fileId,
        string $fileUrl,
        string $displayName,
        string $caption
    ): Post {
        $systemUser = User::getSystemUser();
        $authorUserId = $systemUser ? (string) $systemUser->id : (string) $user->id;
        $isBirthday = $schedule->event_type === CelebrationSchedule::TYPE_BIRTHDAY;
        $years = $schedule->milestone_years;
        $milestoneSuffix = ($years !== null && $years > 0) ? " ({$years} Years)" : '';

        $postAttributes = [
            'user_id' => $authorUserId,
            'circle_id' => null,
            'content_text' => $caption,
            'media' => [
                [
                    'id' => $fileId,
                    'type' => 'image',
                    'url' => $fileUrl,
                ],
            ],
            'tags' => $isBirthday
                ? ['#Birthday', '#Celebration', '#PeersGlobal']
                : ['#Anniversary', '#Celebration', '#PeersGlobal'],
            'visibility' => 'public',
            'moderation_status' => 'approved',
            'sponsored' => false,
            'is_deleted' => false,
            'active' => true,
            'status' => 'active',
            'source_type' => $schedule->event_type,
            'source_id' => (string) $user->id,
            'source_event' => $schedule->event_type,
            'post_type' => $schedule->event_type,
            'title' => $isBirthday
                ? "🎂 Birthday Celebration: {$displayName}"
                : "💍 Wedding Anniversary: {$displayName}",
            'description' => $isBirthday
                ? "{$displayName} is celebrating their birthday!{$milestoneSuffix}"
                : "{$displayName} is celebrating their wedding anniversary!{$milestoneSuffix}",
            'image' => $fileUrl,
        ];

        $post = Post::create($postAttributes);

        if (Schema::hasTable('post_mentions')) {
            PostMention::firstOrCreate([
                'post_id' => $post->id,
                'peer_id' => $user->id,
            ]);
        }

        return $post;
    }

    /**
     * Send in-app notification to the celebrating member after the Timeline post is live.
     */
    protected function dispatchMemberNotification(
        User $user,
        CelebrationSchedule $schedule,
        Post $post,
        string $displayName,
        string $fileUrl
    ): ?string {
        $isBirthday = $schedule->event_type === CelebrationSchedule::TYPE_BIRTHDAY;
        $years = $schedule->milestone_years;

        $title = $isBirthday
            ? "🎂 Happy Birthday, {$displayName}!"
            : "💍 Happy Wedding Anniversary, {$displayName}!";

        $body = $isBirthday
            ? 'A special birthday celebration creative has been published on the Peers Global Community Timeline for you!'
                .($years && $years > 0 ? " Celebrating {$years} wonderful years! 🎉" : ' Have a wonderful day! 🎉')
            : 'A special wedding anniversary creative has been published on the Peers Global Community Timeline for you!'
                .($years && $years > 0 ? " Cheers to {$years} wonderful years together! 🥂" : ' Wishing you both joy and happiness! 🥂');

        try {
            $notification = $this->notificationService->sendToUser(
                $user,
                'birthday_anniversary',
                $title,
                $body,
                [
                    'screen' => 'timeline',
                    'tap_destination' => 'timeline',
                    'navigation_screen' => '/timeline',
                    'post_id' => (string) $post->id,
                    'user_id' => (string) $user->id,
                    'event_type' => $schedule->event_type,
                    'event_year' => (int) $schedule->event_year,
                    'image_url' => $fileUrl,
                    'reference_type' => 'post',
                    'reference_id' => (string) $post->id,
                ],
                [
                    'channel' => 'in_app',
                    'priority' => 'high',
                    'reference_type' => 'post',
                    'reference_id' => (string) $post->id,
                    'dedupe_key' => "celebration:{$schedule->event_type}:{$user->id}:{$schedule->event_year}",
                    'bypass_daily_limit' => true,
                    'force_in_app' => true,
                ]
            );

            if ($notification) {
                $this->writeLog(
                    $schedule,
                    'notification_sent',
                    'success',
                    "In-app {$schedule->event_type} notification {$notification->id} delivered to {$displayName}.",
                    ['notification_id' => (string) $notification->id],
                    postId: (string) $post->id,
                    notificationId: (string) $notification->id
                );

                return (string) $notification->id;
            }
        } catch (Throwable $e) {
            Log::warning("[CelebrationPublishingService] Failed sending in-app notification for user {$user->id}: {$e->getMessage()}");
            $this->writeLog(
                $schedule,
                'notification_failed',
                'warning',
                "Post published, but notification dispatch failed: {$e->getMessage()}",
                postId: (string) $post->id,
                errorDetails: $e->getMessage()
            );
        }

        return null;
    }

    /**
     * Check if a non-deleted celebration post already exists in the `posts` table for this user, event type, and IST year.
     */
    public function findExistingPostForYear(User $user, string $eventType, int $eventYear): ?Post
    {
        $yearStart = Carbon::create($eventYear, 1, 1, 0, 0, 0, CelebrationDateResolver::TIMEZONE)
            ->setTimezone(config('app.timezone', 'UTC'));
        $yearEnd = Carbon::create($eventYear, 12, 31, 23, 59, 59, CelebrationDateResolver::TIMEZONE)
            ->setTimezone(config('app.timezone', 'UTC'));

        return Post::query()
            ->where('source_id', $user->id)
            ->where(function ($q) use ($eventType): void {
                $q->where('source_type', $eventType)
                    ->orWhere('post_type', $eventType);
            })
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$yearStart, $yearEnd])
            ->first();
    }

    private function writeLog(
        CelebrationSchedule $schedule,
        string $action,
        string $status,
        string $message,
        array $context = [],
        ?string $postId = null,
        ?string $creativeFileId = null,
        ?string $notificationId = null,
        ?string $errorDetails = null
    ): void {
        try {
            CelebrationPublishLog::create([
                'celebration_schedule_id' => $schedule->id,
                'user_id' => $schedule->user_id,
                'event_type' => $schedule->event_type,
                'event_year' => $schedule->event_year,
                'action' => $action,
                'status' => $status,
                'attempt_number' => max(1, (int) $schedule->attempts),
                'post_id' => $postId ?? $schedule->post_id,
                'creative_file_id' => $creativeFileId ?? $schedule->creative_file_id,
                'notification_id' => $notificationId ?? $schedule->notification_id,
                'message' => $message,
                'error_details' => $errorDetails,
                'context' => $context,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('[CelebrationPublishingService] Failed writing publish log: '.$e->getMessage());
        }
    }
}
