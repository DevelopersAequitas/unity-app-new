<?php

declare(strict_types=1);

namespace App\Services\Celebrations;

use App\Models\CelebrationPublishLog;
use App\Models\CelebrationSchedule;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CelebrationScheduleSyncService
{
    private const INACTIVE_USER_STATUSES = [
        'inactive',
        'suspended',
        'blocked',
        'banned',
        'deleted',
        'rejected',
    ];

    public function __construct(
        private readonly CelebrationDateResolver $dateResolver,
    ) {}

    public function tablesReady(): bool
    {
        try {
            return Schema::hasTable('celebration_schedules') && Schema::hasTable('celebration_publish_logs');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Determine whether a peer is eligible for automatic birthday/anniversary tracking.
     */
    public function isUserEligible(User $user): bool
    {
        if ($user->trashed()) {
            return false;
        }

        if (! empty($user->gdpr_deleted_at)) {
            return false;
        }

        if (method_exists($user, 'isSystemUser') && $user->isSystemUser()) {
            return false;
        }

        if (strtolower(trim((string) ($user->email ?? ''))) === 'info@peersglobal.com') {
            return false;
        }

        $status = strtolower(trim((string) ($user->status ?? 'active')));
        if ($status !== '' && in_array($status, self::INACTIVE_USER_STATUSES, true)) {
            return false;
        }

        $attributes = $user->getAttributes();
        if (array_key_exists('is_active', $attributes) && $attributes['is_active'] !== null && ! (bool) $attributes['is_active']) {
            return false;
        }

        return true;
    }

    /**
     * Sync birthday and anniversary schedules for a single peer.
     * Automatically handles:
     * - New peer registration
     * - Adding or modifying DOB or anniversary_date
     * - Cancelling old pending schedules when dates change or are removed
     * - Cancelling pending schedules when an account is deleted or deactivated
     * - Preserving already-published schedules (never double-publishes in the same year)
     */
    public function syncForUser(User $user, string $trigger = 'user_saved', ?CarbonInterface $reference = null): array
    {
        if (! $this->tablesReady()) {
            return ['synced' => 0, 'cancelled' => 0];
        }

        if (! $this->isUserEligible($user)) {
            $reason = $user->trashed() || ! empty($user->gdpr_deleted_at)
                ? 'account_deleted'
                : 'account_deactivated';

            $cancelled = $this->cancelPendingSchedulesForUser((string) $user->id, null, $reason, $trigger);

            return ['synced' => 0, 'cancelled' => $cancelled];
        }

        $synced = 0;
        $cancelled = 0;

        $birthdayResult = $this->syncSingleEventType(
            $user,
            CelebrationSchedule::TYPE_BIRTHDAY,
            $user->dob,
            $trigger,
            $reference
        );
        $synced += $birthdayResult['synced'];
        $cancelled += $birthdayResult['cancelled'];

        $anniversaryResult = $this->syncSingleEventType(
            $user,
            CelebrationSchedule::TYPE_ANNIVERSARY,
            $user->anniversary_date ?? null,
            $trigger,
            $reference
        );
        $synced += $anniversaryResult['synced'];
        $cancelled += $anniversaryResult['cancelled'];

        return ['synced' => $synced, 'cancelled' => $cancelled];
    }

    /**
     * Sync schedules for ALL peers in the database (existing peers + cleanup for deactivated/deleted peers).
     */
    public function syncAllActiveUsers(?CarbonInterface $reference = null, string $trigger = 'full_sync'): array
    {
        if (! $this->tablesReady()) {
            return ['users_checked' => 0, 'synced' => 0, 'cancelled' => 0];
        }

        $usersChecked = 0;
        $totalSynced = 0;
        $totalCancelled = 0;

        // 1. Sync all users who have either dob or anniversary_date set
        User::withTrashed()
            ->where(function ($q): void {
                $q->whereNotNull('dob');
                if (Schema::hasColumn('users', 'anniversary_date')) {
                    $q->orWhereNotNull('anniversary_date');
                }
            })
            ->chunkById(200, function ($users) use (&$usersChecked, &$totalSynced, &$totalCancelled, $reference, $trigger): void {
                foreach ($users as $user) {
                    $usersChecked++;
                    $res = $this->syncForUser($user, $trigger, $reference);
                    $totalSynced += $res['synced'];
                    $totalCancelled += $res['cancelled'];
                }
            }, 'id');

        // 2. Cancel any orphaned pending schedules where user no longer exists or is ineligible
        $pendingSchedules = CelebrationSchedule::query()
            ->whereIn('status', [CelebrationSchedule::STATUS_PENDING, CelebrationSchedule::STATUS_FAILED])
            ->with('user')
            ->get();

        foreach ($pendingSchedules as $schedule) {
            $peer = $schedule->user;
            if (! $peer || ! $this->isUserEligible($peer)) {
                $reason = ! $peer || $peer->trashed() ? 'account_deleted' : 'account_deactivated';
                $this->cancelSchedule($schedule, $reason, $trigger);
                $totalCancelled++;
            }
        }

        return [
            'users_checked' => $usersChecked,
            'synced' => $totalSynced,
            'cancelled' => $totalCancelled,
        ];
    }

    /**
     * Cancel all non-published schedules for a user (optionally filtered by event type).
     */
    public function cancelPendingSchedulesForUser(
        string $userId,
        ?string $eventType = null,
        string $reason = 'cancelled',
        string $trigger = 'system'
    ): int {
        if (! $this->tablesReady()) {
            return 0;
        }

        $query = CelebrationSchedule::query()
            ->where('user_id', $userId)
            ->whereIn('status', [
                CelebrationSchedule::STATUS_PENDING,
                CelebrationSchedule::STATUS_FAILED,
                CelebrationSchedule::STATUS_PROCESSING,
            ]);

        if ($eventType !== null) {
            $query->where('event_type', $eventType);
        }

        $schedules = $query->get();
        $count = 0;

        foreach ($schedules as $schedule) {
            $this->cancelSchedule($schedule, $reason, $trigger);
            $count++;
        }

        return $count;
    }

    public function cancelSchedule(CelebrationSchedule $schedule, string $reason, string $trigger = 'system'): void
    {
        if ($schedule->status === CelebrationSchedule::STATUS_PUBLISHED) {
            return;
        }

        $previousStatus = $schedule->status;
        $previousDate = $schedule->scheduled_date?->format('Y-m-d');

        $schedule->update([
            'status' => CelebrationSchedule::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        $this->logEvent($schedule, 'cancelled', 'warning', "Cancelled pending {$schedule->event_type} schedule ({$reason}).", [
            'trigger' => $trigger,
            'reason' => $reason,
            'previous_status' => $previousStatus,
            'previous_scheduled_date' => $previousDate,
        ]);
    }

    private function syncSingleEventType(
        User $user,
        string $eventType,
        mixed $rawDate,
        string $trigger,
        ?CarbonInterface $reference
    ): array {
        $parsedDate = $this->dateResolver->parseDate($rawDate);

        if (! $parsedDate) {
            $cancelled = $this->cancelPendingSchedulesForUser((string) $user->id, $eventType, 'date_removed', $trigger);

            return ['synced' => 0, 'cancelled' => $cancelled];
        }

        $occurrence = $this->dateResolver->resolveNextOccurrence($parsedDate, $reference);
        $targetYear = $occurrence['event_year'];
        $scheduledDateStr = $occurrence['scheduled_date']->format('Y-m-d');
        $sourceDateStr = $parsedDate->format('Y-m-d');

        // Cancel any pending/failed schedule for this user + event_type in a DIFFERENT year (e.g., DOB shifted across year boundary)
        $otherYearPending = CelebrationSchedule::query()
            ->where('user_id', $user->id)
            ->where('event_type', $eventType)
            ->where('event_year', '!=', $targetYear)
            ->whereIn('status', [
                CelebrationSchedule::STATUS_PENDING,
                CelebrationSchedule::STATUS_FAILED,
            ])
            ->get();

        $cancelledCount = 0;
        foreach ($otherYearPending as $oldSchedule) {
            $this->cancelSchedule($oldSchedule, 'date_changed', $trigger);
            $cancelledCount++;
        }

        $existing = CelebrationSchedule::query()
            ->where('user_id', $user->id)
            ->where('event_type', $eventType)
            ->where('event_year', $targetYear)
            ->first();

        if ($existing) {
            // Never overwrite or re-schedule an already published celebration for the same calendar year
            if ($existing->status === CelebrationSchedule::STATUS_PUBLISHED) {
                return ['synced' => 0, 'cancelled' => $cancelledCount];
            }

            $oldScheduledDateStr = $existing->scheduled_date?->format('Y-m-d');
            $oldSourceDateStr = $existing->source_date?->format('Y-m-d');
            $dateChanged = ($oldScheduledDateStr !== $scheduledDateStr) || ($oldSourceDateStr !== $sourceDateStr);
            $wasCancelled = ($existing->status === CelebrationSchedule::STATUS_CANCELLED);

            // If user manually cancelled the exact same unchanged date via admin panel, don't immediately un-cancel unless date changed or explicitly resynced
            if ($wasCancelled && ! $dateChanged && $existing->cancellation_reason === 'admin_cancelled') {
                return ['synced' => 0, 'cancelled' => $cancelledCount];
            }

            if ($dateChanged || $wasCancelled || (int) $existing->milestone_years !== (int) $occurrence['milestone_years']) {
                if ($dateChanged && $existing->status !== CelebrationSchedule::STATUS_CANCELLED) {
                    $this->logEvent($existing, 'cancelled', 'info', "Old pending {$eventType} schedule ({$oldScheduledDateStr}) replaced due to date change.", [
                        'trigger' => $trigger,
                        'old_source_date' => $oldSourceDateStr,
                        'old_scheduled_date' => $oldScheduledDateStr,
                        'new_source_date' => $sourceDateStr,
                        'new_scheduled_date' => $scheduledDateStr,
                    ]);
                }

                $existing->update([
                    'source_date' => $sourceDateStr,
                    'scheduled_date' => $scheduledDateStr,
                    'scheduled_for_at' => $occurrence['scheduled_for_at']->copy()->setTimezone(config('app.timezone', 'UTC')),
                    'timezone' => CelebrationDateResolver::TIMEZONE,
                    'milestone_years' => $occurrence['milestone_years'],
                    'status' => CelebrationSchedule::STATUS_PENDING,
                    'attempts' => 0,
                    'last_error' => null,
                    'cancelled_at' => null,
                    'cancellation_reason' => null,
                    'meta' => array_merge($existing->meta ?? [], [
                        'is_feb29_adjusted' => $occurrence['is_feb29_adjusted'],
                        'scheduled_time_ist' => '07:00 AM IST',
                        'last_synced_trigger' => $trigger,
                    ]),
                ]);

                $this->logEvent(
                    $existing,
                    $dateChanged ? 'rescheduled' : 'scheduled',
                    'info',
                    $dateChanged
                        ? "Rescheduled {$eventType} for {$scheduledDateStr} at 07:00 AM IST."
                        : "Scheduled {$eventType} for {$scheduledDateStr} at 07:00 AM IST.",
                    [
                        'trigger' => $trigger,
                        'source_date' => $sourceDateStr,
                        'scheduled_date' => $scheduledDateStr,
                        'milestone_years' => $occurrence['milestone_years'],
                        'is_feb29_adjusted' => $occurrence['is_feb29_adjusted'],
                    ]
                );

                return ['synced' => 1, 'cancelled' => $cancelledCount];
            }

            return ['synced' => 0, 'cancelled' => $cancelledCount];
        }

        try {
            $schedule = CelebrationSchedule::create([
                'user_id' => (string) $user->id,
                'event_type' => $eventType,
                'event_year' => $targetYear,
                'source_date' => $sourceDateStr,
                'scheduled_date' => $scheduledDateStr,
                'scheduled_for_at' => $occurrence['scheduled_for_at']->copy()->setTimezone(config('app.timezone', 'UTC')),
                'timezone' => CelebrationDateResolver::TIMEZONE,
                'milestone_years' => $occurrence['milestone_years'],
                'status' => CelebrationSchedule::STATUS_PENDING,
                'attempts' => 0,
                'max_attempts' => 3,
                'meta' => [
                    'is_feb29_adjusted' => $occurrence['is_feb29_adjusted'],
                    'scheduled_time_ist' => '07:00 AM IST',
                    'created_trigger' => $trigger,
                ],
            ]);

            $this->logEvent(
                $schedule,
                'scheduled',
                'info',
                "Automatically scheduled {$eventType} for {$scheduledDateStr} at 07:00 AM IST.",
                [
                    'trigger' => $trigger,
                    'source_date' => $sourceDateStr,
                    'scheduled_date' => $scheduledDateStr,
                    'milestone_years' => $occurrence['milestone_years'],
                    'is_feb29_adjusted' => $occurrence['is_feb29_adjusted'],
                ]
            );

            return ['synced' => 1, 'cancelled' => $cancelledCount];
        } catch (Throwable $e) {
            Log::warning("[CelebrationScheduleSyncService] Could not create schedule for user {$user->id} ({$eventType}): {$e->getMessage()}");

            return ['synced' => 0, 'cancelled' => $cancelledCount];
        }
    }

    private function logEvent(CelebrationSchedule $schedule, string $action, string $status, string $message, array $context = []): void
    {
        try {
            CelebrationPublishLog::create([
                'celebration_schedule_id' => $schedule->id,
                'user_id' => $schedule->user_id,
                'event_type' => $schedule->event_type,
                'event_year' => $schedule->event_year,
                'action' => $action,
                'status' => $status,
                'attempt_number' => max(1, (int) $schedule->attempts),
                'post_id' => $schedule->post_id,
                'creative_file_id' => $schedule->creative_file_id,
                'notification_id' => $schedule->notification_id,
                'message' => $message,
                'context' => $context,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('[CelebrationScheduleSyncService] Failed writing publish log: '.$e->getMessage());
        }
    }
}
