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

        return $assignedIndustryId !== null && (string) $assignedIndustryId === (string) $industryId;
    }

    public function industryName($industryId): ?string
    {
        if (! Schema::hasTable('industries')) {
            return null;
        }

        return Industry::query()->whereKey((string) $industryId)->value('name');
    }

    public function industryCircleIds($industryId): array
    {
        if (! Schema::hasTable('circles')) {
            return [];
        }

        $query = DB::table('circles')->whereNull('deleted_at');

        if (Schema::hasColumn('circles', 'industry_id')) {
            $query->where('industry_id', $industryId);
        } elseif (Schema::hasColumn('circles', 'industry_tags')) {
            $industryName = $this->industryName($industryId);
            $query->where(function (QueryBuilder $tagQuery) use ($industryId, $industryName): void {
                $tagQuery->whereJsonContains('industry_tags', (string) $industryId);

                if ($industryName) {
                    $tagQuery->orWhereJsonContains('industry_tags', $industryName);
                }
            });
        } else {
            return [];
        }

        return $query->pluck('id')->map(fn ($id) => (string) $id)->unique()->values()->all();
    }

    public function industryMemberIds($industryId): Collection
    {
        $circleIds = $this->industryCircleIds($industryId);

        if ($circleIds === [] || ! Schema::hasTable('circle_members')) {
            return collect();
        }

        return DB::table('circle_members')
            ->whereIn('circle_id', $circleIds)
            ->where('status', config('circle.member_joined_status', 'approved'))
            ->whereNull('deleted_at')
            ->pluck('user_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();
    }

    public function applyMemberScope($query, $industryId)
    {
        $circleIds = $this->industryCircleIds($industryId);

        if ($circleIds === []) {
            return $query->whereRaw('1=0');
        }

        return $query->whereExists(function ($subQuery) use ($circleIds): void {
            $subQuery->selectRaw(1)
                ->from('circle_members as ide_cm')
                ->whereColumn('ide_cm.user_id', 'users.id')
                ->where('ide_cm.status', config('circle.member_joined_status', 'approved'))
                ->whereNull('ide_cm.deleted_at')
                ->whereIn('ide_cm.circle_id', $circleIds);
        });
    }

    public function applyActivityScope($query, $industryId)
    {
        return $this->applyUserColumnScope($query, 'user_id', $industryId);
    }

    public function applyPostScope($query, $industryId)
    {
        $circleIds = $this->industryCircleIds($industryId);

        if ($circleIds === []) {
            return $query->whereRaw('1=0');
        }

        return $query->where(function ($scopeQuery) use ($circleIds, $industryId): void {
            $scopeQuery->whereIn('circle_id', $circleIds)
                ->orWhereIn('user_id', $this->industryMemberIds($industryId));
        });
    }

    public function applyPendingRequestScope($query, $industryId)
    {
        $table = $this->queryTable($query);

        if ($table && Schema::hasColumn($table, 'circle_id')) {
            return $this->applyCircleColumnScope($query, 'circle_id', $industryId);
        }

        return $this->applyUserColumnScope($query, 'user_id', $industryId);
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
        return $this->applyUserColumnScope($query, 'user_id', $industryId);
    }

    public function applyUserColumnScope($query, string $column, $industryId)
    {
        $memberIds = $this->industryMemberIds($industryId);

        if ($memberIds->isEmpty()) {
            return $query->whereRaw('1=0');
        }

        return $query->whereIn($column, $memberIds->all());
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
