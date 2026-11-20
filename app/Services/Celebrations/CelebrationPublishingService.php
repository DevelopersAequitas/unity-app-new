<?php

declare(strict_types=1);

namespace App\Services\Celebrations;

use App\Models\ActivityCreative;
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
use Illuminate\Support\Str;
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
    public function publishSchedule(
        CelebrationSchedule $schedule,
        ?string $customMessageOverride = null,
        bool $forceManual = false,
        ?string $renderedCreativeDataUrl = null,
        bool $updateExistingCreative = false
    ): array {
        // Lock the schedule row inside a short check to prevent concurrent double execution
        $locked = DB::transaction(function () use ($schedule, $customMessageOverride): array {
            /** @var CelebrationSchedule $fresh */
            $fresh = CelebrationSchedule::query()->whereKey($schedule->id)->lockForUpdate()->firstOrFail();

            if ($fresh->status === CelebrationSchedule::STATUS_PUBLISHED && ! empty($fresh->post_id)) {
                $livePost = $this->findLivePostById((string) $fresh->post_id);
                if ($livePost) {
                    return ['action' => 'already_published', 'schedule' => $fresh, 'post' => $livePost];
                }

                // Linked post was deleted, deactivated, or hidden — reset schedule so it re-publishes live to Timeline
                $fresh->update([
                    'status' => CelebrationSchedule::STATUS_PENDING,
                    'post_id' => null,
                    'last_error' => 'Previously linked post missing or inactive on Timeline; republishing.',
                ]);
                $fresh->refresh();
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
            $post = $locked['post'] ?? (! empty($locked['schedule']->post_id) ? Post::withTrashed()->find($locked['schedule']->post_id) : null);

            if ($updateExistingCreative && $post && ! $post->trashed()) {
                return $this->refreshPublishedCreative($locked['schedule'], $renderedCreativeDataUrl, $customMessageOverride);
            }

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
            if ($updateExistingCreative && ! empty($locked['post'])) {
                return $this->refreshPublishedCreative($locked['schedule'], $renderedCreativeDataUrl, $customMessageOverride);
            }

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
            // 1. Use exact final rendered creative from Admin Panel preview when provided;
            //    otherwise generate the identical HD creative dynamically on backend (Photo + Name + Category + Company)
            $creative = null;
            if (filled($renderedCreativeDataUrl)) {
                $creative = $this->creativeService->storeRenderedCreativeDataUrl(
                    $user,
                    $activeSchedule->event_type,
                    (string) $renderedCreativeDataUrl
                );
            }
            if (! $creative) {
                $creative = $this->creativeService->generate($user, $activeSchedule->event_type);
            }
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
    public function publishForUser(
        User $user,
        string $eventType,
        ?string $customMessage = null,
        bool $forceManual = true,
        ?string $renderedCreativeDataUrl = null,
        bool $updateExistingCreative = false
    ): array {
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

        return $this->publishSchedule($schedule, $customMessage, $forceManual, $renderedCreativeDataUrl, $updateExistingCreative);
    }

    /**
     * Regenerate / replace the creative image on an ALREADY-PUBLISHED Timeline post in-place
     * (ZERO duplicate posts; updates posts.media, posts.image, activity_creatives, and celebration_schedules).
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
    public function refreshPublishedCreative(
        CelebrationSchedule $schedule,
        ?string $renderedCreativeDataUrl = null,
        ?string $customMessageOverride = null
    ): array {
        $user = User::withTrashed()->findOrFail($schedule->user_id);
        $post = ! empty($schedule->post_id) ? $this->findLivePostById((string) $schedule->post_id) : null;

        if (! $post) {
            // Post missing/inactive — fall back to full publish flow
            return $this->publishSchedule($schedule, $customMessageOverride, true, $renderedCreativeDataUrl, false);
        }

        $creative = null;
        if (filled($renderedCreativeDataUrl)) {
            $creative = $this->creativeService->storeRenderedCreativeDataUrl(
                $user,
                $schedule->event_type,
                (string) $renderedCreativeDataUrl
            );
        }
        if (! $creative) {
            $creative = $this->creativeService->generate($user, $schedule->event_type);
        }

        $fileModel = $creative['file'];
        $fileId = (string) $fileModel->id;
        $absoluteUrl = Str::startsWith((string) $creative['url'], ['http://', 'https://'])
            ? (string) $creative['url']
            : url('/api/v1/files/'.$fileId);

        $displayName = $this->creativeService->resolvePeerDisplayName($user);
        $newCaption = filled($customMessageOverride)
            ? trim((string) $customMessageOverride)
            : ($post->content_text ?: $this->buildPersonalizedMessage($user, $schedule->event_type, $schedule->milestone_years));

        $postUpdates = [
            'media' => [
                [
                    'id' => $fileId,
                    'type' => 'image',
                    'url' => $absoluteUrl,
                ],
            ],
            'image' => $absoluteUrl,
            'content_text' => $newCaption,
        ];

        $postColumns = Schema::getColumnListing('posts');
        $filteredUpdates = [];
        foreach ($postUpdates as $col => $val) {
            if (in_array($col, $postColumns, true)) {
                $filteredUpdates[$col] = $val;
            }
        }
        $post->forceFill($filteredUpdates)->save();

        if (Schema::hasTable('activity_creatives')) {
            try {
                ActivityCreative::query()
                    ->where('post_id', $post->id)
                    ->update([
                        'creative_file_id' => $fileId,
                        'creative_url' => $absoluteUrl,
                        'description' => $newCaption,
                        'updated_at' => now(),
                    ]);
            } catch (Throwable $e) {
                Log::warning("[CelebrationPublishingService] Failed updating ActivityCreative for post {$post->id}: {$e->getMessage()}");
            }
        }

        $schedule->update([
            'creative_file_id' => $fileId,
            'creative_url' => $absoluteUrl,
            'custom_message' => filled($customMessageOverride) ? trim((string) $customMessageOverride) : $schedule->custom_message,
            'meta' => array_merge($schedule->meta ?? [], [
                'template_used' => $creative['template_used'],
                'used_initials' => $creative['used_initials'],
                'creative_refreshed_at' => now()->toIso8601String(),
            ]),
        ]);

        $this->writeLog(
            $schedule,
            'creative_refreshed',
            'success',
            "Updated existing {$schedule->event_type} Timeline post {$post->id} with latest creative ({$creative['template_used']}).",
            ['post_id' => (string) $post->id, 'creative_file_id' => $fileId],
            postId: (string) $post->id,
            creativeFileId: $fileId
        );

        return [
            'status' => 'updated',
            'duplicate' => false,
            'schedule' => $schedule->fresh(),
            'post' => $post->fresh(),
            'notification_id' => $schedule->notification_id,
            'message' => ucfirst($schedule->event_type)." creative updated on Timeline for {$displayName} (no duplicate created).",
        ];
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
        $absoluteUrl = Str::startsWith($fileUrl, ['http://', 'https://'])
            ? $fileUrl
            : url('/api/v1/files/'.$fileId);

        $title = $isBirthday
            ? "🎂 Birthday Celebration: {$displayName}"
            : "💍 Wedding Anniversary: {$displayName}";
        $description = $isBirthday
            ? "{$displayName} is celebrating their birthday!{$milestoneSuffix}"
            : "{$displayName} is celebrating their wedding anniversary!{$milestoneSuffix}";

        $geniePostType = $isBirthday ? 'genie_birthday' : 'genie_anniversary';

        $candidateAttributes = [
            'user_id' => $authorUserId,
            'circle_id' => null,
            'content_text' => $caption,
            'media' => [
                [
                    'id' => $fileId,
                    'type' => 'image',
                    'url' => $absoluteUrl,
                ],
            ],
            'tags' => $isBirthday
                ? ['genie_birthday', 'birthday', '#Birthday', '#Celebration', '#PeersGlobal', (string) $user->id]
                : ['genie_anniversary', 'anniversary', '#Anniversary', '#Celebration', '#PeersGlobal', (string) $user->id],
            'visibility' => 'public',
            'moderation_status' => 'approved',
            'sponsored' => false,
            'is_deleted' => false,
            'active' => true,
            'status' => 'active',
            'source_type' => $schedule->event_type,
            'source_id' => (string) $user->id,
            'source_event' => $schedule->event_type,
            'post_type' => $geniePostType,
            'title' => $title,
            'description' => $description,
            'image' => $absoluteUrl,
        ];

        $postColumns = Schema::getColumnListing('posts');
        $postAttributes = [];
        foreach ($candidateAttributes as $col => $val) {
            if (in_array($col, $postColumns, true)) {
                $postAttributes[$col] = $val;
            }
        }

        $post = Post::create($postAttributes);

        if (Schema::hasTable('post_mentions')) {
            try {
                PostMention::firstOrCreate([
                    'post_id' => $post->id,
                    'peer_id' => $user->id,
                ]);
            } catch (Throwable $e) {
                Log::warning("[CelebrationPublishingService] Could not create post_mention for post {$post->id}: {$e->getMessage()}");
            }
        }

        // Register ActivityCreative so the mobile app Timeline renders the creative card consistently with Welcome/Recognition posts
        if (Schema::hasTable('activity_creatives')) {
            try {
                ActivityCreative::create([
                    'user_id' => $user->id,
                    'post_id' => $post->id,
                    'activity_type' => $schedule->event_type,
                    'activity_id' => (string) $user->id,
                    'title' => $title,
                    'description' => $caption,
                    'creative_file_id' => $fileId,
                    'creative_url' => $absoluteUrl,
                    'status' => 'active',
                    'meta' => [
                        'member_name' => $displayName,
                        'event_type' => $schedule->event_type,
                        'event_year' => (int) $schedule->event_year,
                        'milestone_years' => $years,
                    ],
                    'created_by' => $authorUserId,
                ]);
            } catch (Throwable $acEx) {
                Log::warning("[CelebrationPublishingService] Timeline post {$post->id} created, but ActivityCreative save failed: {$acEx->getMessage()}");
            }
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
     * Verify a post ID actually exists and is live/visible on the mobile app Community Timeline.
     */
    public function findLivePostById(string $postId): ?Post
    {
        return Post::query()
            ->whereKey($postId)
            ->where('status', 'active')
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->whereRaw("CAST(visibility AS TEXT) = 'public'")
            ->when(Schema::hasColumn('posts', 'active'), function ($q): void {
                $q->where(function ($aq): void {
                    $aq->whereNull('active')->orWhere('active', true);
                });
            })
            ->first();
    }

    /**
     * Check if a live, public, non-deleted celebration post already exists in the `posts` table for this user, event type, and IST year.
     */
    public function findExistingPostForYear(User $user, string $eventType, int $eventYear): ?Post
    {
        $yearStart = Carbon::create($eventYear, 1, 1, 0, 0, 0, CelebrationDateResolver::TIMEZONE)
            ->setTimezone(config('app.timezone', 'UTC'));
        $yearEnd = Carbon::create($eventYear, 12, 31, 23, 59, 59, CelebrationDateResolver::TIMEZONE)
            ->setTimezone(config('app.timezone', 'UTC'));

        $postTypes = $eventType === CelebrationSchedule::TYPE_ANNIVERSARY
            ? ['anniversary', 'genie_anniversary']
            : ['birthday', 'genie_birthday'];

        return Post::query()
            ->where('source_id', $user->id)
            ->where(function ($q) use ($eventType, $postTypes): void {
                $q->where('source_type', $eventType)
                    ->orWhereIn('post_type', $postTypes);
            })
            ->where('status', 'active')
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->whereRaw("CAST(visibility AS TEXT) = 'public'")
            ->when(Schema::hasColumn('posts', 'active'), function ($q): void {
                $q->where(function ($aq): void {
                    $aq->whereNull('active')->orWhere('active', true);
                });
            })
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
