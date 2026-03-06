<?php

namespace App\Enums;

enum CircleBillingTerm: string
{
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case HALF_YEARLY = 'half_yearly';
    case YEARLY = 'yearly';

    public function months(): int
    {
        return match ($this) {
            self::MONTHLY => 1,
            self::QUARTERLY => 3,
            self::HALF_YEARLY => 6,
            self::YEARLY => 12,
        };
    }

    public function suffix(): string
    {
        return match ($this) {
            self::MONTHLY => 'M01',
            self::QUARTERLY => 'M03',
            self::HALF_YEARLY => 'M06',
            self::YEARLY => 'M12',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::MONTHLY => 'Monthly',
            self::QUARTERLY => 'Quarterly',
            self::HALF_YEARLY => '6 Months',
            self::YEARLY => 'Yearly',
        };
    }

    public static function values(): array
    {
        return array_map(static fn (self $term) => $term->value, self::cases());
    }

    public static function tryFromString(string $value): ?self
    {
        return self::tryFrom(strtolower(trim($value)));
    }
}
