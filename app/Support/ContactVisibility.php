<?php

namespace App\Support;

class ContactVisibility
{
    public const EVERYONE = 'everyone';

    public const CONNECTED_ONLY = 'connected_only';

    public const CIRCLE_ONLY = 'circle_only';

    public const HIDDEN = 'hidden';

    /** @return array<int, string> */
    public static function allowedValues(): array
    {
        return [self::EVERYONE, self::CONNECTED_ONLY, self::CIRCLE_ONLY, self::HIDDEN];
    }

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return match ($normalized) {
            self::EVERYONE, 'public' => self::EVERYONE,
            self::CONNECTED_ONLY, 'connections', 'connected', 'contacts', 'contact_only', 'contacts_only' => self::CONNECTED_ONLY,
            self::CIRCLE_ONLY, 'circle', 'circles', 'circle_members', 'circle_member' => self::CIRCLE_ONLY,
            self::HIDDEN, 'private', 'leadership_only' => self::HIDDEN,
            default => $normalized,
        };
    }
}
