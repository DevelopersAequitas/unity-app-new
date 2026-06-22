<?php

namespace App\Support\Membership;

use Illuminate\Support\Str;

class MembershipStatusLabels
{
    public static function all(): array
    {
        return (array) config('membership.labels', []);
    }

    public static function label(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '—';
        }

        $labels = self::all();
        $normalized = self::normalize($value);

        return $labels[$value]
            ?? $labels[$normalized]
            ?? Str::headline(str_replace('_', ' ', $value));
    }

    public static function normalize(?string $value): string
    {
        return strtolower(trim(str_replace(' ', '_', (string) $value)));
    }
}
