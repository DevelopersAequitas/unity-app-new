<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Jobs\PublishCelebrationPostJob;
use App\Models\AnniversaryTemplate;
use App\Models\CelebrationPublishLog;
use App\Models\CelebrationSchedule;
use App\Models\User;
use App\Services\Admin\AdminPeerService;
use App\Services\Celebrations\CelebrationDateResolver;
use App\Services\Celebrations\CelebrationPublishingService;
use App\Services\Celebrations\CelebrationScheduleSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CelebrationAdminController extends BaseApiController
{
    public function __construct(
        private readonly CelebrationDateResolver $dateResolver,
        private readonly CelebrationScheduleSyncService $syncService,
        private readonly CelebrationPublishingService $publishingService,
        private readonly AdminPeerService $peerService,
    ) {}

    /**
     * GET /api/v1/admin/celebrations/upcoming
     * Returns real upcoming birthdays & anniversaries from the database along with real schedule & publishing statuses.
     */
    public function upcoming(Request $request): JsonResponse
    {
        $search = strtolower(trim((string) $request->query('search', '')));
        $eventType = strtolower(trim((string) $request->query('event_type', 'all')));
        $period = strtolower(trim((string) $request->query('period', '30')));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min((int) $request->query('per_page', 200), 500));

        $todayIst = $this->dateResolver->todayIst();
        $hasAnniversaryCol = Schema::hasColumn('users', 'anniversary_date');

        $withRelations = [
            'cityRelation:id,name,state,country',
            'circleMemberships' => fn ($q) => $q->where('status', 'approved')->whereNull('deleted_at')->with('circle:id,name'),
        ];
        if (Schema::hasTable('circle_categories')) {
            $withRelations[] = 'mainBusinessCategory:id,name';
        }
        if (Schema::hasTable('business_categories')) {
            $withRelations[] = 'businessCategory:id,name';
        }
        if (Schema::hasTable('circle_category_level4')) {
            $withRelations[] = 'level4Category:id,name';
        }

        // Fetch all active non-deleted peers with DOB or anniversary_date from the real DB
        $peersQuery = User::query()
            ->whereNull('deleted_at')
            ->when(Schema::hasColumn('users', 'gdpr_deleted_at'), fn ($q) => $q->whereNull('gdpr_deleted_at'))
            ->where(function ($q) use ($hasAnniversaryCol): void {
                $q->whereNotNull('dob');
                if ($hasAnniversaryCol) {
                    $q->orWhereNotNull('anniversary_date');
                }
            })
            ->with($withRelations);

        $peers = $peersQuery->get()->filter(fn (User $peer): bool => $this->syncService->isUserEligible($peer));

        // Load existing celebration schedules for fast O(1) lookup, verifying that any 'published' post is still live on Timeline
        $schedulesByKey = [];
        $livePostIds = [];
        if ($this->syncService->tablesReady()) {
            $schedules = CelebrationSchedule::query()
                ->whereIn('user_id', $peers->pluck('id')->all())
                ->whereIn('event_year', [$todayIst->year, $todayIst->year + 1])
                ->get();

            $publishedPostIds = $schedules
                ->where('status', CelebrationSchedule::STATUS_PUBLISHED)
                ->pluck('post_id')
                ->filter()
                ->map(fn ($id): string => (string) $id)
                ->unique()
                ->values()
                ->all();

            if ($publishedPostIds !== [] && Schema::hasTable('posts')) {
                $livePostIds = \App\Models\Post::query()
                    ->whereIn('id', $publishedPostIds)
                    ->where('status', 'active')
                    ->where('is_deleted', false)
                    ->whereNull('deleted_at')
                    ->whereRaw("CAST(visibility AS TEXT) = 'public'")
                    ->when(Schema::hasColumn('posts', 'active'), function ($q): void {
                        $q->where(function ($aq): void {
                            $aq->whereNull('active')->orWhere('active', true);
                        });
                    })
                    ->pluck('id')
                    ->map(fn ($id): string => (string) $id)
                    ->all();
                $livePostIds = array_flip($livePostIds);
            }

            foreach ($schedules as $sched) {
                if (
                    $sched->status === CelebrationSchedule::STATUS_PUBLISHED
                    && (empty($sched->post_id) || ! isset($livePostIds[(string) $sched->post_id]))
                ) {
                    // Self-heal orphaned schedule whose post was removed/deactivated so Admin Panel & auto-publish stay accurate
                    $sched->status = CelebrationSchedule::STATUS_PENDING;
                    $sched->post_id = null;
                }
                $schedulesByKey["{$sched->user_id}_{$sched->event_type}_{$sched->event_year}"] = $sched;
            }
        }

        $allEvents = [];

        foreach ($peers as $peer) {
            $peerId = (string) $peer->id;
            $peerName = trim((string) ($peer->display_name ?? ''))
                ?: trim(((string) ($peer->first_name ?? '')).' '.((string) ($peer->last_name ?? '')))
                ?: 'Peer';
            $company = trim((string) ($peer->company_name ?? '')) ?: '—';
            $cityName = $peer->cityRelation?->name ?? (is_string($peer->city) ? $peer->city : '');
            if (is_string($cityName) && str_starts_with(trim($cityName), '{')) {
                $decoded = json_decode($cityName, true);
                $cityName = $decoded['name'] ?? $decoded['label'] ?? $cityName;
            }
            $cityName = trim((string) $cityName) ?: '—';
            $circleName = $peer->circleMemberships?->first()?->circle?->name ?? '';

            $avatarUrl = $peer->profile_photo_url;
            if (empty($avatarUrl) && ! empty($peer->profile_photo_file_id)) {
                $avatarUrl = url('/api/v1/files/'.$peer->profile_photo_file_id);
            }
            if ($avatarUrl && ! Str::startsWith((string) $avatarUrl, ['http://', 'https://'])) {
                $avatarUrl = url(ltrim((string) $avatarUrl, '/'));
            }

            // 1. Birthday occurrence
            $dob = $this->dateResolver->parseDate($peer->dob);
            if ($dob) {
                $occ = $this->dateResolver->resolveNextOccurrence($dob, $todayIst);
                $key = "{$peerId}_birthday_{$occ['event_year']}";
                $schedule = $schedulesByKey[$key] ?? null;

                $allEvents[] = $this->formatUpcomingItem(
                    $peer,
                    $peerId,
                    $peerName,
                    $company,
                    $cityName,
                    $circleName,
                    $avatarUrl,
                    CelebrationSchedule::TYPE_BIRTHDAY,
                    $dob,
                    $occ,
                    $schedule
                );
            }

            // 2. Anniversary occurrence
            if ($hasAnniversaryCol) {
                $anniv = $this->dateResolver->parseDate($peer->anniversary_date);
                if ($anniv) {
                    $occ = $this->dateResolver->resolveNextOccurrence($anniv, $todayIst);
                    $key = "{$peerId}_anniversary_{$occ['event_year']}";
                    $schedule = $schedulesByKey[$key] ?? null;

                    $allEvents[] = $this->formatUpcomingItem(
                        $peer,
                        $peerId,
                        $peerName,
                        $company,
                        $cityName,
                        $circleName,
                        $avatarUrl,
                        CelebrationSchedule::TYPE_ANNIVERSARY,
                        $anniv,
                        $occ,
                        $schedule
                    );
                }
            }
        }

        // Filter by period
        $periodEvents = array_values(array_filter($allEvents, fn (array $item): bool => $this->isInPeriod($item, $period, $todayIst)));

        $birthdaysCount = count(array_filter($periodEvents, fn (array $i): bool => $i['event_type'] === 'birthday'));
        $anniversariesCount = count(array_filter($periodEvents, fn (array $i): bool => $i['event_type'] === 'anniversary'));
        $publishedCount = count(array_filter($periodEvents, fn (array $i): bool => $i['schedule_status'] === 'published'));
        $failedCount = count(array_filter($periodEvents, fn (array $i): bool => $i['schedule_status'] === 'failed'));

        // Filter by event type
        $filtered = $periodEvents;
        if (in_array($eventType, ['birthday', 'anniversary'], true)) {
            $filtered = array_values(array_filter($filtered, fn (array $i): bool => $i['event_type'] === $eventType));
        }

        // Filter by search
        if ($search !== '') {
            $filtered = array_values(array_filter($filtered, function (array $i) use ($search): bool {
                return str_contains(strtolower($i['peer_name']), $search)
                    || str_contains(strtolower($i['company_name']), $search)
                    || str_contains(strtolower($i['city']), $search)
                    || str_contains(strtolower($i['circle_name']), $search)
                    || str_contains(strtolower($i['email']), $search)
                    || str_contains(strtolower($i['phone']), $search);
            }));
        }

        // Sort ascending by days remaining, then peer name
        usort($filtered, function (array $a, array $b): int {
            $cmp = $a['days_remaining'] <=> $b['days_remaining'];

            return $cmp !== 0 ? $cmp : strcasecmp($a['peer_name'], $b['peer_name']);
        });

        $total = count($filtered);
        $offset = ($page - 1) * $perPage;
        $pagedItems = array_slice($filtered, $offset, $perPage);

        return $this->success([
            'items' => $pagedItems,
            'total' => $total,
            'pagination' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'per_page' => $perPage,
                'total' => $total,
            ],
            'summary' => [
                'total_upcoming_events' => count($periodEvents),
                'upcoming_birthdays_count' => $birthdaysCount,
                'upcoming_anniversaries_count' => $anniversariesCount,
                'published_count' => $publishedCount,
                'failed_count' => $failedCount,
                'period_label' => $this->resolvePeriodLabel($period),
            ],
            'automation' => [
                'enabled' => true,
                'publish_time_ist' => '07:00 AM IST',
                'timezone' => CelebrationDateResolver::TIMEZONE,
                'birthday_template_url' => url('/images/birthday_creative_template.jpg'),
                'anniversary_template_url' => $this->resolveActiveAnniversaryTemplateUrl(),
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/celebrations/sync
     * Synchronizes schedules for all peers from the database.
     */
    public function sync(): JsonResponse
    {
        $result = $this->syncService->syncAllActiveUsers(trigger: 'admin_manual_sync');

        return $this->success($result, 'Celebration schedules synchronized successfully.');
    }

    /**
     * POST /api/v1/admin/celebrations/process-due
     * Automatically processes all due birthday & anniversary schedules for today (at/after 7:00 AM IST).
     * Strictly idempotent: already-published schedules are skipped.
     */
    public function processDue(Request $request): JsonResponse
    {
        $nowIst = $this->dateResolver->nowIst();
        $todayIst = $nowIst->copy()->startOfDay();
        $todayStr = $todayIst->format('Y-m-d');

        $this->syncService->syncAllActiveUsers($todayIst, 'admin_auto_process_due');

        if (! $request->boolean('force') && ! $this->dateResolver->isDueAt7AmIst($todayIst, $nowIst)) {
            return $this->success([
                'published_count' => 0,
                'skipped_count' => 0,
                'failed_count' => 0,
                'due_now' => false,
                'current_time_ist' => $nowIst->format('H:i'),
            ], 'Waiting until 07:00 AM IST before publishing today\'s celebrations.');
        }

        // Recover stale processing schedules (older than 5 min, no post)
        CelebrationSchedule::query()
            ->whereDate('scheduled_date', $todayStr)
            ->where('status', CelebrationSchedule::STATUS_PROCESSING)
            ->whereNull('post_id')
            ->where(function ($q): void {
                $q->whereNull('last_attempted_at')
                    ->orWhere('last_attempted_at', '<=', now()->subMinutes(5));
            })
            ->whereColumn('attempts', '<', 'max_attempts')
            ->update([
                'status' => CelebrationSchedule::STATUS_PENDING,
                'last_error' => 'Recovered from interrupted processing state',
            ]);

        // Recover any 'published' schedules for today whose linked post is missing or no longer live on Timeline
        $todayPublished = CelebrationSchedule::query()
            ->whereDate('scheduled_date', $todayStr)
            ->where('status', CelebrationSchedule::STATUS_PUBLISHED)
            ->get();

        foreach ($todayPublished as $pubSched) {
            if (empty($pubSched->post_id) || ! $this->publishingService->findLivePostById((string) $pubSched->post_id)) {
                $pubSched->update([
                    'status' => CelebrationSchedule::STATUS_PENDING,
                    'post_id' => null,
                    'attempts' => 0,
                    'last_error' => 'Recovered: linked post was missing or inactive on Timeline',
                ]);
            }
        }

        $dueSchedules = CelebrationSchedule::query()
            ->whereDate('scheduled_date', $todayStr)
            ->whereIn('status', [
                CelebrationSchedule::STATUS_PENDING,
                CelebrationSchedule::STATUS_FAILED,
            ])
            ->whereColumn('attempts', '<', 'max_attempts')
            ->orderBy('created_at')
            ->get();

        $publishedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;

        foreach ($dueSchedules as $schedule) {
            try {
                $res = $this->publishingService->publishSchedule($schedule, null, false);
                if ($res['status'] === 'published') {
                    $publishedCount++;
                } else {
                    $skippedCount++;
                }
            } catch (Throwable) {
                $failedCount++;
            }
        }

        return $this->success([
            'published_count' => $publishedCount,
            'skipped_count' => $skippedCount,
            'failed_count' => $failedCount,
            'due_now' => true,
            'date_ist' => $todayStr,
        ], $publishedCount > 0
            ? "Automatically published {$publishedCount} due celebration(s) to Timeline."
            : 'All due celebrations for today are already processed.');
    }

    /**
     * POST /api/v1/admin/celebrations/publish
     * Publishes a peer's birthday or anniversary celebration immediately to Timeline + sends in-app notification.
     */
    public function publish(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'event_type' => ['required', 'in:birthday,anniversary'],
            'message' => ['nullable', 'string', 'max:2000'],
            'creative_image' => ['nullable', 'string'],
            'refresh_existing' => ['sometimes', 'boolean'],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);

        if (! $this->syncService->isUserEligible($user)) {
            return $this->error('This peer account is inactive or deleted and cannot be published.', 422);
        }

        try {
            $result = $this->publishingService->publishForUser(
                $user,
                $validated['event_type'],
                $validated['message'] ?? null,
                true,
                $validated['creative_image'] ?? null,
                (bool) ($validated['refresh_existing'] ?? false)
            );

            return $this->success([
                'duplicate' => $result['duplicate'],
                'status' => $result['status'],
                'schedule' => $this->formatSchedule($result['schedule']),
                'post_id' => $result['post']?->id,
                'creative_file_id' => $result['schedule']?->creative_file_id,
                'creative_url' => $result['schedule']?->creative_url,
                'notification_id' => $result['notification_id'],
            ], $result['message']);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->error('Failed to publish celebration post: '.$e->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/admin/celebrations/{id}/refresh-creative
     * Updates an already-published celebration post's creative image in-place (zero duplicates).
     */
    public function refreshCreative(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'creative_image' => ['nullable', 'string'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $schedule = CelebrationSchedule::query()->findOrFail($id);

        try {
            $result = $this->publishingService->refreshPublishedCreative(
                $schedule,
                $validated['creative_image'] ?? null,
                $validated['message'] ?? null
            );

            return $this->success([
                'duplicate' => $result['duplicate'],
                'status' => $result['status'],
                'schedule' => $this->formatSchedule($result['schedule']),
                'post_id' => $result['post']?->id,
                'creative_file_id' => $result['schedule']?->creative_file_id,
                'creative_url' => $result['schedule']?->creative_url,
                'notification_id' => $result['notification_id'],
            ], $result['message']);
        } catch (Throwable $e) {
            return $this->error('Failed to refresh celebration creative: '.$e->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/admin/celebrations/schedule
     * Ensures a peer's upcoming celebration is scheduled for 7:00 AM IST (with optional custom message).
     */
    public function schedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'event_type' => ['required', 'in:birthday,anniversary'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);
        $rawDate = $validated['event_type'] === 'anniversary' ? $user->anniversary_date : $user->dob;
        $parsedDate = $this->dateResolver->parseDate($rawDate);

        if (! $parsedDate) {
            return $this->error("Peer does not have a valid {$validated['event_type']} date configured.", 422);
        }

        $this->syncService->syncForUser($user, 'admin_schedule_update');

        $occurrence = $this->dateResolver->resolveNextOccurrence($parsedDate);
        $schedule = CelebrationSchedule::query()
            ->where('user_id', $user->id)
            ->where('event_type', $validated['event_type'])
            ->where('event_year', $occurrence['event_year'])
            ->firstOrFail();

        if ($schedule->status === CelebrationSchedule::STATUS_PUBLISHED) {
            return $this->success([
                'duplicate' => true,
                'schedule' => $this->formatSchedule($schedule),
            ], "This {$validated['event_type']} celebration is already published for {$schedule->event_year}.");
        }

        $schedule->update([
            'status' => CelebrationSchedule::STATUS_PENDING,
            'custom_message' => filled($validated['message'] ?? null) ? trim((string) $validated['message']) : $schedule->custom_message,
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'last_error' => null,
        ]);

        return $this->success([
            'duplicate' => false,
            'schedule' => $this->formatSchedule($schedule->fresh()),
        ], ucfirst($validated['event_type'])." celebration scheduled for {$schedule->scheduled_date->format('d M Y')} at 07:00 AM IST.");
    }

    /**
     * POST /api/v1/admin/celebrations/{id}/cancel
     */
    public function cancel(string $id): JsonResponse
    {
        $schedule = CelebrationSchedule::query()->findOrFail($id);

        if ($schedule->status === CelebrationSchedule::STATUS_PUBLISHED) {
            return $this->error('Cannot cancel a celebration that has already been published.', 422);
        }

        $this->syncService->cancelSchedule($schedule, 'admin_cancelled', 'admin_panel');

        return $this->success([
            'schedule' => $this->formatSchedule($schedule->fresh()),
        ], 'Scheduled celebration cancelled.');
    }

    /**
     * POST /api/v1/admin/celebrations/{id}/retry
     */
    public function retry(Request $request, string $id): JsonResponse
    {
        $schedule = CelebrationSchedule::query()->findOrFail($id);

        if ($schedule->status === CelebrationSchedule::STATUS_PUBLISHED) {
            return $this->success([
                'duplicate' => true,
                'schedule' => $this->formatSchedule($schedule),
            ], 'Celebration is already published.');
        }

        $schedule->update([
            'status' => CelebrationSchedule::STATUS_PENDING,
            'attempts' => 0,
            'last_error' => null,
        ]);

        if ($request->boolean('sync', true) || config('queue.default') === 'sync') {
            $result = $this->publishingService->publishSchedule($schedule->fresh(), null, true);

            return $this->success([
                'duplicate' => $result['duplicate'],
                'schedule' => $this->formatSchedule($result['schedule']),
                'post_id' => $result['post']?->id,
                'notification_id' => $result['notification_id'],
            ], $result['message']);
        }

        PublishCelebrationPostJob::dispatch((string) $schedule->id, null, true);

        return $this->success([
            'schedule' => $this->formatSchedule($schedule->fresh()),
        ], 'Celebration retry job queued.');
    }

    /**
     * GET /api/v1/admin/celebrations/logs
     */
    public function logs(Request $request): JsonResponse
    {
        if (! $this->syncService->tablesReady()) {
            return $this->success(['items' => [], 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 0]]);
        }

        $perPage = max(1, min((int) $request->query('per_page', 25), 100));
        $query = CelebrationPublishLog::query()
            ->with(['user:id,first_name,last_name,display_name,email,company_name'])
            ->orderByDesc('created_at');

        if ($type = $request->query('event_type')) {
            if (in_array($type, ['birthday', 'anniversary'], true)) {
                $query->where('event_type', $type);
            }
        }

        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(function (CelebrationPublishLog $log): array {
            $user = $log->user;
            $peerName = $user
                ? ($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')))
                : 'Peer';

            return [
                'id' => (string) $log->id,
                'schedule_id' => $log->celebration_schedule_id,
                'user_id' => (string) $log->user_id,
                'peer_name' => $peerName,
                'event_type' => $log->event_type,
                'event_year' => (int) $log->event_year,
                'action' => $log->action,
                'status' => $log->status,
                'attempt_number' => (int) $log->attempt_number,
                'post_id' => $log->post_id,
                'creative_file_id' => $log->creative_file_id,
                'notification_id' => $log->notification_id,
                'message' => $log->message,
                'error_details' => $log->error_details,
                'context' => $log->context,
                'created_at' => $log->created_at?->toISOString(),
            ];
        })->values()->all();

        return $this->success([
            'items' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/celebrations/templates
     */
    public function templates(): JsonResponse
    {
        return $this->success([
            'birthday_template_url' => url('/images/birthday_creative_template.jpg'),
            'anniversary_template_url' => $this->resolveActiveAnniversaryTemplateUrl(),
        ]);
    }

    /**
     * POST /api/v1/admin/celebrations/templates/anniversary
     * Uploads and activates a dedicated Wedding Anniversary creative template (separate from birthday).
     */
    public function uploadAnniversaryTemplate(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $file = $request->file('image');
        $path = $file->store('anniversary_templates', 'public');

        if (Schema::hasTable('anniversary_templates')) {
            AnniversaryTemplate::query()->where('is_active', true)->update(['is_active' => false]);
            $template = AnniversaryTemplate::create([
                'image_path' => $path,
                'message' => (string) ($request->input('message') ?: 'Happy Wedding Anniversary!'),
                'is_active' => true,
            ]);

            return $this->success([
                'id' => (string) $template->id,
                'anniversary_template_url' => Storage::disk('public')->url($path),
            ], 'Official Anniversary Creative template uploaded successfully.');
        }

        return $this->error('Anniversary templates table is not available.', 500);
    }

    /**
     * DELETE /api/v1/admin/celebrations/templates/anniversary
     */
    public function resetAnniversaryTemplate(): JsonResponse
    {
        if (Schema::hasTable('anniversary_templates')) {
            AnniversaryTemplate::query()->where('is_active', true)->update(['is_active' => false]);
        }

        return $this->success([
            'anniversary_template_url' => null,
        ], 'Custom Anniversary template reset.');
    }

    private function formatUpcomingItem(
        User $peer,
        string $peerId,
        string $peerName,
        string $company,
        string $city,
        string $circleName,
        ?string $avatarUrl,
        string $eventType,
        Carbon $originalDate,
        array $occ,
        ?CelebrationSchedule $schedule
    ): array {
        $daysRemaining = (int) $occ['days_remaining'];
        $isBirthday = $eventType === CelebrationSchedule::TYPE_BIRTHDAY;

        $countdownText = "In {$daysRemaining} days";
        if ($daysRemaining === 0) {
            $countdownText = $isBirthday ? 'Today! 🎉' : 'Today! ❤️';
        } elseif ($daysRemaining === 1) {
            $countdownText = $isBirthday ? 'Tomorrow! 🎂' : 'Tomorrow! 🥂';
        }

        $milestoneYears = (int) $occ['milestone_years'];

        $designation = trim((string) ($peer->designation ?? ''));
        $isGenericDesignation = $designation === ''
            || in_array(strtolower($designation), ['—', '-', 'member', 'peer', 'user', 'n/a', 'na'], true);
        $businessCategory = trim((string) (
            $peer->businessCategory?->name
            ?? $peer->level4Category?->name
            ?? $peer->mainBusinessCategory?->name
            ?? $peer->business_sub_category
            ?? ''
        ));
        $designationOrCategory = ($businessCategory !== '' && $isGenericDesignation)
            ? $businessCategory
            : (! $isGenericDesignation ? $designation : ($businessCategory !== '' ? $businessCategory : ($designation !== '' ? $designation : null)));

        return [
            'id' => "evt-{$eventType}-{$peerId}-{$occ['scheduled_date']->format('Y-m-d')}",
            'peer_id' => $peerId,
            'peer_name' => $peerName,
            'display_name' => $peerName,
            'designation' => $designation !== '' ? $designation : null,
            'business_category' => $businessCategory !== '' ? $businessCategory : null,
            'designation_or_category' => $designationOrCategory,
            'company_name' => $company,
            'city' => $city,
            'circle_name' => $circleName,
            'avatar_url' => $avatarUrl,
            'phone' => (string) ($peer->phone ?? ''),
            'email' => (string) ($peer->email ?? ''),
            'event_type' => $eventType,
            'original_date' => $originalDate->format('Y-m-d'),
            'original_date_formatted' => $originalDate->format('j M Y'),
            'upcoming_date' => $occ['scheduled_date']->format('Y-m-d'),
            'upcoming_date_formatted' => $occ['scheduled_date']->format('j M Y'),
            'event_year' => (int) $occ['event_year'],
            'days_remaining' => $daysRemaining,
            'countdown_text' => $countdownText,
            'completed_milestone_number' => $milestoneYears,
            'milestone_text' => "{$milestoneYears} Years",
            'is_feb29_adjusted' => (bool) $occ['is_feb29_adjusted'],
            'schedule_id' => $schedule ? (string) $schedule->id : null,
            'schedule_status' => $schedule ? (string) $schedule->status : 'pending',
            'scheduled_time_ist' => '07:00 AM IST',
            'timezone' => CelebrationDateResolver::TIMEZONE,
            'post_id' => $schedule?->post_id,
            'creative_url' => $schedule?->creative_url,
            'notification_id' => $schedule?->notification_id,
            'published_at' => $schedule?->published_at?->toISOString(),
            'attempts' => $schedule ? (int) $schedule->attempts : 0,
            'last_error' => $schedule?->last_error,
            'custom_message' => $schedule?->custom_message,
            'default_message' => $this->publishingService->buildPersonalizedMessage($peer, $eventType, $milestoneYears),
            'raw_peer' => $this->peerService->transformPeer($peer),
        ];
    }

    private function formatSchedule(CelebrationSchedule $schedule): array
    {
        return [
            'id' => (string) $schedule->id,
            'dedup_key' => $schedule->dedupKey(),
            'user_id' => (string) $schedule->user_id,
            'event_type' => $schedule->event_type,
            'event_year' => (int) $schedule->event_year,
            'source_date' => $schedule->source_date?->format('Y-m-d'),
            'scheduled_date' => $schedule->scheduled_date?->format('Y-m-d'),
            'scheduled_for_at' => $schedule->scheduled_for_at?->toISOString(),
            'scheduled_time_ist' => '07:00 AM IST',
            'timezone' => $schedule->timezone,
            'milestone_years' => $schedule->milestone_years,
            'status' => $schedule->status,
            'custom_message' => $schedule->custom_message,
            'post_id' => $schedule->post_id,
            'creative_file_id' => $schedule->creative_file_id,
            'creative_url' => $schedule->creative_url,
            'notification_id' => $schedule->notification_id,
            'attempts' => (int) $schedule->attempts,
            'max_attempts' => (int) $schedule->max_attempts,
            'published_at' => $schedule->published_at?->toISOString(),
            'last_error' => $schedule->last_error,
        ];
    }

    private function isInPeriod(array $item, string $period, Carbon $todayIst): bool
    {
        $days = (int) $item['days_remaining'];

        return match ($period) {
            '7' => $days >= 0 && $days <= 7,
            '15' => $days >= 0 && $days <= 15,
            '30' => $days >= 0 && $days <= 30,
            '60' => $days >= 0 && $days <= 60,
            '90' => $days >= 0 && $days <= 90,
            'month' => Carbon::parse($item['upcoming_date'], CelebrationDateResolver::TIMEZONE)->month === $todayIst->month,
            'year' => Carbon::parse($item['upcoming_date'], CelebrationDateResolver::TIMEZONE)->year === $todayIst->year,
            default => $days >= 0,
        };
    }

    private function resolvePeriodLabel(string $period): string
    {
        return match ($period) {
            '7' => 'Next 7 Days',
            '15' => 'Next 15 Days',
            '30' => 'Next 30 Days',
            '60' => 'Next 60 Days',
            '90' => 'Next 90 Days',
            'month' => 'This Month',
            'year' => 'This Year',
            default => 'All Upcoming',
        };
    }

    private function resolveActiveAnniversaryTemplateUrl(): ?string
    {
        if (! Schema::hasTable('anniversary_templates')) {
            return null;
        }

        $active = AnniversaryTemplate::query()->where('is_active', true)->latest('updated_at')->first();
        if (! $active || empty($active->image_path)) {
            return null;
        }

        if (Storage::disk('public')->exists($active->image_path)) {
            return url(Storage::disk('public')->url($active->image_path));
        }

        return null;
    }
}
