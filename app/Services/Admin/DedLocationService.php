<?php

namespace App\Services\Admin;

use App\Models\AdminDedDistrict;
use App\Models\Circle;
use App\Models\District;
use App\Models\State;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DedLocationService
{
    private const INVALID_LOCATION_VALUES = [
        '',
        '-',
        'n/a',
        'na',
        'no city',
        'none',
        'null',
        'not available',
        'unknown',
        'all india',
        'india',
        'east india',
        'west india',
        'north india',
        'south india',
        'central india',
        'online',
        'offline',
        'virtual',
    ];

    public function getAvailableStates(): Collection
    {
        if (! Schema::hasTable('states')) {
            return collect();
        }

        return State::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function getAvailableDistrictsByState(?string $stateId): Collection
    {
        if (! Schema::hasTable('districts') || ! Str::isUuid((string) $stateId)) {
            return collect();
        }

        return District::query()
            ->where('state_id', $stateId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (District $district) => (object) [
                'id' => (string) $district->id,
                'name' => (string) $district->name,
                'district_name' => (string) $district->name,
            ])
            ->values();
    }

    public function normalizeDistrictName(mixed $value): string
    {
        $normalized = Str::of((string) $value)
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->lower()
            ->toString();

        return in_array($normalized, self::INVALID_LOCATION_VALUES, true) ? '' : $normalized;
    }

    public function displayName(mixed $value): string
    {
        $value = Str::of((string) $value)
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();

        return $value === '' ? '' : Str::of($value)->lower()->title()->toString();
    }

    public function getAssignedDedDistrict(string $adminUserId): ?array
    {
        if (! Schema::hasTable('admin_ded_districts')) {
            return null;
        }

        $mapping = AdminDedDistrict::query()
            ->where('admin_user_id', $adminUserId)
            ->first();

        if (! $mapping) {
            return null;
        }

        $districtName = '';
        $stateName = '';

        if ($mapping->district_id && Schema::hasTable('districts')) {
            $districtName = (string) District::query()->whereKey($mapping->district_id)->value('name');
        }

        if ($mapping->state_id && Schema::hasTable('states')) {
            $stateName = (string) State::query()->whereKey($mapping->state_id)->value('name');
        }

        if ($districtName === '' && Schema::hasColumn('admin_ded_districts', 'district_name')) {
            $districtName = (string) ($mapping->district_name ?? '');
        }

        if ($stateName === '' && Schema::hasColumn('admin_ded_districts', 'state_name')) {
            $stateName = (string) ($mapping->state_name ?? '');
        }

        $districtName = $this->displayName($districtName);
        $stateName = $this->displayName($stateName);

        return $districtName !== '' ? [
            'admin_user_id' => $adminUserId,
            'state_id' => $mapping->state_id,
            'district_id' => $mapping->district_id,
            'state_name' => $stateName,
            'district_name' => $districtName,
        ] : null;
    }

    public function resolveDistrictSelection(?string $districtId, ?string $stateId = null): ?array
    {
        if (! Schema::hasTable('districts') || ! Str::isUuid((string) $districtId) || ! Str::isUuid((string) $stateId)) {
            return null;
        }

        $district = District::query()
            ->with('state:id,name')
            ->whereKey($districtId)
            ->where('status', 'active')
            ->first(['id', 'state_id', 'name']);

        if (! $district) {
            return null;
        }

        if ((string) $district->state_id !== (string) $stateId) {
            return null;
        }

        return [
            'state_id' => (string) $district->state_id,
            'state_name' => (string) ($district->state?->name ?? ''),
            'district_id' => (string) $district->id,
            'district_name' => (string) $district->name,
        ];
    }

    public function applyDedDistrictScope($query, string $userColumn = 'users.city', ?string $districtName = null): void
    {
        $districtName = $this->normalizeDistrictName($districtName);

        if ($districtName === '') {
            $query->whereRaw('1=0');
            return;
        }

        $query->whereRaw('LOWER(TRIM(COALESCE(' . $userColumn . ", ''))) = ?", [$districtName]);
    }

    public function syncFromUser(User $user): ?District
    {
        return $this->syncFromCityReference($user->city_id, $user->city);
    }

    public function syncFromCircle(Circle $circle): ?District
    {
        return $this->syncFromCityReference($circle->city_id, $circle->city);
    }

    public function syncFromCityId(?string $cityId): ?District
    {
        return $this->syncFromCityReference($cityId, null);
    }

    public function syncFromLocationName(?string $locationName): ?District
    {
        return $this->syncFromCityReference(null, $locationName);
    }

    public function upsertDistrict(?string $stateName, ?string $districtName): ?District
    {
        $stateName = $this->displayName($stateName);
        $districtName = $this->displayName($districtName);

        if ($this->normalizeDistrictName($stateName) === '' || $this->normalizeDistrictName($districtName) === '') {
            return null;
        }

        if (! Schema::hasTable('states') || ! Schema::hasTable('districts')) {
            return null;
        }

        $state = State::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [$this->normalizeDistrictName($stateName)])
            ->first(['id', 'name', 'status']);

        if (! $state) {
            $state = State::query()->create([
                'name' => $stateName,
                'status' => 'active',
            ]);
        } elseif ($state->status !== 'active') {
            $state->forceFill(['status' => 'active'])->save();
        }

        $district = District::query()
            ->where('state_id', $state->id)
            ->whereRaw('LOWER(TRIM(name)) = ?', [$this->normalizeDistrictName($districtName)])
            ->first(['id', 'state_id', 'name', 'status']);

        if (! $district) {
            return District::query()->create([
                'state_id' => $state->id,
                'name' => $districtName,
                'status' => 'active',
            ]);
        }

        if ($district->status !== 'active') {
            $district->forceFill(['status' => 'active'])->save();
        }

        return $district;
    }

    private function syncFromCityReference(?string $cityId, ?string $cityName): ?District
    {
        $city = $this->resolveCityRecord($cityId, $cityName);

        if (! $city) {
            return null;
        }

        return $this->upsertDistrict(
            $city->state ?? null,
            ($city->district ?? null) ?: ($city->name ?? null),
        );
    }

    private function resolveCityRecord(?string $cityId, ?string $cityName): ?object
    {
        if (! Schema::hasTable('cities')) {
            return null;
        }

        $columns = array_values(array_filter([
            'name',
            Schema::hasColumn('cities', 'state') ? 'state' : null,
            Schema::hasColumn('cities', 'district') ? 'district' : null,
            Schema::hasColumn('cities', 'country') ? 'country' : null,
            Schema::hasColumn('cities', 'country_code') ? 'country_code' : null,
        ]));

        if ($cityId && Str::isUuid((string) $cityId)) {
            $city = DB::table('cities')
                ->where('id', $cityId)
                ->first($columns);

            if ($city && $this->isIndianCity($city)) {
                return $city;
            }
        }

        $normalizedCityName = $this->normalizeDistrictName($cityName);

        if ($normalizedCityName === '') {
            return null;
        }

        $query = DB::table('cities')
            ->where(function ($query) use ($normalizedCityName): void {
                $query->whereRaw('LOWER(TRIM(name)) = ?', [$normalizedCityName]);

                if (Schema::hasColumn('cities', 'district')) {
                    $query->orWhereRaw('LOWER(TRIM(district)) = ?', [$normalizedCityName]);
                }
            });

        $this->applyIndianCityFilter($query);

        return $query->orderBy('name')->first($columns);
    }

    private function applyIndianCityFilter($query): void
    {
        $hasCountry = Schema::hasColumn('cities', 'country');
        $hasCountryCode = Schema::hasColumn('cities', 'country_code');

        if (! $hasCountry && ! $hasCountryCode) {
            return;
        }

        $query->where(function ($query) use ($hasCountry, $hasCountryCode): void {
            if ($hasCountry) {
                $query->whereRaw("LOWER(TRIM(COALESCE(country, ''))) = 'india'");
            }

            if ($hasCountryCode) {
                $method = $hasCountry ? 'orWhereRaw' : 'whereRaw';
                $query->{$method}("LOWER(TRIM(COALESCE(country_code, ''))) = 'in'");
            }
        });
    }

    private function isIndianCity(object $city): bool
    {
        $country = $this->normalizeDistrictName($city->country ?? '');
        $countryCode = $this->normalizeDistrictName($city->country_code ?? '');

        if ($country === '' && $countryCode === '') {
            return true;
        }

        return $country === 'india' || $countryCode === 'in';
    }
}
