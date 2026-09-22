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
     * Start the 12-day independent WhatsApp onboarding sequence when a user registers.
     * Each Day N is scheduled at registration timestamp + (N * 24 hours).
     */
    public function startJourney(User $user, ?Carbon $startedAt = null): void
    {
        $startedAt = $startedAt ?? ($user->created_at ? Carbon::parse($user->created_at) : now());

        // Check if journey already initialized for this user
        $existingCount = DailyHabitSend::where('user_id', $user->id)->count();
        if ($existingCount > 0) {
            Log::info('Daily Habit Loop already initialized for user.', [
                'user_id' => $user->id,
            ]);

            return;
        }

        // Initialize Days 1 through 12, each at registration + (day_number * 24 hours)
        for ($day = 1; $day <= 12; $day++) {
            $scheduledAt = $this->calculateDayScheduleTime($startedAt, $day);

            DailyHabitSend::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'day_number' => $day,
                'scheduled_at' => $scheduledAt,
                'status' => 'scheduled',
            ]);
        }

        Log::info('Daily Habit Loop 12-day journey initialized for user.', [
            'user_id' => $user->id,
            'started_at' => $startedAt->toIso8601String(),
        ]);
    }

    /**
     * Schedule a day's message if not already scheduled (up to Day 12).
     */
    public function scheduleNextDay(User $user, int $currentDayNumber, Carbon $lastSentAt): void
    {
        if ($currentDayNumber >= 12) {
            Log::info('Daily Habit Loop completed for user (Day 12 reached).', [
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

        $journeyStartedAt = $this->getJourneyStartedAt($user);
        $scheduledAt = $this->calculateDayScheduleTime($journeyStartedAt, $nextDayNumber);

        DailyHabitSend::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
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
        if ($dayNumber < 1 || $dayNumber > 12) {
            return null;
        }

        if ($dayNumber === 1) {
            $template = WhatsappTemplate::where('template_key', 'day_1_complete_profile')
                ->where('is_active', true)
                ->first();

            if ($template) {
                return $template;
            }
        }

        if ($dayNumber === 2) {
            $template = WhatsappTemplate::where('template_key', 'business_referrals_day_2')
                ->where('is_active', true)
                ->first();

            if ($template) {
                return $template;
            }
        }

        if ($dayNumber === 3) {
            $template = WhatsappTemplate::where('template_key', 'day_3_photo')
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

        // Dynamic fallback lookup for all days (e.g. day_3, day_3_*, day_5, day_5_*, etc.)
        return WhatsappTemplate::query()
            ->where('is_active', true)
            ->where(function ($query) use ($dayNumber): void {
                $query->where('template_key', "day_{$dayNumber}")
                    ->orWhere('template_key', 'like', "day_{$dayNumber}\\_%")
                    ->orWhere('template_key', 'like', "day_{$dayNumber}-%")
                    ->orWhere('template_key', 'like', "day{$dayNumber}\\_%")
                    ->orWhere('template_key', 'like', "%day_{$dayNumber}%")
                    ->orWhere('template_key', 'like', "%day{$dayNumber}%");
            })
            ->first();
    }

    /**
     * Calculate scheduled time for Day N from registration timestamp.
     * scheduled_at = registration_time + (day_number * 24 hours)
     */
    public function calculateDayScheduleTime(Carbon $startedAt, int $dayNumber): Carbon
    {
        return $startedAt->copy()->addHours($dayNumber * 24);
    }

    /**
     * Calculate send time for Day 1: exactly 24 hours after registration timestamp.
     */
    public function calculateDay1ScheduleTime(Carbon $dateTime, ?string $timezone = null): Carbon
    {
        return $dateTime->copy()->addHours(24);
    }

    /**
     * Calculate next consecutive day's send time: exactly 24 hours after previous successful send time.
     */
    public function calculateNextConsecutiveScheduleTime(Carbon $lastSentAt, ?string $timezone = null): Carbon
    {
        return $lastSentAt->copy()->addHours(24);
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

        try {
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
        } catch (\Throwable $e) {
            Log::warning('Daily Habit Loop failed to resolve circle link.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
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

        if ($firstSend && $firstSend->scheduled_at) {
            return $firstSend->scheduled_at->copy()->subHours($firstSend->day_number * 24);
        }

        return $user->created_at ? Carbon::parse($user->created_at) : now();
    }
}
