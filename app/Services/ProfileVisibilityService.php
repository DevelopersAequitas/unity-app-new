<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProfileVisibilityService
{
    public const EVERYONE = 'everyone';

    public const CONNECTED_ONLY = 'connected_only';

    public const CIRCLE_ONLY = 'circle_only';

    public const HIDDEN = 'hidden';

    public function canView(?User $viewer, User $profileOwner): bool
    {
        if (! $viewer) {
            return false;
        }

        if ((string) $viewer->id === (string) $profileOwner->id || $this->isAdmin($viewer)) {
            return true;
        }

        return match ($this->visibilityFor($profileOwner)) {
            self::CONNECTED_ONLY => $this->areConnected($viewer, $profileOwner),
            self::CIRCLE_ONLY => $this->shareCircle($viewer, $profileOwner),
            self::HIDDEN => false,
            default => true,
        };
    }

    public function applyVisibleTo(Builder $query, ?User $viewer, string $userTable = 'users'): Builder
    {
        if (! $viewer || ! Schema::hasColumn('users', 'profile_visibility')) {
            return $query;
        }

        if ($this->isAdmin($viewer)) {
            return $query;
        }

        $viewerId = (string) $viewer->id;

        return $query->where(function (Builder $visibilityQuery) use ($viewerId, $userTable): void {
            $visibilityQuery
                ->where("{$userTable}.id", $viewerId)
                ->orWhereNull("{$userTable}.profile_visibility")
                ->orWhere("{$userTable}.profile_visibility", self::EVERYONE)
                ->orWhere(function (Builder $connectedQuery) use ($viewerId, $userTable): void {
                    $connectedQuery
                        ->where("{$userTable}.profile_visibility", self::CONNECTED_ONLY)
                        ->whereExists($this->acceptedConnectionExistsQuery($viewerId, $userTable));
                })
                ->orWhere(function (Builder $circleQuery) use ($viewerId, $userTable): void {
                    $circleQuery
                        ->where("{$userTable}.profile_visibility", self::CIRCLE_ONLY)
                        ->whereExists($this->sharedCircleExistsQuery($viewerId, $userTable));
                });
        });
    }

    public function visibilityFor(User $user): string
    {
        if (! Schema::hasColumn($user->getTable(), 'profile_visibility')) {
            return self::EVERYONE;
        }

        $visibility = (string) ($user->profile_visibility ?: self::EVERYONE);

        return in_array($visibility, [self::EVERYONE, self::CONNECTED_ONLY, self::CIRCLE_ONLY, self::HIDDEN], true)
            ? $visibility
            : self::EVERYONE;
    }

    private function areConnected(User $viewer, User $profileOwner): bool
    {
        if (! Schema::hasTable('connections')) {
            return false;
        }

        return DB::table('connections')
            ->where('is_approved', true)
            ->where(function ($query) use ($viewer, $profileOwner): void {
                $query->where(function ($pair) use ($viewer, $profileOwner): void {
                    $pair->where('requester_id', $viewer->id)->where('addressee_id', $profileOwner->id);
                })->orWhere(function ($pair) use ($viewer, $profileOwner): void {
                    $pair->where('requester_id', $profileOwner->id)->where('addressee_id', $viewer->id);
                });
            })
            ->exists();
    }

    private function shareCircle(User $viewer, User $profileOwner): bool
    {
        if (! Schema::hasTable('circle_members')) {
            return false;
        }

        return DB::table('circle_members as viewer_cm')
            ->join('circle_members as owner_cm', 'owner_cm.circle_id', '=', 'viewer_cm.circle_id')
            ->where('viewer_cm.user_id', $viewer->id)
            ->where('owner_cm.user_id', $profileOwner->id)
            ->where('viewer_cm.status', 'approved')
            ->where('owner_cm.status', 'approved')
            ->whereNull('viewer_cm.deleted_at')
            ->whereNull('owner_cm.deleted_at')
            ->whereNull('viewer_cm.left_at')
            ->whereNull('owner_cm.left_at')
            ->exists();
    }

    private function isAdmin(User $viewer): bool
    {
        return Schema::hasTable('roles')
            && Schema::hasTable('admin_user_roles')
            && $viewer->roles()->whereIn('key', ['global_admin', 'ded', 'industry_director', 'circle_leader'])->exists();
    }

    private function acceptedConnectionExistsQuery(string $viewerId, string $userTable): \Closure
    {
        return function ($query) use ($viewerId, $userTable): void {
            $query->selectRaw('1')
                ->from('connections')
                ->where('is_approved', true)
                ->where(function ($pairQuery) use ($viewerId, $userTable): void {
                    $pairQuery->where(function ($pair) use ($viewerId, $userTable): void {
                        $pair->where('requester_id', $viewerId)->whereColumn('addressee_id', "{$userTable}.id");
                    })->orWhere(function ($pair) use ($viewerId, $userTable): void {
                        $pair->whereColumn('requester_id', "{$userTable}.id")->where('addressee_id', $viewerId);
                    });
                });
        };
    }

    private function sharedCircleExistsQuery(string $viewerId, string $userTable): \Closure
    {
        return function ($query) use ($viewerId, $userTable): void {
            $query->selectRaw('1')
                ->from('circle_members as viewer_cm')
                ->join('circle_members as owner_cm', 'owner_cm.circle_id', '=', 'viewer_cm.circle_id')
                ->where('viewer_cm.user_id', $viewerId)
                ->whereColumn('owner_cm.user_id', "{$userTable}.id")
                ->where('viewer_cm.status', 'approved')
                ->where('owner_cm.status', 'approved')
                ->whereNull('viewer_cm.deleted_at')
                ->whereNull('owner_cm.deleted_at')
                ->whereNull('viewer_cm.left_at')
                ->whereNull('owner_cm.left_at');
        };
    }
}
