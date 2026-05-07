<?php

namespace App\Http\Resources;

use App\Models\CircleMember;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class MyLeadershipCircleResource extends JsonResource
{
    public function toArray($request): array
    {
        $circle = $this->relationLoaded('circle') ? $this->circle : null;
        $role = $this->relationLoaded('roleModel') ? $this->roleModel : null;
        $roleSlug = $this->normalizeRoleSlug(
            $role?->slug
            ?? $role?->key
            ?? $role?->name
            ?? $role?->display_name
            ?? $this->role
        );
        $roleName = $role?->name ?? $this->roleNameFromSlug($roleSlug);
        $displayName = $role?->display_name ?? $roleName;
        $joinedAt = $this->joined_at ?? $this->created_at;

        return [
            'circle_member_id' => $this->id,
            'circle' => $circle ? [
                'id' => $circle->id,
                'name' => $circle->name,
                'slug' => $circle->slug,
                'description' => $circle->description,
                'status' => $circle->status,
                'type' => $circle->type,
                'city' => $circle->city_display ?? $circle->city,
                'country' => $circle->country,
                'cover_photo_url' => $circle->cover_image_url,
            ] : null,
            'role' => [
                'id' => $role?->id ?? $this->role_id,
                'name' => $roleName,
                'slug' => $roleSlug,
                'display_name' => $displayName,
            ],
            'membership_status' => $this->status,
            'joined_at' => $joinedAt ? $joinedAt->toDateString() : null,
            'can_access_leadership_chat' => in_array($roleSlug, CircleMember::LEADERSHIP_ROLE_OPTIONS, true),
        ];
    }

    private function normalizeRoleSlug(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return Str::of($value)
            ->lower()
            ->replace(['-', ' '], '_')
            ->replaceMatches('/_+/', '_')
            ->trim('_')
            ->toString();
    }

    private function roleNameFromSlug(?string $slug): ?string
    {
        if ($slug === null) {
            return null;
        }

        return Str::of($slug)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }
}
