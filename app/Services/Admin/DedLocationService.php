<?php

namespace App\Services\Admin;

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
        '--',
        'n/a',
        'na',
        'none',
        'null',
        'no city',
        'unknown',
        'not available',
        'not applicable',
    ];

    public function getAvailableStates(): Collection
    {
        if (! Schema::hasTable('states') || ! Schema::hasColumn('states', 'name')) {
            return collect();
        }

        return DB::table('states')
            ->when(Schema::hasColumn('states', 'status'), fn (Builder $query) => $query->where('status', 'active'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (object $state): object => (object) [
                'id' => (string) $state->id,
                'name' => $this->displayName($state->name),
            ])
            ->values();
    }

    public function getAvailableDistrictsByState(?string $stateId): Collection
    {
        if (! Schema::hasTable('districts') || ! Schema::hasColumn('districts', 'name')) {
            return collect();
        }

        $query = DB::table('districts')
            ->when(Schema::hasColumn('districts', 'status'), fn (Builder $builder) => $builder->where('districts.status', 'active'));

        if ($stateId && Schema::hasColumn('districts', 'state_id')) {
            $query->where('districts.state_id', $stateId);
        }

        return $query
            ->orderBy('districts.name')
            ->get(['districts.id', 'districts.name'])
            ->map(fn (object $district): object => (object) [
                'id' => (string) $district->id,
                'name' => $this->displayName($district->name),
                'district_name' => $this->displayName($district->name),
                'district_id' => (string) $district->id,
            ])
            ->values();
    }

    public function normalizeDistrictName(?string $value): ?string
    {
        $name = $this->displayName($value);

        return $this->isUsableLocationName($name) ? $name : null;
    }

    public function getAssignedDedDistrict(string $adminUserId): ?object
    {
        if (! Schema::hasTable('admin_ded_districts')) {
            return null;
        }

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
        $stateName = $this->normalizeDistrictName($assignment->states_table_name ?? null)
            ?: $this->normalizeDistrictName($assignment->state_name ?? null);

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

    private function isUsableLocationName(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return ! in_array($this->normalizedKey($value), self::INVALID_LOCATION_VALUES, true);
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

    private function normalizedKey(?string $value): string
    {
        return Str::lower(preg_replace('/\s+/u', ' ', trim((string) $value)));
    }
}
