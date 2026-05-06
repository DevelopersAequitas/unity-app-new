<?php

namespace App\Services\Admin;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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

    public function visibleCircleIds(User $user): array
    {
        if ($this->isGlobal($user)) {
            return Circle::query()->pluck('id')->all();
        }

        $direct = Circle::query()
            ->where(fn ($q) => $q->where('founder_user_id', $user->id)
                ->orWhere('director_user_id', $user->id)
                ->orWhere('industry_director_user_id', $user->id)
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
}
