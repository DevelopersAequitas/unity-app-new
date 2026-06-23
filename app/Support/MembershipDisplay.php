<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class MembershipDisplay
{
    public static function statusLabel(?string $value): string
    {
        $normalized = self::normalizeStatus($value);

        if ($normalized === '') {
            return '—';
        }

        return config('membership.statuses.' . $normalized)
            ?? Str::headline(str_replace('_', ' ', $normalized));
    }

    public static function statusOptions(): array
    {
        return config('membership.statuses', []);
    }

    public static function normalizeStatus(?string $value): string
    {
        return strtolower(trim(str_replace(' ', '_', (string) $value)));
    }

    public static function dateLabel(mixed $value): string
    {
        if (blank($value)) {
            return '—';
        }

        try {
            $date = $value instanceof CarbonInterface ? $value : Carbon::parse($value);
        } catch (\Throwable) {
            return '—';
        }

        return $date->format('d-m-Y');
    }

    public static function dateTimeLabel(mixed $value): string
    {
        if (blank($value)) {
            return '—';
        }

        try {
            $date = $value instanceof CarbonInterface ? $value : Carbon::parse($value);
        } catch (\Throwable) {
            return '—';
        }

        return $date->format('d-m-Y h:i A');
    }

    public static function dateKey(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            $date = $value instanceof CarbonInterface ? $value : Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }

        return $date->toDateString();
    }
}
