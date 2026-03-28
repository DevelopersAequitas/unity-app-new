<?php

namespace App\Services\Users;

use App\Models\City;
use App\Models\User;
use Illuminate\Support\Str;

class PublicProfileSlugService
{
    public function normalize(?string $value): ?string
    {
        $slug = Str::slug((string) $value);

        return $slug !== '' ? $slug : null;
    }

    public function ensureForUser(User $user): string
    {
        if (! empty($user->public_profile_slug)) {
            return (string) $user->public_profile_slug;
        }

        return $this->generateUniqueForUser($user);
    }

    public function generateUniqueForUser(User $user): string
    {
        $parts = $this->buildReadableParts($user);
        $base = implode('-', $parts);

        if ($base === '') {
            $base = 'member';
        }

        $candidate = $base;

        if (count($parts) < 2) {
            $candidate .= '-' . $this->suffix();
        }

        while ($this->slugExistsForAnotherUser($candidate, $user)) {
            $candidate = $base . '-' . $this->suffix();
        }

        return $candidate;
    }

    private function buildReadableParts(User $user): array
    {
        $cityName = $this->resolveCityName($user);

        $parts = [
            $this->normalize($user->display_name),
            $this->normalize($user->company_name),
            $this->normalize($cityName),
        ];

        return array_values(array_filter($parts, fn (?string $value) => ! empty($value)));
    }

    private function resolveCityName(User $user): ?string
    {
        if (! empty($user->city)) {
            return (string) $user->city;
        }

        $cityRelation = $user->relationLoaded('city') ? $user->city : null;
        if (! empty($cityRelation?->name)) {
            return (string) $cityRelation->name;
        }

        if (! empty($user->city_id)) {
            return City::query()->whereKey($user->city_id)->value('name');
        }

        return null;
    }

    private function slugExistsForAnotherUser(string $slug, User $user): bool
    {
        return User::query()
            ->where('public_profile_slug', $slug)
            ->where('id', '!=', $user->id)
            ->exists();
    }

    private function suffix(): string
    {
        return strtolower(bin2hex(random_bytes(4)));
    }
}
