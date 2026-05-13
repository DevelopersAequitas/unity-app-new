<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class FollowerResource extends JsonResource
{
    public function toArray($request): array
    {
        $follower = $this->whenLoaded('follower');

        return [
            'follow_id' => $this->id,
            'status' => $this->status,
            'followed_at' => optional($this->accepted_at ?? $this->created_at)?->format('Y-m-d H:i:s'),
            'user' => $this->formatFollower($follower),
        ];
    }

    private function formatFollower(mixed $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'display_name' => $this->displayName($user),
            'email' => $user->email,
            'phone' => $user->phone,
            'company_name' => $user->company_name,
            'designation' => $user->designation,
            'city' => $user->city,
            'profile_photo_url' => $this->profilePhotoUrl($user),
            'public_profile_slug' => $user->public_profile_slug,
        ];
    }

    private function displayName(mixed $user): ?string
    {
        $displayName = $user->display_name
            ?? trim(($user->first_name ?? '').' '.($user->last_name ?? ''));

        if ($displayName === '' && ! empty($user->email)) {
            $displayName = Str::before($user->email, '@');
        }

        return $displayName !== '' ? $displayName : null;
    }

    private function profilePhotoUrl(mixed $user): ?string
    {
        if (! empty($user->profile_photo_url)) {
            return $user->profile_photo_url;
        }

        $fileId = $user->profile_photo_file_id
            ?? $user->profile_photo_id
            ?? $user->profile_image_id
            ?? null;

        return $fileId ? url('/api/v1/files/'.$fileId) : null;
    }
}
