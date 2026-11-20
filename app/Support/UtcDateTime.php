<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class UtcDateTime
{
    /**
     * Pure calendar date fields (YYYY-MM-DD) that must NEVER be shifted across timezones.
     */
    private const CALENDAR_DATE_KEYS = [
        'dob',
        'date_of_birth',
        'birth_date',
        'anniversary_date',
        'wedding_anniversary',
        'date',
        'start_date',
        'end_date',
        'startDate',
        'endDate',
        'from_date',
        'to_date',
        'fromDate',
        'toDate',
        'event_date',
        'scheduled_date',
        'occurrence_date',
        'source_date',
        'membership_start_date',
        'membership_end_date',
        'valid_from_date',
        'valid_until_date',
    ];

    /**
     * Sensitive or non-datetime keys that should always be skipped untouched.
     */
    private const SKIP_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'access_token',
        'refresh_token',
        'secret',
        'signature',
        'razorpay_signature',
        'email',
        'phone',
        'mobile',
        'url',
        'banner_url',
        'image',
        'image_url',
        'content',
        'content_text',
        'description',
        'html',
        'long_bio_html',
        'timezone',
    ];

    private const NAIVE_DATETIME_REGEX = '/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?$/';

    private const EXPLICIT_TZ_DATETIME_REGEX = '/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})$/i';

    /**
     * Check whether a string is a valid PHP/IANA timezone identifier.
     */
    public static function isValidTimezone(?string $timezone): bool
    {
        if ($timezone === null || trim($timezone) === '') {
            return false;
        }

        try {
            new DateTimeZone(trim($timezone));

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Resolve the client's timezone from explicit input, request header/body, or fallback.
     */
    public static function resolveRequestTimezone(?Request $request = null, ?string $explicitTimezone = null, string $fallback = 'UTC'): string
    {
        $candidates = [
            $explicitTimezone,
            $request?->input('timezone'),
            $request?->header('X-Timezone'),
            $request?->query('timezone'),
            $fallback,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && self::isValidTimezone($candidate)) {
                return trim($candidate);
            }
        }

        return 'UTC';
    }

    /**
     * Convert any datetime input into a canonical UTC Carbon instance.
     * - If the string already contains 'Z' or a '+/-HH:mm' offset, respects that offset and converts to UTC.
     * - If the string is naive (no offset), interprets it in $sourceTimezone (default UTC) and converts to UTC.
     */
    public static function toUtc(mixed $value, ?string $sourceTimezone = null): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value)->utc();
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->utc();
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $tz = self::isValidTimezone($sourceTimezone) ? trim((string) $sourceTimezone) : 'UTC';

        try {
            if (preg_match(self::EXPLICIT_TZ_DATETIME_REGEX, $trimmed) === 1) {
                return Carbon::parse($trimmed)->utc();
            }

            if (preg_match(self::NAIVE_DATETIME_REGEX, $trimmed) === 1) {
                return Carbon::parse($trimmed, $tz)->utc();
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Convert any datetime input into a canonical UTC SQL string ("Y-m-d H:i:s").
     */
    public static function toUtcString(mixed $value, ?string $sourceTimezone = null): ?string
    {
        return self::toUtc($value, $sourceTimezone)?->format('Y-m-d H:i:s');
    }

    /**
     * Convert any datetime input into a canonical UTC ISO-8601 string ("Y-m-d\TH:i:s\Z").
     */
    public static function toUtcIso(mixed $value, ?string $sourceTimezone = null): ?string
    {
        return self::toUtc($value, $sourceTimezone)?->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Format a stored UTC datetime into a target display timezone.
     */
    public static function formatForTimezone(mixed $utcValue, ?string $targetTimezone = null, string $format = 'Y-m-d H:i:s'): ?string
    {
        $utc = self::toUtc($utcValue, 'UTC');
        if (! $utc) {
            return null;
        }

        $tz = self::isValidTimezone($targetTimezone) ? trim((string) $targetTimezone) : 'UTC';

        return $utc->copy()->setTimezone($tz)->format($format);
    }

    /**
     * Recursively normalize all datetime fields in a CRUD request payload into canonical UTC ISO-8601.
     * Pure calendar dates (YYYY-MM-DD) and non-datetime strings are left untouched.
     *
     * @param  array<string|int, mixed>  $payload
     * @return array<string|int, mixed>
     */
    public static function normalizePayloadToUtc(array $payload, ?string $sourceTimezone = null): array
    {
        $resolvedTz = self::isValidTimezone($sourceTimezone) ? trim((string) $sourceTimezone) : 'UTC';

        // Allow a nested object's own `timezone` field to override the source timezone for its sibling fields
        if (isset($payload['timezone']) && is_string($payload['timezone']) && self::isValidTimezone($payload['timezone'])) {
            $resolvedTz = trim($payload['timezone']);
        }

        foreach ($payload as $key => $value) {
            $keyStr = is_string($key) ? $key : '';

            if ($keyStr !== '' && (self::shouldSkipKey($keyStr) || self::isCalendarDateKey($keyStr))) {
                continue;
            }

            if (is_array($value)) {
                $payload[$key] = self::normalizePayloadToUtc($value, $resolvedTz);
                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            $trimmed = trim($value);
            if ($trimmed === '') {
                continue;
            }

            // Convert ISO-8601 with offset or naive datetime strings to canonical UTC ISO-8601
            if (
                preg_match(self::EXPLICIT_TZ_DATETIME_REGEX, $trimmed) === 1
                || preg_match(self::NAIVE_DATETIME_REGEX, $trimmed) === 1
            ) {
                $utcIso = self::toUtcIso($trimmed, $resolvedTz);
                if ($utcIso !== null) {
                    $payload[$key] = $utcIso;
                }
            }
        }

        return $payload;
    }

    private static function isCalendarDateKey(string $key): bool
    {
        return in_array($key, self::CALENDAR_DATE_KEYS, true);
    }

    private static function shouldSkipKey(string $key): bool
    {
        $lower = strtolower($key);
        if (in_array($lower, self::SKIP_KEYS, true)) {
            return true;
        }

        return str_contains($lower, 'password')
            || str_contains($lower, 'secret')
            || str_contains($lower, 'token')
            || str_contains($lower, 'signature')
            || str_ends_with($lower, '_local');
    }
}
