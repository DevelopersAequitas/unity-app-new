<?php

namespace App\Support\ActivityHistory;

use App\Models\User;
use Illuminate\Support\Collection;

class OtherUserNameResolver
{
    /**
     * @param  Collection<string|null>  $ids
     * @return array<string, string|null>
     */
    public function mapNames(Collection $ids): array
    {
        $uniqueIds = $ids
            ->filter()
            ->unique()
            ->values();

        if ($uniqueIds->isEmpty()) {
            return [];
        }

        return User::query()
            ->select(['id', 'display_name', 'first_name', 'last_name'])
            ->whereIn('id', $uniqueIds)
            ->get()
            ->mapWithKeys(function (User $user) {
                return [$user->id => $this->buildName($user)];
            })
            ->all();
    }

    private function buildName(User $user): ?string
    {
        $displayName = trim((string) $user->display_name);
        if ($displayName !== '') {
            return $displayName;
        }

        $fullName = trim(trim((string) $user->first_name).' '.trim((string) $user->last_name));
        if ($fullName !== '') {
            return $fullName;
        }

        $firstName = trim((string) $user->first_name);
        if ($firstName !== '') {
            return $firstName;
        }

        return null;
    }
}
