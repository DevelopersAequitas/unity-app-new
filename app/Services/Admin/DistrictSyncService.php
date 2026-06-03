<?php

namespace App\Services\Admin;

use App\Models\Circle;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class DistrictSyncService
{
    private const INVALID_EXACT_VALUES = [
        '', '-', '--', 'n/a', 'na', 'none', 'null', 'no city', 'unknown',
        'east india', 'west india', 'north india', 'south india', 'central india', 'india', 'london',
    ];

    private const INVALID_CONTAINS = [
        ' hotel', 'hotel ', 'resort', 'restaurant', 'private limited', ' pvt', ' ltd',
        'limited', 'company', 'corporation', 'street', ' road', 'near ', 'opp ', 'opposite',
        'floor', 'building', 'tower', 'complex', 'mall', 'airport', 'station', 'office',
    ];

    public function syncFromUser(User $user): void
    {
        $this->safely(function () use ($user): void {
            $location = null;

            if ($user->getAttribute('city_id')) {
                $location = $this->locationFromCityId((string) $user->getAttribute('city_id'));
            }

            if (! $location) {
                $location = $this->locationFromCityName(
                    $this->firstFilled($user->getAttribute('city'), $user->getAttribute('business_city')),
                    $this->firstFilled($user->getAttribute('state'), $user->getAttribute('business_state')),
                );
            }

            if ($location) {
                $this->upsertDistrict($location['state'], $location['district']);
            }
        });
    }

    public function syncFromCircle(Circle $circle): void
    {
        $this->safely(function () use ($circle): void {
            $location = null;

            if ($circle->getAttribute('city_id')) {
                $location = $this->locationFromCityId((string) $circle->getAttribute('city_id'));
            }

            if (! $location) {
                $city = is_string($circle->getAttribute('city')) ? $circle->getAttribute('city') : $circle->city_display;
                $location = $this->locationFromCityName($city, null);
            }

            if ($location) {
                $this->upsertDistrict($location['state'], $location['district']);
            }
        });
    }

    public function normalizeDistrictName(?string $value): ?string
    {
        $value = preg_replace('/\s+/u', ' ', trim((string) $value));
        $value = trim($value, '"');

        if ($value === '') {
            return null;
        }

        $name = Str::title(Str::lower($value));
        $key = Str::lower($name);

        if (in_array($key, self::INVALID_EXACT_VALUES, true)) {
            return null;
        }

        foreach (self::INVALID_CONTAINS as $needle) {
            if (str_contains($key, trim($needle))) {
                return null;
            }
        }

        if (mb_strlen($name) > 150 || str_contains($name, ',') || str_contains($name, '\n')) {
            return null;
        }

        return $name;
    }

    public function upsertDistrict(?string $stateName, ?string $districtName): ?string
    {
        if (! Schema::hasTable('states') || ! Schema::hasTable('districts')) {
            return null;
        }

        $stateName = $this->normalizeDistrictName($stateName);
        $districtName = $this->normalizeDistrictName($districtName);

        if (! $stateName || ! $districtName) {
            return null;
        }

        return DB::transaction(function () use ($stateName, $districtName): ?string {
            $state = DB::table('states')
                ->whereRaw("LOWER(NULLIF(TRIM(name), '')) = ?", [Str::lower($stateName)])
                ->first(['id']);

            $now = now();

            if (! $state) {
                $stateId = (string) Str::uuid();
                DB::table('states')->insert([
                    'id' => $stateId,
                    'name' => $stateName,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $stateId = (string) $state->id;
                DB::table('states')
                    ->where('id', $stateId)
                    ->update(array_filter([
                        'name' => $stateName,
                        'status' => Schema::hasColumn('states', 'status') ? 'active' : null,
                        'updated_at' => $now,
                    ], fn ($value) => $value !== null));
            }

            $district = DB::table('districts')
                ->where('state_id', $stateId)
                ->whereRaw("LOWER(NULLIF(TRIM(name), '')) = ?", [Str::lower($districtName)])
                ->first(['id']);

            if ($district) {
                DB::table('districts')
                    ->where('id', $district->id)
                    ->update(array_filter([
                        'name' => $districtName,
                        'status' => Schema::hasColumn('districts', 'status') ? 'active' : null,
                        'updated_at' => $now,
                    ], fn ($value) => $value !== null));

                return (string) $district->id;
            }

            $districtId = (string) Str::uuid();
            DB::table('districts')->insert([
                'id' => $districtId,
                'state_id' => $stateId,
                'name' => $districtName,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $districtId;
        });
    }

    private function locationFromCityId(string $cityId): ?array
    {
        if (! Schema::hasTable('cities')) {
            return null;
        }

        $city = DB::table('cities')->where('id', $cityId)->first(['name', 'state', 'district']);

        if (! $city) {
            return null;
        }

        $state = $this->normalizeDistrictName($city->state ?? null);
        $district = $this->normalizeDistrictName($city->district ?? null) ?: $this->normalizeDistrictName($city->name ?? null);

        return ($state && $district) ? compact('state', 'district') : null;
    }

    private function locationFromCityName(?string $cityName, ?string $stateName): ?array
    {
        $cityName = $this->normalizeDistrictName($cityName);
        $stateName = $this->normalizeDistrictName($stateName);

        if (! $cityName) {
            return null;
        }

        if (Schema::hasTable('cities')) {
            $query = DB::table('cities')
                ->whereRaw("LOWER(NULLIF(TRIM(name), '')) = ?", [Str::lower($cityName)]);

            if ($stateName && Schema::hasColumn('cities', 'state')) {
                $query->orderByRaw("CASE WHEN LOWER(NULLIF(TRIM(state), '')) = ? THEN 0 ELSE 1 END", [Str::lower($stateName)]);
            }

            $city = $query->first(['name', 'state', 'district']);
            if ($city) {
                $state = $this->normalizeDistrictName($city->state ?? null) ?: $stateName;
                $district = $this->normalizeDistrictName($city->district ?? null) ?: $this->normalizeDistrictName($city->name ?? null);

                return ($state && $district) ? compact('state', 'district') : null;
            }
        }

        return ($stateName && $cityName) ? ['state' => $stateName, 'district' => $cityName] : null;
    }

    private function firstFilled(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            Log::warning('admin.ded_district_sync_failed', [
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
