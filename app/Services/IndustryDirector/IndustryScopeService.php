<?php

namespace App\Services\IndustryDirector;

use App\Models\Industry;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IndustryScopeService
{
    public function assignedIndustryIdForAdmin(string $adminUserId): ?string
    {
        if (! Schema::hasTable('industry_director_assignments')) {
            return null;
        }

        $industryId = DB::table('industry_director_assignments')
            ->where('admin_user_id', $adminUserId)
            ->where('is_active', true)
            ->value('industry_id');

        return $industryId !== null ? (string) $industryId : null;
    }

    public function assignedIndustryOrFail(string $adminUserId): string
    {
        $industryId = $this->assignedIndustryIdForAdmin($adminUserId);

        if (! $industryId) {
            throw new HttpException(403, 'Industry Director industry assignment missing.');
        }

        return $industryId;
    }

    public function adminHasIndustryAccess(string $adminUserId, $industryId): bool
    {
        $assignedIndustryId = $this->assignedIndustryIdForAdmin($adminUserId);

        return $assignedIndustryId !== null
            && in_array((string) $industryId, $this->industryAndChildIds($assignedIndustryId), true);
    }

    public function industryName($industryId): ?string
    {
        if (! Schema::hasTable('industries')) {
            return null;
        }

        return Industry::query()->whereKey((string) $industryId)->value('name');
    }

    public function industryAndChildIds($industryId): array
    {
        if (! Schema::hasTable('industries') || blank($industryId)) {
            return [(string) $industryId];
        }

        $ids = collect([(string) $industryId]);
        $frontier = collect([(string) $industryId]);

        while ($frontier->isNotEmpty()) {
            $children = DB::table('industries')
                ->whereIn('parent_id', $frontier->all())
                ->when(Schema::hasColumn('industries', 'is_active'), fn ($query) => $query->where('is_active', true))
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->diff($ids)
                ->values();

            if ($children->isEmpty()) {
                break;
            }

            $ids = $ids->merge($children)->unique()->values();
            $frontier = $children;
        }

        return $ids->unique()->values()->all();
    }

    public function industryNamesForScope($industryId): array
    {
        if (! Schema::hasTable('industries')) {
            return [];
        }

        return DB::table('industries')
            ->whereIn('id', $this->industryAndChildIds($industryId))
            ->pluck('name')
            ->filter(fn ($name) => filled($name))
            ->map(fn ($name) => trim((string) $name))
            ->unique()
            ->values()
            ->all();
    }

    public function industryCircleIds($industryId): array
    {
        if (! Schema::hasTable('circles')) {
            return [];
        }

        $industryIds = $this->industryAndChildIds($industryId);
        $industryNames = $this->industryNamesForScope($industryId);
        $categoryScope = $this->categoryScopeForIndustry($industryId);
        $circleIds = collect();

        $circleQuery = DB::table('circles')->select('id');
        $this->withoutDeleted($circleQuery, 'circles');

        $circleQuery->where(function (QueryBuilder $query) use ($industryIds, $industryNames): void {
            $hasCondition = false;

            if (Schema::hasColumn('circles', 'industry_id')) {
                $query->whereIn('industry_id', $industryIds);
                $hasCondition = true;
            }

            if (Schema::hasColumn('circles', 'industry_tags')) {
                $query->when($hasCondition, fn (QueryBuilder $q) => $q->orWhere(function (QueryBuilder $tags) use ($industryIds, $industryNames): void {
                    $this->whereJsonContainsAny($tags, 'industry_tags', array_merge($industryIds, $industryNames));
                }), fn (QueryBuilder $q) => $q->where(function (QueryBuilder $tags) use ($industryIds, $industryNames): void {
                    $this->whereJsonContainsAny($tags, 'industry_tags', array_merge($industryIds, $industryNames));
                }));
                $hasCondition = true;
            }

            if (! $hasCondition) {
                $query->whereRaw('1=0');
            }
        });

        $circleIds = $circleIds->merge($circleQuery->pluck('id'));

        if (Schema::hasTable('circle_category_mappings')) {
            $mappingQuery = DB::table('circle_category_mappings')->select('circle_id');
            $mappingQuery->where(function (QueryBuilder $query) use ($categoryScope): void {
                $hasCondition = false;

                foreach ([
                    'category_id' => $categoryScope['level1'],
                    'circle_category_id' => $categoryScope['level1'],
                    'level2_id' => $categoryScope['level2'],
                    'level3_id' => $categoryScope['level3'],
                    'level4_id' => $categoryScope['level4'],
                ] as $column => $ids) {
                    if (Schema::hasColumn('circle_category_mappings', $column) && $ids !== []) {
                        $hasCondition ? $query->orWhereIn($column, $ids) : $query->whereIn($column, $ids);
                        $hasCondition = true;
                    }
                }

                if (! $hasCondition) {
                    $query->whereRaw('1=0');
                }
            });

            $circleIds = $circleIds->merge($mappingQuery->pluck('circle_id'));
        }

        return $circleIds->map(fn ($id) => (string) $id)->unique()->values()->all();
    }

    public function industryMemberIds($industryId): Collection
    {
        $memberIds = collect();
        $circleIds = $this->industryCircleIds($industryId);
        $categoryScope = $this->categoryScopeForIndustry($industryId);
        $industryIds = $this->industryAndChildIds($industryId);
        $industryNames = $this->industryNamesForScope($industryId);

        if (Schema::hasTable('users')) {
            $userQuery = DB::table('users')->select('id');
            $this->withoutDeleted($userQuery, 'users');
            $userQuery->where(function (QueryBuilder $query) use ($categoryScope, $industryIds, $industryNames): void {
                $hasCondition = false;

                if (Schema::hasColumn('users', 'main_business_category_id') && $categoryScope['level1'] !== []) {
                    $query->whereIn('main_business_category_id', $categoryScope['level1']);
                    $hasCondition = true;
                }

                if (Schema::hasColumn('users', 'business_category_id') && $categoryScope['level4'] !== []) {
                    $hasCondition
                        ? $query->orWhereIn('business_category_id', $categoryScope['level4'])
                        : $query->whereIn('business_category_id', $categoryScope['level4']);
                    $hasCondition = true;
                }

                foreach (['industry_tags', 'industries_of_interest'] as $jsonColumn) {
                    if (Schema::hasColumn('users', $jsonColumn)) {
                        $hasCondition
                            ? $query->orWhere(function (QueryBuilder $jsonQuery) use ($jsonColumn, $industryIds, $industryNames): void {
                                $this->whereJsonContainsAny($jsonQuery, $jsonColumn, array_merge($industryIds, $industryNames));
                            })
                            : $query->where(function (QueryBuilder $jsonQuery) use ($jsonColumn, $industryIds, $industryNames): void {
                                $this->whereJsonContainsAny($jsonQuery, $jsonColumn, array_merge($industryIds, $industryNames));
                            });
                        $hasCondition = true;
                    }
                }

                if (! $hasCondition) {
                    $query->whereRaw('1=0');
                }
            });

            $memberIds = $memberIds->merge($userQuery->pluck('id'));
        }

        if ($circleIds !== [] && Schema::hasTable('circle_members')) {
            $circleMemberQuery = DB::table('circle_members')
                ->whereIn('circle_id', $circleIds)
                ->where('status', config('circle.member_joined_status', 'approved'));
            $this->withoutDeleted($circleMemberQuery, 'circle_members');

            $memberIds = $memberIds->merge($circleMemberQuery->pluck('user_id'));
        }

        if (Schema::hasTable('joined_circle_categories')) {
            $joinedQuery = DB::table('joined_circle_categories')->select('user_id');
            $joinedQuery->where(function (QueryBuilder $query) use ($categoryScope): void {
                $hasCondition = false;

                foreach ([
                    'level1_category_id' => $categoryScope['level1'],
                    'level2_category_id' => $categoryScope['level2'],
                    'level3_category_id' => $categoryScope['level3'],
                    'level4_category_id' => $categoryScope['level4'],
                ] as $column => $ids) {
                    if (Schema::hasColumn('joined_circle_categories', $column) && $ids !== []) {
                        $hasCondition ? $query->orWhereIn($column, $ids) : $query->whereIn($column, $ids);
                        $hasCondition = true;
                    }
                }

                if (! $hasCondition) {
                    $query->whereRaw('1=0');
                }
            });

            $memberIds = $memberIds->merge($joinedQuery->pluck('user_id'));
        }

        return $memberIds
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();
    }

    public function scopeDebugSnapshot($industryId): array
    {
        $childIndustryIds = array_values(array_diff($this->industryAndChildIds($industryId), [(string) $industryId]));
        $circleIds = $this->industryCircleIds($industryId);
        $memberIds = $this->industryMemberIds($industryId);

        return [
            'assigned_industry_id' => (string) $industryId,
            'child_industry_ids' => $childIndustryIds,
            'scoped_member_count' => $memberIds->count(),
            'circle_count' => count($circleIds),
        ];
    }

    public function applyMemberScope($query, $industryId)
    {
        $memberIds = $this->industryMemberIds($industryId);

        if ($memberIds->isEmpty()) {
            return $query->whereRaw('1=0');
        }

        $column = $query instanceof EloquentBuilder ? $query->getModel()->getTable() . '.id' : 'users.id';

        return $query->whereIn($column, $memberIds->all());
    }

    public function applyActivityScope($query, $industryId)
    {
        $table = $this->queryTable($query);
        $columns = collect(['user_id', 'from_user_id', 'to_user_id', 'initiator_user_id', 'peer_user_id', 'requester_id', 'invitee_id'])
            ->filter(fn (string $column) => $table && Schema::hasColumn($table, $column))
            ->values()
            ->all();

        return $this->applyAnyUserColumnScope($query, $columns ?: ['user_id'], $industryId);
    }

    public function applyPostScope($query, $industryId)
    {
        return $this->applyUserColumnScope($query, 'user_id', $industryId);
    }

    public function applyPendingRequestScope($query, $industryId)
    {
        $table = $this->queryTable($query);

        if ($table && Schema::hasColumn($table, 'circle_id')) {
            return $this->applyCircleColumnScope($query, 'circle_id', $industryId);
        }

        return $this->applyActivityScope($query, $industryId);
    }

    public function applyCircleScope($query, $industryId)
    {
        $circleIds = $this->industryCircleIds($industryId);

        if ($circleIds === []) {
            return $query->whereRaw('1=0');
        }

        $column = $query instanceof EloquentBuilder ? $query->getModel()->getTable() . '.id' : 'id';

        return $query->whereIn($column, $circleIds);
    }

    public function applyCoinsScope($query, $industryId)
    {
        return $this->applyUserColumnScope($query, 'user_id', $industryId);
    }

    public function applyLifeImpactScope($query, $industryId)
    {
        return $this->applyAnyUserColumnScope($query, ['user_id', 'triggered_by_user_id'], $industryId);
    }

    public function applyUserColumnScope($query, string $column, $industryId)
    {
        $memberIds = $this->industryMemberIds($industryId);

        if ($memberIds->isEmpty()) {
            return $query->whereRaw('1=0');
        }

        return $query->whereIn($column, $memberIds->all());
    }

    public function applyAnyUserColumnScope($query, array $columns, $industryId)
    {
        $memberIds = $this->industryMemberIds($industryId);
        $table = $this->queryTable($query);
        $columns = collect($columns)
            ->filter(fn ($column) => is_string($column) && $column !== '')
            ->filter(fn (string $column) => ! $table || Schema::hasColumn($table, $column))
            ->unique()
            ->values();

        if ($memberIds->isEmpty() || $columns->isEmpty()) {
            return $query->whereRaw('1=0');
        }

        return $query->where(function ($scopeQuery) use ($columns, $memberIds): void {
            foreach ($columns->values() as $index => $column) {
                $index === 0
                    ? $scopeQuery->whereIn($column, $memberIds->all())
                    : $scopeQuery->orWhereIn($column, $memberIds->all());
            }
        });
    }

    public function applyCircleColumnScope($query, string $column, $industryId)
    {
        $circleIds = $this->industryCircleIds($industryId);

        if ($circleIds === []) {
            return $query->whereRaw('1=0');
        }

        return $query->whereIn($column, $circleIds);
    }

    public function userInIndustry(string $userId, $industryId): bool
    {
        return $this->industryMemberIds($industryId)->contains((string) $userId);
    }

    private function categoryScopeForIndustry($industryId): array
    {
        $names = $this->industryNamesForScope($industryId);
        $level1 = $this->categoryIdsByName('circle_categories', $names);

        if (Schema::hasTable('circle_categories') && Schema::hasColumn('circle_categories', 'parent_id')) {
            $level1 = $this->collectSelfReferencingCategoryDescendants($level1, 'circle_categories', 'parent_id');
        }

        $level2 = $this->categoryIdsByName($this->level2Table(), $names);
        if ($level1 !== [] && Schema::hasTable($this->level2Table()) && Schema::hasColumn($this->level2Table(), 'circle_category_id')) {
            $level2 = collect($level2)
                ->merge(DB::table($this->level2Table())->whereIn('circle_category_id', $level1)->pluck('id'))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        $level3 = $this->categoryIdsByName($this->level3Table(), $names);
        if ($level2 !== [] && Schema::hasTable($this->level3Table()) && Schema::hasColumn($this->level3Table(), 'level2_id')) {
            $level3 = collect($level3)
                ->merge(DB::table($this->level3Table())->whereIn('level2_id', $level2)->pluck('id'))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        $level4 = $this->categoryIdsByName($this->level4Table(), $names);
        if (Schema::hasTable($this->level4Table())) {
            $level4Query = DB::table($this->level4Table())->select('id');
            $level4Query->where(function (QueryBuilder $query) use ($level1, $level2, $level3): void {
                $hasCondition = false;

                foreach ([
                    'circle_category_id' => $level1,
                    'level2_id' => $level2,
                    'level3_id' => $level3,
                ] as $column => $ids) {
                    if (Schema::hasColumn($this->level4Table(), $column) && $ids !== []) {
                        $hasCondition ? $query->orWhereIn($column, $ids) : $query->whereIn($column, $ids);
                        $hasCondition = true;
                    }
                }

                if (! $hasCondition) {
                    $query->whereRaw('1=0');
                }
            });

            $level4 = collect($level4)
                ->merge($level4Query->pluck('id'))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        return [
            'level1' => $level1,
            'level2' => $level2,
            'level3' => $level3,
            'level4' => $level4,
        ];
    }

    private function categoryIdsByName(string $table, array $names): array
    {
        if ($names === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, 'name')) {
            return [];
        }

        return DB::table($table)
            ->whereIn(DB::raw('LOWER(name)'), collect($names)->map(fn ($name) => strtolower((string) $name))->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function collectSelfReferencingCategoryDescendants(array $rootIds, string $table, string $parentColumn): array
    {
        $ids = collect($rootIds)->map(fn ($id) => (int) $id)->unique()->values();
        $frontier = $ids;

        while ($frontier->isNotEmpty()) {
            $children = DB::table($table)
                ->whereIn($parentColumn, $frontier->all())
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->diff($ids)
                ->values();

            if ($children->isEmpty()) {
                break;
            }

            $ids = $ids->merge($children)->unique()->values();
            $frontier = $children;
        }

        return $ids->all();
    }

    private function whereJsonContainsAny(QueryBuilder $query, string $column, array $values): void
    {
        $values = collect($values)
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => (string) $value)
            ->unique()
            ->values();

        if ($values->isEmpty()) {
            $query->whereRaw('1=0');
            return;
        }

        foreach ($values as $index => $value) {
            $index === 0
                ? $query->whereJsonContains($column, $value)
                : $query->orWhereJsonContains($column, $value);
        }
    }

    private function withoutDeleted(QueryBuilder $query, string $table): QueryBuilder
    {
        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull($table . '.deleted_at');
        }

        return $query;
    }

    private function level2Table(): string
    {
        return Schema::hasTable('level2_categories') ? 'level2_categories' : 'circle_category_level2';
    }

    private function level3Table(): string
    {
        return Schema::hasTable('level3_categories') ? 'level3_categories' : 'circle_category_level3';
    }

    private function level4Table(): string
    {
        return Schema::hasTable('level4_categories') ? 'level4_categories' : 'circle_category_level4';
    }

    private function queryTable($query): ?string
    {
        if ($query instanceof EloquentBuilder) {
            return $query->getModel()->getTable();
        }

        if ($query instanceof QueryBuilder) {
            return is_string($query->from) ? $query->from : null;
        }

        return null;
    }
}
