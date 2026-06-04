<?php

namespace App\Services\Admin;

use App\Models\AdminDedDistrict;
use App\Models\Circle;
use App\Models\District;
use App\Models\State;
use App\Models\User;
use Illuminate\Database\Query\Builder;
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
        $this->syncFromExistingLocations();

        if (! Schema::hasTable('states')) {
            return collect();
        }

        return State::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (State $state) {
                $name = $this->displayName($state->name);

                return $name === '' ? null : (object) [
                    'id' => (string) $state->id,
                    'name' => $name,
                    'dedupe_key' => $this->normalizeDistrictName($name),
                ];
            })
            ->filter()
            ->unique('dedupe_key')
            ->map(fn ($state) => (object) [
                'id' => $state->id,
                'name' => $state->name,
            ])
    private const INVALID_STATE_KEYS = [
        'national', 'allindia', 'panindia', 'india', 'global', 'worldwide',
        'eastindia', 'westindia', 'northindia', 'southindia', 'centralindia',
    ];

    private bool $locationsSynced = false;

    private ?Collection $usedLocationPairs = null;

    public function __construct(private readonly DistrictSyncService $districtSyncService)
    {
    }

    public function getAvailableStates(): Collection
    {
        if (! Schema::hasTable('states') || ! Schema::hasColumn('states', 'name')) {
            return collect();
        }

        $usedStateKeys = $this->usedLocationPairs()
            ->pluck('state_key')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($usedStateKeys === []) {
            return collect();
        }

        $unique = collect();

        DB::table('states')
            ->when(Schema::hasColumn('states', 'status'), fn (Builder $query) => $query->where('status', 'active'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->each(function (object $state) use ($unique, $usedStateKeys): void {
                $name = $this->districtSyncService->normalizeStateName($state->name ?? null);
                $key = $this->districtSyncService->stateKey($name);

                if (! $name || ! $this->isUsableStateKey($key, $name) || ! in_array($key, $usedStateKeys, true) || $unique->has($key)) {
                    return;
                }

                $unique->put($key, (object) [
                    'id' => (string) $state->id,
                    'name' => $name,
                ]);
            });

        return $unique
            ->values()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    public function getAvailableDistrictsByState(?string $stateId): Collection
    {
        $this->syncFromExistingLocations();

        if (! Schema::hasTable('districts') || ! Str::isUuid((string) $stateId)) {
            return collect();
        }

        return District::query()
            ->where('state_id', $stateId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (District $district) {
                $name = $this->cleanDistrictDisplayName($district->name);

                return $name === '' ? null : (object) [
                    'id' => (string) $district->id,
                    'name' => $name,
                    'district_name' => $name,
                    'dedupe_key' => $this->districtDedupeKey($name),
                ];
            })
            ->filter()
            ->unique('dedupe_key')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn ($district) => (object) [
                'id' => $district->id,
                'name' => $district->name,
                'district_name' => $district->district_name,
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

    public function cleanDistrictDisplayName(mixed $value): string
    {
        $value = Str::of((string) $value)
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();

        if ($value === '') {
            return '';
        }

        $value = preg_split('/,/', $value, 2)[0] ?? $value;
        $value = preg_replace('/\s+district$/i', '', trim($value)) ?? $value;

        return $this->displayName($value);
    }

    public function districtDedupeKey(mixed $value): string
    {
        return $this->normalizeDistrictName($this->cleanDistrictDisplayName($value));
    }

    public function getAssignedDedDistrict(string $adminUserId): ?array
        if (! $stateId || ! Schema::hasTable('districts') || ! Schema::hasColumn('districts', 'name')) {
            return collect();
        }

        $stateName = Schema::hasTable('states') && Schema::hasColumn('states', 'name')
            ? DB::table('states')->where('id', $stateId)->value('name')
            : null;
        $stateKey = $this->districtSyncService->stateKey($stateName);

        if (! $this->isUsableStateKey($stateKey, $stateName)) {
            return collect();
        }

        $usedDistrictKeys = $this->usedLocationPairs()
            ->filter(fn (object $pair): bool => (string) $pair->state_key === $stateKey)
            ->pluck('district_key')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($usedDistrictKeys === []) {
            return collect();
        }

        $query = DB::table('districts')
            ->when(Schema::hasColumn('districts', 'status'), fn (Builder $builder) => $builder->where('districts.status', 'active'));

        if (Schema::hasColumn('districts', 'state_id')) {
            $stateIds = $this->equivalentStateIds($stateId);
            $query->whereIn('districts.state_id', $stateIds !== [] ? $stateIds : [$stateId]);
        }

        $districts = $query->orderBy('districts.name')->get(['districts.id', 'districts.name'])
            ->filter(fn (object $district): bool => in_array($this->districtSyncService->districtKey($district->name ?? null), $usedDistrictKeys, true));

        return $this->districtSyncService->uniqueDistrictRows($districts);
    }


    public function districtBelongsToState(string $districtId, string $stateId): bool
    {
        return $this->getAvailableDistrictsByState($stateId)
            ->contains(fn (object $district): bool => (string) $district->id === (string) $districtId);
    }

    public function canonicalStateIdForDistrict(string $districtId, ?string $fallbackStateId = null): ?string
    {
        if (! Schema::hasTable('districts') || ! Schema::hasColumn('districts', 'state_id')) {
            return $fallbackStateId;
        }

        return DB::table('districts')->where('id', $districtId)->value('state_id') ?: $fallbackStateId;
    }

    public function normalizeDistrictName(?string $value): ?string
    {
        return $this->districtSyncService->normalizeDistrictName($value);
    }

    public function getAssignedDedDistrict(string $adminUserId): ?object
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

        $districtName = $this->cleanDistrictDisplayName($districtName);
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
            'district_name' => $this->cleanDistrictDisplayName($district->name),
        ];
    }

    public function applyDedDistrictScope($query, string $userColumn = 'users.city', ?string $districtName = null): void
    {
        $districtName = $this->normalizeDistrictName($districtName);

        if ($districtName === '') {
        $query = DB::table('admin_ded_districts')
            ->where('admin_ded_districts.admin_user_id', $adminUserId);

        if (Schema::hasTable('districts') && Schema::hasColumn('admin_ded_districts', 'district_id')) {
            $query->leftJoin('districts', 'districts.id', '=', 'admin_ded_districts.district_id');
        }

        if (Schema::hasTable('states') && Schema::hasColumn('admin_ded_districts', 'state_id')) {
            $query->leftJoin('states', 'states.id', '=', 'admin_ded_districts.state_id');
        }

        $selects = ['admin_ded_districts.admin_user_id'];
        foreach (['state_id', 'district_id', 'state_name', 'district_name'] as $column) {
            if (Schema::hasColumn('admin_ded_districts', $column)) {
                $selects[] = 'admin_ded_districts.' . $column;
            }
        }

        if (Schema::hasTable('districts') && Schema::hasColumn('admin_ded_districts', 'district_id')) {
            $selects[] = 'districts.name as districts_table_name';
        }

        if (Schema::hasTable('states') && Schema::hasColumn('admin_ded_districts', 'state_id')) {
            $selects[] = 'states.name as states_table_name';
        }

        $assignment = $query->select($selects)->first();

        if (! $assignment) {
            return null;
        }

        $districtName = $this->normalizeDistrictName($assignment->districts_table_name ?? null)
            ?: $this->normalizeDistrictName($assignment->district_name ?? null);
        $stateName = $this->districtSyncService->normalizeStateName($assignment->states_table_name ?? null)
            ?: $this->districtSyncService->normalizeStateName($assignment->state_name ?? null);

        return (object) [
            'state_id' => $assignment->state_id ?? null,
            'state_name' => $stateName,
            'district_id' => $assignment->district_id ?? null,
            'district_name' => $districtName,
        ];
    }

    public function applyDedDistrictScope($query, ?string $districtName, string $userColumn = 'users.city'): void
    {
        $districtName = $this->normalizeDistrictName($districtName);

        if (! $districtName) {
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
        $districtName = $this->cleanDistrictDisplayName($districtName);

        if ($this->normalizeDistrictName($stateName) === '' || $this->districtDedupeKey($districtName) === '') {
            return null;
        }

        if (! Schema::hasTable('states') || ! Schema::hasTable('districts')) {
            return null;
        }

        $state = State::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [$this->normalizeDistrictName($stateName)])
            ->first(['id', 'name', 'status']);

        if (! $state) {
            if (! $this->stateExistsInIndianCities($stateName)) {
                return null;
            }

            $state = State::query()->create([
                'name' => $stateName,
                'status' => 'active',
            ]);
        } elseif ($state->status !== 'active') {
            $state->forceFill(['status' => 'active'])->save();
        }

        $district = District::query()
            ->where('state_id', $state->id)
            ->get(['id', 'state_id', 'name', 'status'])
            ->first(fn (District $district) => $this->districtDedupeKey($district->name) === $this->districtDedupeKey($districtName));

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

    public function syncFromExistingLocations(): void
    {
        $this->syncModelLocations('users');
        $this->syncModelLocations('circles');
        $this->syncStateDistrictPairs('users');
        $this->syncStateDistrictPairs('circles');
    }

    private function syncModelLocations(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $query = DB::table($table)->select('id');

        if (Schema::hasColumn($table, 'city_id')) {
            $query->addSelect('city_id');
        }

        if (Schema::hasColumn($table, 'city')) {
            $query->addSelect('city');
        }

        if (! Schema::hasColumn($table, 'city_id') && ! Schema::hasColumn($table, 'city')) {
            return;
        }

        $query->where(function ($query) use ($table): void {
            if (Schema::hasColumn($table, 'city_id')) {
                $query->whereNotNull('city_id');
            }

            if (Schema::hasColumn($table, 'city')) {
                $method = Schema::hasColumn($table, 'city_id') ? 'orWhereNotNull' : 'whereNotNull';
                $query->{$method}('city');
            }
        })
            ->orderBy('id')
            ->chunk(500, function ($rows): void {
                foreach ($rows as $row) {
                    $this->syncFromCityReference($row->city_id ?? null, $row->city ?? null);
                }
            });
    }

    private function syncStateDistrictPairs(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $stateColumn = collect(['state', 'business_state'])->first(fn (string $column) => Schema::hasColumn($table, $column));
        $districtColumn = collect(['district', 'district_name'])->first(fn (string $column) => Schema::hasColumn($table, $column));

        if (! $stateColumn || ! $districtColumn) {
            return;
        }

        DB::table($table)
            ->select($stateColumn . ' as state_name', $districtColumn . ' as district_name')
            ->whereNotNull($stateColumn)
            ->whereNotNull($districtColumn)
            ->distinct()
            ->orderBy($stateColumn)
            ->chunk(500, function ($rows): void {
                foreach ($rows as $row) {
                    $this->upsertDistrict($row->state_name ?? null, $row->district_name ?? null);
                }
            });
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

    private function stateExistsInIndianCities(string $stateName): bool
    {
        if (! Schema::hasTable('cities') || ! Schema::hasColumn('cities', 'state')) {
            return false;
        }

        $query = DB::table('cities')
            ->whereRaw('LOWER(TRIM(state)) = ?', [$this->normalizeDistrictName($stateName)]);

        $this->applyIndianCityFilter($query);

        return $query->exists();
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
        $query->whereRaw('LOWER(NULLIF(TRIM(' . $userColumn . "), '')) = ?", [Str::lower($districtName)]);
    }

    public function resolveDistrictId(?string $districtName, ?string $stateId = null): ?string
    {
        $districtName = $this->normalizeDistrictName($districtName);

        if (! $districtName || ! Schema::hasTable('districts') || ! Schema::hasColumn('districts', 'name')) {
            return null;
        }

        $query = DB::table('districts')
            ->whereRaw("LOWER(NULLIF(TRIM(name), '')) = ?", [Str::lower($districtName)]);

        if ($stateId && Schema::hasColumn('districts', 'state_id')) {
            $query->where('state_id', $stateId);
        }

        if (Schema::hasColumn('districts', 'status')) {
            $query->where('status', 'active');
        }

        return $query->value('id') ?: null;
    }

    public function resolveStateName(?string $stateId): ?string
    {
        if (! $stateId || ! Schema::hasTable('states') || ! Schema::hasColumn('states', 'name')) {
            return null;
        }

        $state = DB::table('states')
            ->where('id', $stateId)
            ->value('name');

        return $state ? $this->displayName($state) : null;
    }

    public function syncKnownLocations(): void
    {
        if ($this->locationsSynced) {
            return;
        }

        $this->districtSyncService->syncKnownLocations();
        $this->locationsSynced = true;
    }


    private function usedLocationPairs(): Collection
    {
        if ($this->usedLocationPairs !== null) {
            return $this->usedLocationPairs;
        }

        $pairs = collect();

        $this->appendUsedLocationsFromCityRelation($pairs, 'users');
        $this->appendUsedLocationsFromCityRelation($pairs, 'circles');
        $this->appendUsedLocationsFromDirectColumns($pairs, 'users', 'city', 'state');
        $this->appendUsedLocationsFromDirectColumns($pairs, 'users', 'business_city', 'business_state');
        $this->appendUsedLocationsFromDirectColumns($pairs, 'users', 'district', 'state');
        $this->appendUsedLocationsFromDirectColumns($pairs, 'circles', 'city', 'state');
        $this->appendUsedLocationsFromDirectColumns($pairs, 'circles', 'district', 'state');
        $this->appendUsedLocationsFromDedAssignments($pairs);

        return $this->usedLocationPairs = $pairs
            ->filter(fn (object $pair): bool => $pair->state_key !== '' && $pair->district_key !== '')
            ->unique(fn (object $pair): string => $pair->state_key . '|' . $pair->district_key)
            ->values();
    }

    private function appendUsedLocationsFromCityRelation(Collection $pairs, string $ownerTable): void
    {
        if (! Schema::hasTable($ownerTable) || ! Schema::hasColumn($ownerTable, 'city_id') || ! Schema::hasTable('cities') || ! Schema::hasColumn('cities', 'state')) {
            return;
        }

        $districtExpression = Schema::hasColumn('cities', 'district')
            ? "COALESCE(NULLIF(TRIM(cities.district), ''), cities.name) as district_name"
            : 'cities.name as district_name';

        DB::table($ownerTable)
            ->join('cities', 'cities.id', '=', $ownerTable . '.city_id')
            ->whereNotNull($ownerTable . '.city_id')
            ->whereNotNull('cities.state')
            ->whereRaw("NULLIF(TRIM(cities.state), '') IS NOT NULL")
            ->distinct()
            ->get(['cities.state as state_name', DB::raw($districtExpression)])
            ->each(fn (object $row) => $this->pushUsedLocationPair($pairs, $row->state_name ?? null, $row->district_name ?? null));
    }

    private function appendUsedLocationsFromDirectColumns(Collection $pairs, string $table, string $districtColumn, string $stateColumn): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $districtColumn)) {
            return;
        }

        $columns = [$districtColumn . ' as district_name'];
        $hasStateColumn = Schema::hasColumn($table, $stateColumn);
        if ($hasStateColumn) {
            $columns[] = $stateColumn . ' as state_name';
        }

        DB::table($table)
            ->whereNotNull($districtColumn)
            ->whereRaw("NULLIF(TRIM({$districtColumn}), '') IS NOT NULL")
            ->distinct()
            ->get($columns)
            ->each(function (object $row) use ($pairs, $hasStateColumn): void {
                $stateName = $hasStateColumn ? ($row->state_name ?? null) : null;
                $districtName = $row->district_name ?? null;

                if (! $this->isUsableLocationPair($stateName, $districtName)) {
                    $location = $this->canonicalLocationForDistrictName($districtName);
                    $stateName = $location->state_name ?? $stateName;
                    $districtName = $location->district_name ?? $districtName;
                }

                $this->pushUsedLocationPair($pairs, $stateName, $districtName);
            });
    }

    private function appendUsedLocationsFromDedAssignments(Collection $pairs): void
    {
        if (! Schema::hasTable('admin_ded_districts')) {
            return;
        }

        $query = DB::table('admin_ded_districts');
        $selects = [];

        if (Schema::hasColumn('admin_ded_districts', 'state_name')) {
            $selects[] = 'admin_ded_districts.state_name as assigned_state_name';
        }
        if (Schema::hasColumn('admin_ded_districts', 'district_name')) {
            $selects[] = 'admin_ded_districts.district_name as assigned_district_name';
        }

        if (Schema::hasColumn('admin_ded_districts', 'state_id') && Schema::hasTable('states') && Schema::hasColumn('states', 'name')) {
            $query->leftJoin('states', 'states.id', '=', 'admin_ded_districts.state_id');
            $selects[] = 'states.name as state_table_name';
        }

        if (Schema::hasColumn('admin_ded_districts', 'district_id') && Schema::hasTable('districts') && Schema::hasColumn('districts', 'name')) {
            $query->leftJoin('districts', 'districts.id', '=', 'admin_ded_districts.district_id');
            $selects[] = 'districts.name as district_table_name';
        }

        if ($selects === []) {
            return;
        }

        $query->select($selects)
            ->distinct()
            ->get()
            ->each(function (object $row) use ($pairs): void {
                $this->pushUsedLocationPair(
                    $pairs,
                    $row->state_table_name ?? $row->assigned_state_name ?? null,
                    $row->district_table_name ?? $row->assigned_district_name ?? null,
                );
            });
    }

    private function canonicalLocationForDistrictName(?string $districtName): ?object
    {
        $districtKey = $this->districtSyncService->districtKey($districtName);
        if ($districtKey === '' || ! Schema::hasTable('cities') || ! Schema::hasColumn('cities', 'state')) {
            return null;
        }

        $columns = ['cities.name', 'cities.state'];
        if (Schema::hasColumn('cities', 'district')) {
            $columns[] = 'cities.district';
        }

        return DB::table('cities')
            ->where(function (Builder $query) use ($districtKey): void {
                $query->whereRaw("REGEXP_REPLACE(LOWER(COALESCE(cities.name, '')), '[^a-z0-9]+', '', 'g') = ?", [$districtKey]);

                if (Schema::hasColumn('cities', 'district')) {
                    $query->orWhereRaw("REGEXP_REPLACE(LOWER(COALESCE(cities.district, '')), '[^a-z0-9]+', '', 'g') = ?", [$districtKey]);
                }
            })
            ->whereNotNull('cities.state')
            ->whereRaw("NULLIF(TRIM(cities.state), '') IS NOT NULL")
            ->get($columns)
            ->map(function (object $city): object {
                return (object) [
                    'state_name' => $city->state ?? null,
                    'district_name' => $city->district ?? $city->name ?? null,
                ];
            })
            ->first(fn (object $location): bool => $this->isUsableLocationPair($location->state_name ?? null, $location->district_name ?? null));
    }

    private function pushUsedLocationPair(Collection $pairs, ?string $stateName, ?string $districtName): void
    {
        if (! $this->isUsableLocationPair($stateName, $districtName)) {
            return;
        }

        $stateName = $this->districtSyncService->normalizeStateName($stateName);
        $districtName = $this->districtSyncService->normalizeDistrictName($districtName);

        $pairs->push((object) [
            'state_name' => $stateName,
            'district_name' => $districtName,
            'state_key' => $this->districtSyncService->stateKey($stateName),
            'district_key' => $this->districtSyncService->districtKey($districtName),
        ]);
    }

    private function isUsableLocationPair(?string $stateName, ?string $districtName): bool
    {
        $stateName = $this->districtSyncService->normalizeStateName($stateName);
        $districtName = $this->districtSyncService->normalizeDistrictName($districtName);

        if (! $stateName || ! $districtName) {
            return false;
        }

        return $this->isUsableStateKey($this->districtSyncService->stateKey($stateName), $stateName)
            && $this->districtSyncService->districtKey($districtName) !== '';
    }

    private function isUsableStateKey(string $stateKey, ?string $stateName): bool
    {
        if ($stateKey === '' || in_array($stateKey, self::INVALID_STATE_KEYS, true)) {
            return false;
        }

        return mb_strlen((string) $this->districtSyncService->normalizeStateName($stateName)) >= 3;
    }

    private function equivalentStateIds(string $stateId): array
    {
        if (! Schema::hasTable('states') || ! Schema::hasColumn('states', 'name')) {
            return [$stateId];
        }

        $stateName = DB::table('states')->where('id', $stateId)->value('name');
        $stateKey = $this->districtSyncService->stateKey($stateName);

        if ($stateKey === '') {
            return [$stateId];
        }

        return DB::table('states')
            ->get(['id', 'name'])
            ->filter(fn (object $state): bool => $this->districtSyncService->stateKey($state->name ?? null) === $stateKey)
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->values()
            ->all();
    }

    private function displayName(?string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim((string) $value));
        $value = trim($value, '"');

        if ($value === '') {
            return '';
        }

        return Str::title(Str::lower($value));
    }
}
