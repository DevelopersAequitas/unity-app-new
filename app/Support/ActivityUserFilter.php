<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserTag;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Schema;

class ActivityUserFilter
{
    /**
     * Apply active status and non-team-member exclusion to a User query (Eloquent or Query Builder).
     */
    public static function applyToUserQuery(
        EloquentBuilder|QueryBuilder $query,
        string $userColumn = 'users.id',
        string $tablePrefix = 'users'
    ): void {
        if (Schema::hasColumn($tablePrefix, 'status')) {
            $query->where("{$tablePrefix}.status", 'active');
        }

        if (Schema::hasColumn($tablePrefix, 'deleted_at')) {
            $query->whereNull("{$tablePrefix}.deleted_at");
        }

        if (Schema::hasTable('user_tag_assignments') && Schema::hasTable('user_tags')) {
            $query->whereNotExists(function ($sub) use ($userColumn): void {
                $sub->selectRaw('1')
                    ->from('user_tag_assignments as uta_filter')
                    ->join('user_tags as ut_filter', 'ut_filter.id', '=', 'uta_filter.tag_id')
                    ->whereColumn('uta_filter.user_id', $userColumn)
                    ->where('ut_filter.slug', UserTag::SLUG_TEAM_MEMBER)
                    ->where('ut_filter.is_active', true);
            });
        }
    }

    /**
     * Apply active status and non-team-member exclusion to an activity query based on actor column.
     * If $actorAlias is provided (e.g. 'actor'), it checks columns on that joined table alias directly.
     * Otherwise, it checks the users table via subquery.
     */
    public static function applyToActivityQuery(
        EloquentBuilder|QueryBuilder $query,
        string $actorColumn,
        ?string $actorAlias = null
    ): void {
        if ($actorAlias !== null && $actorAlias !== '') {
            if (Schema::hasColumn('users', 'status')) {
                $query->where("{$actorAlias}.status", 'active');
            }

            if (Schema::hasColumn('users', 'deleted_at')) {
                $query->whereNull("{$actorAlias}.deleted_at");
            }
        } else {
            $query->whereExists(function ($sub) use ($actorColumn): void {
                $sub->selectRaw('1')
                    ->from('users as u_act_filter')
                    ->whereColumn('u_act_filter.id', $actorColumn);

                if (Schema::hasColumn('users', 'status')) {
                    $sub->where('u_act_filter.status', 'active');
                }

                if (Schema::hasColumn('users', 'deleted_at')) {
                    $sub->whereNull('u_act_filter.deleted_at');
                }
            });
        }

        if (Schema::hasTable('user_tag_assignments') && Schema::hasTable('user_tags')) {
            $query->whereNotExists(function ($sub) use ($actorColumn): void {
                $sub->selectRaw('1')
                    ->from('user_tag_assignments as uta_filter')
                    ->join('user_tags as ut_filter', 'ut_filter.id', '=', 'uta_filter.tag_id')
                    ->whereColumn('uta_filter.user_id', $actorColumn)
                    ->where('ut_filter.slug', UserTag::SLUG_TEAM_MEMBER)
                    ->where('ut_filter.is_active', true);
            });
        }
    }

    /**
     * Check if a specific user instance is active and not a team member.
     */
    public static function isEligibleUser(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->trashed()) {
            return false;
        }

        if (isset($user->status) && strtolower((string) $user->status) !== 'active') {
            return false;
        }

        if (Schema::hasTable('user_tag_assignments') && Schema::hasTable('user_tags')) {
            if ($user->hasTag(UserTag::SLUG_TEAM_MEMBER)) {
                return false;
            }
        }

        return true;
    }
}
