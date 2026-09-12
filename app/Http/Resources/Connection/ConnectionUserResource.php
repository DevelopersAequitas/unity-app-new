<?php

declare(strict_types=1);

namespace App\Http\Resources\Connection;

use App\Models\UserFollow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;

class ConnectionUserResource extends JsonResource
{
    public function toArray($request): array
    {
        $fileId = $this->profile_photo_file_id ?? $this->profile_photo_id ?? null;

        $authUser = auth('sanctum')->user() ?: ($request instanceof Request ? $request->user() : null);

        $isBookmark = false;
        if ($authUser) {
            $bookmarks = $authUser->bookmarks ?? [];
            $isBookmark = in_array((string) $this->id, $bookmarks, true);
        }

        $isFollowing = false;
        if ($this->getAttribute('is_following') !== null) {
            $isFollowing = (bool) $this->getAttribute('is_following');
        } elseif ($authUser && Schema::hasTable('user_follows')) {
            $authUserId = (string) $authUser->id;
            $targetId = (string) $this->id;

            if ($authUserId !== $targetId) {
                $isFollowing = UserFollow::query()
                    ->where('follower_id', $authUserId)
                    ->where('following_id', $targetId)
                    ->where('status', 'accepted')
                    ->exists();
            }
        }

        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'profile_photo_url' => $fileId ? url('/api/v1/files/'.$fileId) : null,
            'company_name' => $this->company_name,
            'city' => optional($this->city)->name
                ?? $this->city_name
                ?? $this->city
                ?? null,
            'membership_status' => $this->membership_status,
            'life_impacted_count' => (int) ($this->life_impacted_count ?? 0),
            'category' => $this->level4Category?->name ?? null,
            'designation' => $this->designation ?? null,
            'is_bookmark' => $isBookmark,
            'is_following' => $isFollowing,
            'is_connected' => true,
        ];
    }
}

