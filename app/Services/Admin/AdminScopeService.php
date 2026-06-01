<?php

namespace App\Services\Admin;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\Industry;
use App\Models\IndustryDirectorAssignment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class AdminScopeService
{
    public function roleKeys(User $user): array
    {
        return $user->roles()->pluck('roles.key')->unique()->values()->all();
    }

    public function isGlobal(User $user): bool
    {
        return in_array('global_admin', $this->roleKeys($user), true);
    }


    public function isIndustryDirector(User $user): bool
    {
        return in_array('industry_director', $this->roleKeys($user), true);
    }

    public function assignedIndustryIds(User $user): array
    {
        if (! $this->isIndustryDirector($user)) {
            return [];
        }

        $ids = collect();

        if (Schema::hasTable('industry_director_assignments')) {
            $ids = $ids->merge(IndustryDirectorAssignment::query()
                ->where('user_id', $user->id)
                ->pluck('industry_id'));
        }

        if (Schema::hasColumn('circles', 'industry_id')) {
            $ids = $ids->merge(Circle::query()
                ->where('industry_director_user_id', $user->id)
                ->whereNotNull('industry_id')
                ->pluck('industry_id'));
        }

        if ($ids->isEmpty() && Schema::hasColumn('circles', 'industry_tags')) {
            $tags = Circle::query()
                ->where('industry_director_user_id', $user->id)
                ->pluck('industry_tags')
                ->flatten()
                ->filter()
                ->unique()
                ->values();

            if ($tags->isNotEmpty()) {
                $uuidTags = $tags->filter(fn ($tag) => is_string($tag) && preg_match('/^[0-9a-fA-F-]{36}$/', $tag));
                $nameTags = $tags->diff($uuidTags);

                $ids = $ids->merge(Industry::query()
                    ->where(function (Builder $query) use ($uuidTags, $nameTags): void {
                        if ($uuidTags->isNotEmpty()) {
                            $query->whereIn('id', $uuidTags);
                        }

                        if ($nameTags->isNotEmpty()) {
                            $method = $uuidTags->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                            $query->{$method}('name', $nameTags);
                        }
                    })
                    ->pluck('id'));
            }
        }

        return $ids->map(fn ($id) => (string) $id)->unique()->values()->all();
    }

    public function visibleCircleIds(User $user): array
    {
        if ($this->isGlobal($user)) {
            return Circle::query()->pluck('id')->all();
        }

        if ($this->isIndustryDirector($user)) {
            return $this->circleIdsForIndustries($this->assignedIndustryIds($user));
        }

        $direct = Circle::query()
            ->where(fn ($q) => $q->where('founder_user_id', $user->id)
                ->orWhere('director_user_id', $user->id)
                ->orWhere('ded_user_id', $user->id))
            ->pluck('id');

        $member = CircleMember::query()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->pluck('circle_id');

        return $direct->merge($member)->unique()->values()->all();
    }

    public function visibleIndustryIds(User $user): array
    {
        if ($this->isGlobal($user)) {
            return \App\Models\Industry::query()->pluck('id')->all();
        }

        if ($this->isIndustryDirector($user)) {
            return $this->assignedIndustryIds($user);
        }

        if (! Schema::hasColumn('circles', 'industry_id')) {
            return [];
        }

        return Circle::query()
            ->whereIn('id', $this->visibleCircleIds($user))
            ->whereNotNull('industry_id')
            ->pluck('industry_id')
            ->unique()
            ->values()
            ->all();
    }

    public function visibleDistrictIds(User $user): array
    {
        if ($this->isGlobal($user)) {
            return Circle::query()->whereNotNull('ded_user_id')->pluck('ded_user_id')->unique()->values()->all();
        }

        $keys = $this->roleKeys($user);
        if (in_array('ded', $keys, true)) {
            return [$user->id];
        }

        return Circle::query()
            ->whereIn('id', $this->visibleCircleIds($user))
            ->whereNotNull('ded_user_id')
            ->pluck('ded_user_id')
            ->unique()
            ->values()
            ->all();
    }

    public function applyUserScope(Builder $query, User $actor): Builder
    {
        if ($this->isGlobal($actor)) {
            return $query;
        }

        $circleIds = $this->visibleCircleIds($actor);

        return $query->where(function (Builder $q) use ($actor, $circleIds): void {
            $q->where('users.id', $actor->id)
                ->orWhereHas('circleMemberships', function (Builder $sub) use ($circleIds): void {
                    $sub->whereIn('circle_members.circle_id', $circleIds)
                        ->where('circle_members.status', 'approved')
                        ->whereNull('circle_members.deleted_at');
                });
        });
    }

    public function applyCircleScope(Builder $query, User $actor, string $column = 'id'): Builder
    {
        if ($this->isGlobal($actor)) {
            return $query;
        }

        return $query->whereIn($column, $this->visibleCircleIds($actor));
    }

    public function applyIndustryScope(Builder $query, User $actor, string $column = 'id'): Builder
    {
        if ($this->isGlobal($actor)) {
            return $query;
        }

        return $query->whereIn($column, $this->visibleIndustryIds($actor));
    }

    public function assertCircleVisible(User $actor, string $circleId): void
    {
        if ($this->isGlobal($actor)) {
            return;
        }

        abort_unless(in_array($circleId, $this->visibleCircleIds($actor), true), 403, 'Out of scope.');
    }

    private function circleIdsForIndustries(array $industryIds): array
    {
        $industryIds = array_values(array_filter(array_map('strval', $industryIds)));

        if ($industryIds === []) {
            return [];
        }

        $query = Circle::query();

        if (Schema::hasColumn('circles', 'industry_id')) {
            $query->whereIn('industry_id', $industryIds);
        } elseif (Schema::hasColumn('circles', 'industry_tags')) {
            $industryNames = Industry::query()
                ->whereIn('id', $industryIds)
                ->pluck('name')
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->values()
                ->all();

            $query->where(function (Builder $tagQuery) use ($industryIds, $industryNames): void {
                foreach ($industryIds as $industryId) {
                    $tagQuery->orWhereJsonContains('industry_tags', $industryId);
                }

                foreach ($industryNames as $industryName) {
                    $tagQuery->orWhereJsonContains('industry_tags', $industryName);
                }
            });
        } else {
            return [];
        }

        return $query->pluck('id')->map(fn ($id) => (string) $id)->unique()->values()->all();
    }

}
