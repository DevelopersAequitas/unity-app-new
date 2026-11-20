<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\UtcDateTime;
use Illuminate\Database\Eloquent\Model;

/**
 * Reusable Eloquent Model Trait for Global UTC Persistence.
 *
 * Any model using this trait automatically converts all `datetime` / `immutable_datetime` / `timestamp`
 * cast attributes into canonical UTC on both Create and Edit (`saving` lifecycle hook).
 */
trait NormalizesUtcTimestamps
{
    public static function bootNormalizesUtcTimestamps(): void
    {
        static::saving(function (Model $model): void {
            $casts = $model->getCasts();
            $rawAttributes = $model->getAttributes();

            foreach ($casts as $attribute => $castType) {
                $normalizedCast = strtolower(trim((string) $castType));
                $isDatetimeCast = str_starts_with($normalizedCast, 'datetime')
                    || str_starts_with($normalizedCast, 'immutable_datetime')
                    || $normalizedCast === 'timestamp';

                if (! $isDatetimeCast || ! array_key_exists($attribute, $rawAttributes)) {
                    continue;
                }

                $rawValue = $rawAttributes[$attribute];
                if ($rawValue === null || $rawValue === '') {
                    continue;
                }

                $utcString = UtcDateTime::toUtcString($rawValue, 'UTC');
                if ($utcString !== null) {
                    $model->setRawAttributes(
                        array_merge($model->getAttributes(), [$attribute => $utcString]),
                        false
                    );
                }
            }
        });
    }
}
