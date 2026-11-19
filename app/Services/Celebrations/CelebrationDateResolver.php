<?php

declare(strict_types=1);

namespace App\Services\Celebrations;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class CelebrationDateResolver
{
    public const TIMEZONE = 'Asia/Kolkata';

    public const PUBLISH_HOUR = 7;

    public const PUBLISH_MINUTE = 0;

    public function nowIst(?CarbonInterface $reference = null): Carbon
    {
        return $reference
            ? Carbon::instance($reference)->copy()->setTimezone(self::TIMEZONE)
            : Carbon::now(self::TIMEZONE);
    }

    public function todayIst(?CarbonInterface $reference = null): Carbon
    {
        return $this->nowIst($reference)->startOfDay();
    }

    /**
     * Resolve the exact calendar date in a specific year for a source date (DOB or anniversary).
     * Handles February 29: uses Feb 29 in leap years and Feb 28 in non-leap years.
     *
     * @return array{date: Carbon, is_feb29_adjusted: bool}
     */
    public function resolveDateForYear(CarbonInterface $sourceDate, int $year): array
    {
        $month = (int) $sourceDate->month;
        $day = (int) $sourceDate->day;
        $isFeb29 = ($month === 2 && $day === 29);
        $isLeapYear = Carbon::createFromDate($year, 1, 1, self::TIMEZONE)->isLeapYear();

        $resolvedDay = ($isFeb29 && ! $isLeapYear) ? 28 : $day;
        $date = Carbon::create($year, $month, $resolvedDay, 0, 0, 0, self::TIMEZONE)->startOfDay();

        return [
            'date' => $date,
            'is_feb29_adjusted' => $isFeb29 && ! $isLeapYear,
        ];
    }

    /**
     * Calculate the next upcoming occurrence (including today in Asia/Kolkata).
     *
     * @return array{
     *   scheduled_date: Carbon,
     *   event_year: int,
     *   scheduled_for_at: Carbon,
     *   days_remaining: int,
     *   milestone_years: int,
     *   is_feb29_adjusted: bool
     * }
     */
    public function resolveNextOccurrence(CarbonInterface $sourceDate, ?CarbonInterface $reference = null): array
    {
        $todayIst = $this->todayIst($reference);
        $currentYear = (int) $todayIst->year;

        $thisYear = $this->resolveDateForYear($sourceDate, $currentYear);
        $scheduledDate = $thisYear['date'];
        $isFeb29Adjusted = $thisYear['is_feb29_adjusted'];

        if ($scheduledDate->lt($todayIst)) {
            $nextYear = $this->resolveDateForYear($sourceDate, $currentYear + 1);
            $scheduledDate = $nextYear['date'];
            $isFeb29Adjusted = $nextYear['is_feb29_adjusted'];
        }

        $eventYear = (int) $scheduledDate->year;
        $scheduledForAt = $scheduledDate->copy()->setTime(self::PUBLISH_HOUR, self::PUBLISH_MINUTE, 0);
        $daysRemaining = (int) $todayIst->diffInDays($scheduledDate, false);
        $milestoneYears = max(0, $eventYear - (int) $sourceDate->year);

        return [
            'scheduled_date' => $scheduledDate,
            'event_year' => $eventYear,
            'scheduled_for_at' => $scheduledForAt,
            'days_remaining' => max(0, $daysRemaining),
            'milestone_years' => $milestoneYears,
            'is_feb29_adjusted' => $isFeb29Adjusted,
        ];
    }

    /**
     * Check if a scheduled celebration date is due at or after 7:00 AM IST on that date.
     */
    public function isDueAt7AmIst(CarbonInterface|string $scheduledDate, ?CarbonInterface $reference = null): bool
    {
        $nowIst = $this->nowIst($reference);
        $targetDateStr = $scheduledDate instanceof CarbonInterface
            ? $scheduledDate->copy()->setTimezone(self::TIMEZONE)->format('Y-m-d')
            : Carbon::parse($scheduledDate, self::TIMEZONE)->format('Y-m-d');

        $todayStr = $nowIst->format('Y-m-d');

        if ($targetDateStr !== $todayStr) {
            return false;
        }

        return $nowIst->hour > self::PUBLISH_HOUR
            || ($nowIst->hour === self::PUBLISH_HOUR && $nowIst->minute >= self::PUBLISH_MINUTE);
    }

    /**
     * Safely parse a date string or Carbon instance.
     */
    public function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        $raw = trim((string) $value);
        if ($raw === '' || in_array(strtolower($raw), ['null', 'undefined', '—', '-'], true)) {
            return null;
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
