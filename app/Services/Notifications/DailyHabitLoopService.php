<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Circle;
use App\Models\Notifications\DailyHabitSend;
use App\Models\User;
use App\Models\WhatsappTemplate;
use App\Services\Referrals\ReferralService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DailyHabitLoopService
{
    /**
     * Start the 30-day journey when a user becomes eligible.
     * Registration day is Day 0 (no habit message on registration day).
     * Day 1 is scheduled for the next applicable day at 10:00 AM local time.
     */
    public function startJourney(User $user, ?Carbon $startedAt = null): void
    {
        $startedAt = $startedAt ?? now();
        $timezone = $this->getUserTimezone($user);

        // Calculate schedule time for Day 1 (starts next day at 10:00 AM local time)
        $scheduledAt = $this->calculateDay1ScheduleTime($startedAt, $timezone);

        // Prevent duplicate scheduling if Day 1 already exists
        $exists = DailyHabitSend::where('user_id', $user->id)
            ->where('day_number', 1)
            ->exists();

        if ($exists) {
            Log::info('Daily Habit Loop Day 1 already scheduled or sent for user.', [
                'user_id' => $user->id,
            ]);

            return;
        }

        // Create the send record with unique protection storing only delivery state
        DailyHabitSend::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'journey_started_at' => $startedAt,
            'day_number' => 1,
            'scheduled_at' => $scheduledAt,
            'status' => 'scheduled',
        ]);

        Log::info('Daily Habit Loop started for user.', [
            'user_id' => $user->id,
            'journey_started_at' => $startedAt->toIso8601String(),
            'scheduled_at' => $scheduledAt->toIso8601String(),
        ]);
    }

    /**
     * Schedule the next day's message (consecutive day).
     * Missing or inactive templates must not block sequence progression.
     */
    public function scheduleNextDay(User $user, int $currentDayNumber, Carbon $lastSentAt): void
    {
        if ($currentDayNumber >= 30) {
            Log::info('Daily Habit Loop completed for user.', [
                'user_id' => $user->id,
            ]);

            return;
        }

        $nextDayNumber = $currentDayNumber + 1;

        // Prevent duplicate scheduling
        $exists = DailyHabitSend::where('user_id', $user->id)
            ->where('day_number', $nextDayNumber)
            ->exists();

        if ($exists) {
            Log::info("Daily Habit Loop Day {$nextDayNumber} already scheduled or sent for user.", [
                'user_id' => $user->id,
            ]);

            return;
        }

        $timezone = $this->getUserTimezone($user);

        // Derive source time from previous send record (sent_at if sent, or scheduled_at if failed/skipped)
        $currentSend = DailyHabitSend::where('user_id', $user->id)
            ->where('day_number', $currentDayNumber)
            ->first();

        $sourceTime = ($currentSend && $currentSend->sent_at)
            ? $currentSend->sent_at
            : ($currentSend && $currentSend->scheduled_at ? $currentSend->scheduled_at : $lastSentAt);

        $scheduledAt = $sourceTime->copy()->addHours(24);

        DailyHabitSend::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'journey_started_at' => $this->getJourneyStartedAt($user),
            'day_number' => $nextDayNumber,
            'scheduled_at' => $scheduledAt,
            'status' => 'scheduled',
        ]);

        Log::info("Daily Habit Loop Day {$nextDayNumber} scheduled.", [
            'user_id' => $user->id,
            'scheduled_at' => $scheduledAt->toIso8601String(),
        ]);
    }

    /**
     * Resolve the active template dynamically from the database for a given day.
     */
    public function resolveTemplateForDay(int $dayNumber): ?WhatsappTemplate
    {
        if ($dayNumber === 1) {
            return WhatsappTemplate::where('template_key', 'day_1_complete_profile')
                ->where('is_active', true)
                ->first();
        }

        if ($dayNumber === 2) {
            $template = WhatsappTemplate::where('template_key', 'business_referrals_day_2')
                ->where('is_active', true)
                ->first();

            if ($template) {
                return $template;
            }
        }

        if ($dayNumber === 4) {
            $template = WhatsappTemplate::where('template_key', 'day_4_business_referral')
                ->where('is_active', true)
                ->first();

            if ($template) {
                return $template;
            }
        }

        if ($dayNumber === 7) {
            $template = WhatsappTemplate::where('template_key', 'day_7_introduce_yourself_circle')
                ->where('is_active', true)
                ->first();

            if ($template) {
                return $template;
            }
        }

        return WhatsappTemplate::query()
            ->where(function ($query) use ($dayNumber): void {
                $query->where('template_key', "day_{$dayNumber}")
                    ->orWhere('template_key', 'like', "day_{$dayNumber}_%");
            })
            ->where('is_active', true)
            ->first();
    }

    /**
     * Calculate preferred 10 AM send time for Day 1.
     * Registration day is Day 0, Day 1 always starts next day at 10:00 AM local time.
     */
    public function calculateDay1ScheduleTime(Carbon $dateTime, string $timezone): Carbon
    {
        $localTime = $dateTime->copy()->setTimezone($timezone);
        $scheduledLocal = $localTime->copy()->addDay()->setTime(10, 0, 0);

        return $scheduledLocal->setTimezone('UTC');
    }

    /**
     * Calculate next consecutive day's 10 AM send time.
     */
    public function calculateNextConsecutiveScheduleTime(Carbon $lastSentAt, string $timezone): Carbon
    {
        $lastSentLocal = $lastSentAt->copy()->setTimezone($timezone);
        $scheduledLocal = $lastSentLocal->copy()->addDay()->setTime(10, 0, 0);

        return $scheduledLocal->setTimezone('UTC');
    }

    /**
     * Resolve dynamic ReferralLink URL for a given user.
     */
    public function resolveReferralLink(User $user): string
    {
        try {
            $referralData = app(ReferralService::class)->generateOrGetReferral($user);
            if (! empty($referralData['referral_link'])) {
                return (string) $referralData['referral_link'];
            }
        } catch (\Throwable $e) {
            Log::warning('Daily Habit Loop failed to resolve referral link via ReferralService.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        $base = (string) config('referrals.register_url', rtrim((string) config('app.url'), '/').'/register');

        return $base;
    }

    /**
     * Resolve dynamic CircleLink URL for a given user.
     */
    public function resolveCircleLink(User $user): string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');

        $circle = null;
        if ($user->relationLoaded('activeCircle') && $user->activeCircle) {
            $circle = $user->activeCircle;
        } elseif (! empty($user->active_circle_id)) {
            $circle = Circle::find($user->active_circle_id);
        }

        if (! $circle && method_exists($user, 'circles')) {
            $circle = $user->circles()->first();
        }

        if (! $circle && method_exists($user, 'circleMembers')) {
            $member = $user->circleMembers()
                ->where(function ($query): void {
                    $query->whereNull('status')->orWhere('status', 'approved');
                })
                ->with('circle')
                ->first();
            $circle = $member?->circle;
        }

        if (! $circle && method_exists($user, 'foundedCircles')) {
            $circle = $user->foundedCircles()->first();
        }

        if ($circle && ! empty($circle->id)) {
            return "{$baseUrl}/circles/{$circle->id}";
        }

        return "{$baseUrl}/circles";
    }

    /**
     * Resolve the timezone of a user.
     */
    public function getUserTimezone(User $user): string
    {
        $tz = $user->timezone;
        if (! $tz || ! is_string($tz) || ! in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            $tz = (string) (config('app.timezone') ?: 'UTC');
        }

        return $tz;
    }

    /**
     * Helper to retrieve journey start time for a user.
     */
    private function getJourneyStartedAt(User $user): Carbon
    {
        $firstSend = DailyHabitSend::where('user_id', $user->id)
            ->orderBy('day_number', 'asc')
            ->first();

        return $firstSend ? $firstSend->journey_started_at : now();
    }
}
