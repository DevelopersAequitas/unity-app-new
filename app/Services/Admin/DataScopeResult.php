<?php

declare(strict_types=1);

namespace App\Services\Admin;

class DataScopeResult
{
    public function __construct(
        public readonly string $scopeType,
        public readonly array $scopeIds,
        public readonly array $circleIds,
        public readonly bool $isGlobal,
        public readonly ?string $districtId = null,
        public readonly ?string $stateId = null,
    ) {}

    public static function global(): self
    {
        return new self(
            scopeType: 'global',
            scopeIds: [],
            circleIds: [],
            isGlobal: true,
        );
    }

    public static function circle(array $circleIds): self
    {
        return new self(
            scopeType: 'circle',
            scopeIds: $circleIds,
            circleIds: $circleIds,
            isGlobal: false,
        );
    }

    public static function district(string $districtId, array $circleIds, ?string $stateId = null): self
    {
        return new self(
            scopeType: 'district',
            scopeIds: [$districtId],
            circleIds: $circleIds,
            isGlobal: false,
            districtId: $districtId,
            stateId: $stateId,
        );
    }

    public static function industry(array $industryIds, array $circleIds): self
    {
        return new self(
            scopeType: 'industry',
            scopeIds: $industryIds,
            circleIds: $circleIds,
            isGlobal: false,
        );
    }
}
