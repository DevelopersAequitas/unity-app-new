<?php

namespace App\Services\Admin;

use App\Models\AdminDedDistrict;
use App\Models\District;
use App\Models\State;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use stdClass;

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
    ];

    public function getAvailableStates(): Collection
    {
        $states = collect();

        $states->push($this->option('all', 'All states / current data'));

        if (Schema::hasTable('states')) {
            State::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->each(fn (State $state) => $states->push($this->option((string) $state->id, (string) $state->name)));
        }

        $this->collectStateNamesFromActualData()
            ->each(function (string $stateName) use ($states): void {
                if ($states->contains(fn ($state) => $this->sameLocation($state->name, $stateName))) {
                    return;
                }

                $states->push($this->option($this->stateNameKey($stateName), $this->displayName($stateName)));
            });

        return $states->values();
    }

    public function getAvailableDistrictsByState(?string $stateId): Collection
    {
        $stateName = $this->resolveStateName($stateId);
        $districtNames = collect();

        $this->collectCircleLocations($districtNames, $stateName);
        $this->collectUserLocations($districtNames, $stateName);
        $this->collectTableTextLocations($districtNames, 'requirements', ['city', 'city_name', 'district', 'region']);
        $this->collectTableJsonLocations($districtNames, 'requirements', ['region_filter']);
        $this->collectTableTextLocations($districtNames, 'events', ['city', 'city_name', 'district', 'location_text']);
        $this->collectTableTextLocations($districtNames, 'visitor_registrations', ['visitor_city']);

        return $districtNames
            ->map(fn (string $name) => $this->districtOption($name, $stateId, $stateName))
            ->filter()
            ->unique(fn ($option) => $this->normalizeDistrictName($option->name))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
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

        if ($value === '') {
            return '';
        }

        return Str::of($value)->lower()->title()->toString();
    }

    public function getAssignedDedDistrict(string $adminUserId): ?array
    {
        if (! Schema::hasTable('admin_ded_districts')) {
            return null;
        }

        $query = AdminDedDistrict::query()->where('admin_user_id', $adminUserId);

        $mapping = $query->first();

        if (! $mapping) {
            return null;
        }

        $districtName = Schema::hasColumn('admin_ded_districts', 'district_name')
            ? (string) ($mapping->district_name ?? '')
            : '';
        $stateName = Schema::hasColumn('admin_ded_districts', 'state_name')
            ? (string) ($mapping->state_name ?? '')
            : '';

        if ($districtName === '' && $mapping->district_id && Schema::hasTable('districts')) {
            $districtName = (string) District::query()->whereKey($mapping->district_id)->value('name');
        }

        if ($stateName === '' && $mapping->state_id && Schema::hasTable('states')) {
            $stateName = (string) State::query()->whereKey($mapping->state_id)->value('name');
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

    public function resolveDistrictSelection(?string $districtValue, ?string $stateValue = null): ?array
    {
        $districtValue = trim((string) $districtValue);

        if ($districtValue === '') {
            return null;
        }

        $stateName = $this->resolveStateName($stateValue);
        $stateId = $this->resolveStateId($stateValue, $stateName);
        $district = null;

        if (Str::isUuid($districtValue) && Schema::hasTable('districts')) {
            $district = District::query()->whereKey($districtValue)->first(['id', 'state_id', 'name']);
            if ($district) {
                $stateId = $stateId ?: (string) $district->state_id;
                $stateName = $stateName ?: $this->resolveStateName($stateId);
            }
        }

        $districtName = $district ? (string) $district->name : $districtValue;
        $districtName = $this->displayName($districtName);

        if ($districtName === '') {
            return null;
        }

        if (! $stateId && ! $stateName) {
            $stateName = $this->inferStateNameForDistrict($districtName);
            $stateId = $this->resolveStateId(null, $stateName);
        }

        if (! $district && $stateId && Schema::hasTable('districts')) {
            $district = District::query()
                ->where('state_id', $stateId)
                ->whereRaw('LOWER(TRIM(name)) = ?', [$this->normalizeDistrictName($districtName)])
                ->first(['id', 'state_id', 'name']);
        }

        if (! $district && $stateId && Schema::hasTable('districts')) {
            $district = District::query()->create([
                'state_id' => $stateId,
                'name' => $districtName,
                'status' => 'active',
            ]);
        }

        return [
            'state_id' => $stateId,
            'state_name' => $stateName ?: null,
            'district_id' => $district?->id,
            'district_name' => $districtName,
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

    private function collectStateNamesFromActualData(): Collection
    {
        $states = collect();

        if (Schema::hasTable('cities') && Schema::hasColumn('cities', 'state')) {
            DB::table('cities')
                ->whereNotNull('state')
                ->distinct()
                ->pluck('state')
                ->each(fn ($state) => $this->addLocationValue($states, $state));
        }

        foreach ([['users', 'state'], ['users', 'business_state'], ['circles', 'state'], ['events', 'state']] as [$table, $column]) {
            $this->collectTextColumn($states, $table, $column);
        }

        return $states->values();
    }

    private function collectCircleLocations(Collection $districts, ?string $stateName): void
    {
        if (! Schema::hasTable('circles')) {
            return;
        }

        if (Schema::hasColumn('circles', 'city_id') && Schema::hasTable('cities')) {
            $query = DB::table('circles')
                ->join('cities', 'cities.id', '=', 'circles.city_id')
                ->whereNotNull('circles.city_id');

            $this->applyCityStateFilter($query, $stateName);

            foreach (['name', 'district'] as $column) {
                if (Schema::hasColumn('cities', $column)) {
                    (clone $query)->whereNotNull('cities.' . $column)->distinct()->pluck('cities.' . $column)
                        ->each(fn ($value) => $this->addLocationValue($districts, $value));
                }
            }
        }

        $this->collectTableTextLocations($districts, 'circles', ['city', 'district']);
    }

    private function collectUserLocations(Collection $districts, ?string $stateName): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (Schema::hasColumn('users', 'city_id') && Schema::hasTable('cities')) {
            $query = DB::table('users')
                ->join('cities', 'cities.id', '=', 'users.city_id')
                ->whereNotNull('users.city_id');

            $this->applyCityStateFilter($query, $stateName);

            foreach (['name', 'district'] as $column) {
                if (Schema::hasColumn('cities', $column)) {
                    (clone $query)->whereNotNull('cities.' . $column)->distinct()->pluck('cities.' . $column)
                        ->each(fn ($value) => $this->addLocationValue($districts, $value));
                }
            }
        }

        $this->collectTableTextLocations($districts, 'users', ['city', 'district', 'business_city']);
    }

    private function collectTableTextLocations(Collection $districts, string $table, array $columns): void
    {
        foreach ($columns as $column) {
            $this->collectTextColumn($districts, $table, $column);
        }
    }

    private function collectTableJsonLocations(Collection $districts, string $table, array $columns): void
    {
        foreach ($columns as $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)
                ->whereNotNull($column)
                ->pluck($column)
                ->each(function ($value) use ($districts): void {
                    $decoded = is_string($value) ? json_decode($value, true) : null;
                    $items = is_array($decoded) ? $decoded : (is_array($value) ? $value : []);
                    collect($items)->flatten()->each(fn ($item) => $this->addLocationValue($districts, $item));
                });
        }
    }

    private function collectTextColumn(Collection $locations, string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)
            ->whereNotNull($column)
            ->distinct()
            ->pluck($column)
            ->each(fn ($value) => $this->addLocationValue($locations, $value));
    }

    private function addLocationValue(Collection $locations, mixed $value): void
    {
        $normalized = $this->normalizeDistrictName($value);

        if ($normalized === '' || $locations->has($normalized)) {
            return;
        }

        $locations->put($normalized, $this->displayName($value));
    }

    private function districtOption(string $districtName, ?string $stateId, ?string $stateName): ?stdClass
    {
        $districtName = $this->displayName($districtName);

        if ($districtName === '') {
            return null;
        }

        $district = null;
        $resolvedStateId = $this->resolveStateId($stateId, $stateName);

        if ($resolvedStateId && Schema::hasTable('districts')) {
            $district = District::query()
                ->where('state_id', $resolvedStateId)
                ->whereRaw('LOWER(TRIM(name)) = ?', [$this->normalizeDistrictName($districtName)])
                ->first(['id', 'name']);
        }

        return (object) [
            'id' => $district?->id ?: $districtName,
            'name' => $districtName,
            'district_name' => $districtName,
        ];
    }

    private function applyCityStateFilter($query, ?string $stateName): void
    {
        if ($stateName && Schema::hasColumn('cities', 'state')) {
            $query->whereRaw("LOWER(TRIM(COALESCE(cities.state, ''))) = ?", [$this->normalizeDistrictName($stateName)]);
        }
    }

    private function inferStateNameForDistrict(string $districtName): ?string
    {
        if (! Schema::hasTable('cities') || ! Schema::hasColumn('cities', 'state')) {
            return null;
        }

        $normalized = $this->normalizeDistrictName($districtName);

        if ($normalized === '') {
            return null;
        }

        $query = DB::table('cities')
            ->whereNotNull('state')
            ->where(function ($locationQuery) use ($normalized): void {
                if (Schema::hasColumn('cities', 'name')) {
                    $locationQuery->whereRaw("LOWER(TRIM(COALESCE(name, ''))) = ?", [$normalized]);
                } else {
                    $locationQuery->whereRaw('1=0');
                }

                if (Schema::hasColumn('cities', 'district')) {
                    $locationQuery->orWhereRaw("LOWER(TRIM(COALESCE(district, ''))) = ?", [$normalized]);
                }
            });

        $stateName = $query->value('state');

        return $stateName ? $this->displayName($stateName) : null;
    }

    private function resolveStateName(?string $stateId): ?string
    {
        $stateId = rawurldecode(trim((string) $stateId));

        if ($stateId === '' || $stateId === 'all') {
            return null;
        }

        if (str_starts_with($stateId, 'name:')) {
            return $this->displayName(substr($stateId, 5));
        }

        if (Str::isUuid($stateId) && Schema::hasTable('states')) {
            $name = State::query()->whereKey($stateId)->value('name');
            return $name ? $this->displayName($name) : null;
        }

        return $this->displayName($stateId);
    }

    private function resolveStateId(?string $stateId, ?string $stateName): ?string
    {
        $stateId = rawurldecode(trim((string) $stateId));

        if (Str::isUuid($stateId) && Schema::hasTable('states') && State::query()->whereKey($stateId)->exists()) {
            return $stateId;
        }

        if ($stateName && Schema::hasTable('states')) {
            return State::query()
                ->whereRaw('LOWER(TRIM(name)) = ?', [$this->normalizeDistrictName($stateName)])
                ->value('id');
        }

        return null;
    }

    private function stateNameKey(string $stateName): string
    {
        return 'name:' . $this->normalizeDistrictName($stateName);
    }

    private function option(string $id, string $name): stdClass
    {
        return (object) [
            'id' => $id,
            'name' => $name,
        ];
    }

    private function sameLocation(mixed $left, mixed $right): bool
    {
        return $this->normalizeDistrictName($left) === $this->normalizeDistrictName($right);
    }
}
