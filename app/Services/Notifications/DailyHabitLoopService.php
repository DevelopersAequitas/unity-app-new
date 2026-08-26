<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Notifications\DailyHabitSend;
use App\Models\User;
use App\Models\WhatsappTemplate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DailyHabitLoopService
{
    /**
     * Start the 30-day journey when a user becomes eligible.
     */
    public function startJourney(User $user, ?Carbon $startedAt = null): void
    {
        $startedAt = $startedAt ?? now();
        $timezone = $this->getUserTimezone($user);

        // Calculate schedule time for Day 1
        $scheduledAt = $this->calculateDay1ScheduleTime($startedAt, $timezone);

        // Find template dynamically for Day 1
        $template = $this->resolveTemplateForDay(1);

        if (! $template) {
            Log::warning('Daily Habit Loop Day 1 skipped: Active template not found for Day 1.', [
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

        $template = $this->resolveTemplateForDay($nextDayNumber);

        if (! $template) {
            Log::warning("Daily Habit Loop Day {$nextDayNumber} schedule skipped: Active template not found.", [
                'user_id' => $user->id,
            ]);

            return;
        }

        $timezone = $this->getUserTimezone($user);

        if ($nextDayNumber === 2) {
            // Day 2 must be scheduled exactly 24 hours after Day 1 was successfully sent
            $day1Send = DailyHabitSend::where('user_id', $user->id)
                ->where('day_number', 1)
                ->where('status', 'sent')
                ->first();

            $sourceTime = $day1Send && $day1Send->sent_at ? $day1Send->sent_at : $lastSentAt;
            $scheduledAt = $sourceTime->copy()->addHours(24);
        } else {
            $scheduledAt = $this->calculateNextConsecutiveScheduleTime($lastSentAt, $timezone);
        }

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
     */
    public function calculateDay1ScheduleTime(Carbon $dateTime, string $timezone): Carbon
    {
        $localTime = $dateTime->copy()->setTimezone($timezone);
        $localWindowEnd = $localTime->copy()->setTime(11, 0, 0);

        if ($localTime > $localWindowEnd) {
            // After window today, schedule for tomorrow's window
            $scheduledLocal = $localTime->copy()->addDay()->setTime(10, 0, 0);
        } else {
            // Before or during window today, schedule for today's window
            $scheduledLocal = $localTime->copy()->setTime(10, 0, 0);
        }

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
