<?php

namespace App\Services\AdminCampaigns;

use App\Models\CampaignSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CampaignScheduleCalculator
{
    /**
     * Calculates the next execution run date-time in UTC for a schedule.
     */
    public function calculateNextRunAt(CampaignSchedule $schedule, Carbon $from): ?Carbon
    {
        // Early return for immediate schedules
        if (($schedule->schedule_type ?? 'immediately') === 'immediately') {
            return $from->copy();
        }

        // Early return for once schedules that have already run
        if ($schedule->schedule_type === 'once' && ! empty($schedule->last_run_at)) {
            return null;
        }

        // 1. Convert "from" time to schedule timezone
        $tz = filled($schedule->timezone) ? $schedule->timezone : 'UTC';

        try {
            $localFrom = $from->copy()->setTimezone($tz);
        } catch (\Exception $e) {
            Log::error("Invalid timezone '{$tz}' in schedule calculation, falling back to UTC.");
            $tz = 'UTC';
            $localFrom = $from->copy()->setTimezone('UTC');
        }

        $startDateStr = $schedule->start_date;
        if ($startDateStr instanceof \Carbon\Carbon) {
            $startDateStr = $startDateStr->toDateString();
        }
        if (empty($startDateStr)) {
            $startDateStr = now()->toDateString();
        }

        try {
            $localStartDate = Carbon::createFromFormat('Y-m-d H:i:s', $startDateStr.' 00:00:00', $tz);
        } catch (\Exception $e) {
            Log::error("Invalid start_date '{$startDateStr}' in schedule calculation, falling back to today.");
            $localStartDate = Carbon::createFromFormat('Y-m-d H:i:s', now()->toDateString().' 00:00:00', $tz);
        }

        // Ensure candidate starts at or after the schedule start date
        if ($localFrom->lt($localStartDate)) {
            $candidate = $localStartDate->copy();
        } else {
            $candidate = $localFrom->copy();
        }

        // Set candidate's time to the scheduled send time (handling missing or shorter formats defensively)
        if (filled($schedule->send_time)) {
            $timeParts = explode(':', $schedule->send_time);
            $hours = isset($timeParts[0]) ? (int) $timeParts[0] : 9;
            $minutes = isset($timeParts[1]) ? (int) $timeParts[1] : 0;
            $seconds = isset($timeParts[2]) ? (int) $timeParts[2] : 0;
            $candidate->setTime($hours, $minutes, $seconds);
        } else {
            $candidate->setTime(9, 0, 0); // Default to 9:00 AM if send time is missing
        }

        // 1. Ensure candidate satisfies the recurrence rules.
        // If not, advance until we find the first candidate that does.
        if (! $this->matchesRule($schedule, $candidate, $localStartDate)) {
            $candidate = $this->advanceCycle($schedule, $candidate, $localStartDate);
        }

        // 2. If the candidate date-time is in the past compared to the relative "from" time,
        // we must advance it to the next matching instance.
        // EXCEPTION: If the campaign has never run before (last_run_at is empty) and the candidate
        // date is today, we keep today's candidate so that it executes immediately.
        $hasNeverRun = empty($schedule->last_run_at);
        $candidateIsToday = $candidate->toDateString() === $localFrom->toDateString();

        $isWithinThreshold = abs($candidate->diffInSeconds($localFrom, false)) <= 60;

        if ($candidate->lte($localFrom) || $isWithinThreshold) {
            if ($hasNeverRun && $candidateIsToday) {
                // Do not advance. Let today's first scheduled run execute even if send time has passed.
            } else {
                $candidate = $this->advanceCycle($schedule, $candidate, $localStartDate);
            }
        }

        // Check end date boundaries in local timezone
        if ($schedule->end_type === 'date' && filled($schedule->end_date)) {
            try {
                $localEndDate = Carbon::parse($schedule->end_date, $tz)->endOfDay();
                if ($candidate->gt($localEndDate)) {
                    return null;
                }
            } catch (\Exception $e) {
                Log::error("Invalid end_date '{$schedule->end_date}' in schedule calculation.");
            }
        }

        // Return final candidate converted to UTC for DB storage
        return $candidate->setTimezone('UTC');
    }

    /**
     * Check if a given date satisfies the recurrence rule.
     */
    public function matchesRule(CampaignSchedule $schedule, Carbon $date, Carbon $startDate): bool
    {
        // Only run on or after start date
        if ($date->lt($startDate->copy()->startOfDay())) {
            return false;
        }

        // Create UTC date-only instances for safe calendar diffs (eliminating DST transition hour shifts)
        $dateUtc = Carbon::createFromFormat('Y-m-d', $date->toDateString(), 'UTC')->startOfDay();
        $startDateUtc = Carbon::createFromFormat('Y-m-d', $startDate->toDateString(), 'UTC')->startOfDay();

        switch ($schedule->recurrence_type) {
            case 'daily':
                $interval = (int) ($schedule->frequency_interval ?? 1);
                if ($interval <= 0) {
                    $interval = 1;
                }
                $diff = abs($dateUtc->diffInDays($startDateUtc, false));

                return ($diff % $interval) === 0;

            case 'weekly':
                $interval = (int) ($schedule->frequency_interval ?? 1);
                if ($interval <= 0) {
                    $interval = 1;
                }
                $diff = abs($dateUtc->diffInWeeks($startDateUtc, false));
                if (($diff % $interval) !== 0) {
                    return false;
                }
                if (empty($schedule->weekdays)) {
                    return true;
                }
                $allowedDays = array_map('trim', explode(',', $schedule->weekdays));

                return in_array($date->format('l'), $allowedDays, true);

            case 'monthly':
                $interval = (int) ($schedule->frequency_interval ?? 1);
                if ($interval <= 0) {
                    $interval = 1;
                }
                $diff = abs($dateUtc->diffInMonths($startDateUtc, false));
                if (($diff % $interval) !== 0) {
                    return false;
                }

                $basis = filled($schedule->monthly_basis) ? $schedule->monthly_basis : 'date';
                if ($basis === 'date') {
                    // E.g. Monthly by Day of Month (1st, 15th, 28th)
                    $targetDay = (int) ($schedule->monthly_day_of_month ?? 1);
                    if ($targetDay <= 0) {
                        $targetDay = 1;
                    }
                    $maxDaysInMonth = $date->daysInMonth;
                    $actualTargetDay = min($targetDay, $maxDaysInMonth);

                    return $date->day === $actualTargetDay;
                } else {
                    // E.g. Monthly by Position (First Monday, Last Friday, etc.)
                    return $this->matchesMonthlyPosition($schedule, $date);
                }

            case 'yearly':
                $targetMonth = (int) ($schedule->yearly_month ?? 1);
                $targetDay = (int) ($schedule->yearly_day ?? 1);
                if ($targetMonth < 1 || $targetMonth > 12) {
                    $targetMonth = 1;
                }
                if ($targetDay < 1 || $targetDay > 31) {
                    $targetDay = 1;
                }

                return (int) $date->month === $targetMonth && (int) $date->day === $targetDay;

            case 'custom':
                $unit = filled($schedule->custom_unit) ? $schedule->custom_unit : 'day';
                $interval = (int) ($schedule->frequency_interval ?? 1);
                if ($interval <= 0) {
                    $interval = 1;
                }
                switch ($unit) {
                    case 'day':
                        $diff = abs($dateUtc->diffInDays($startDateUtc, false));

                        return ($diff % $interval) === 0;
                    case 'week':
                        $diff = abs($dateUtc->diffInWeeks($startDateUtc, false));

                        return ($diff % $interval) === 0;
                    case 'month':
                        $diff = abs($dateUtc->diffInMonths($startDateUtc, false));

                        return ($diff % $interval) === 0;
                    case 'year':
                        $diff = abs($dateUtc->diffInYears($startDateUtc, false));

                        return ($diff % $interval) === 0;
                }

                return false;

            case 'cycle':
                $sendDays = (int) ($schedule->cycle_send_days ?? 1);
                $pauseDays = (int) ($schedule->cycle_pause_days ?? 0);
                if ($sendDays <= 0) {
                    $sendDays = 1;
                }
                if ($pauseDays < 0) {
                    $pauseDays = 0;
                }
                $cycleLength = $sendDays + $pauseDays;
                if ($cycleLength <= 0) {
                    $cycleLength = 1;
                }
                $diff = abs($date->copy()->startOfDay()->diffInDays($startDate->copy()->startOfDay(), false));
                $position = $diff % $cycleLength;

                return $position < $sendDays;

            default:
                return true;
        }
    }

    /**
     * Advance the date iteratively until we match the recurrence rule.
     */
    private function advanceCycle(CampaignSchedule $schedule, Carbon $date, Carbon $startDate): Carbon
    {
        $candidate = $date->copy();
        $safety = 0;

        do {
            $candidate->addDay();
            $safety++;
        } while (! $this->matchesRule($schedule, $candidate, $startDate) && $safety < 2000);

        return $candidate;
    }

    /**
     * Match first/second/third/fourth/last weekday of the month.
     */
    private function matchesMonthlyPosition(CampaignSchedule $schedule, Carbon $date): bool
    {
        // 1. Check if the weekday matches
        $targetDayOfWeek = filled($schedule->monthly_day_of_week) ? $schedule->monthly_day_of_week : 'Monday';
        if ($date->format('l') !== $targetDayOfWeek) {
            return false;
        }

        $pos = filled($schedule->monthly_position) ? $schedule->monthly_position : 'first'; // first, second, third, fourth, last

        switch ($pos) {
            case 'first':
                return $date->day <= 7;
            case 'second':
                return $date->day > 7 && $date->day <= 14;
            case 'third':
                return $date->day > 14 && $date->day <= 21;
            case 'fourth':
                return $date->day > 21 && $date->day <= 28;
            case 'last':
                $temp = $date->copy()->addWeek();

                return $temp->month !== $date->month;
        }

        return false;
    }
}
