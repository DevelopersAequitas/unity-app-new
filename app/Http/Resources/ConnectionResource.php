<?php

namespace App\Http\Resources;

use App\Models\UserFollow;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;

class ConnectionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        $authUser = auth('sanctum')->user() ?: ($request ? $request->user() : null);

        $requesterFormatted = $this->formatUser($this->requester, $authUser, 'requester');
        $addresseeFormatted = $this->formatUser($this->addressee, $authUser, 'addressee');

        $counterpart = ($authUser && (string) $this->requester_id === (string) $authUser->id)
            ? $addresseeFormatted
            : $requesterFormatted;

        return [
            'id' => (string) $this->id,
            'is_approved' => (bool) $this->is_approved,
            'approved_at' => $this->approved_at,
            'created_at' => $this->created_at,
            'requested_at' => $this->created_at,
            'user' => $counterpart,
            'peer' => $counterpart,
            'member' => $counterpart,
            'from_user' => $requesterFormatted,
            'to_user' => $addresseeFormatted,
            'requester' => $requesterFormatted,
            'addressee' => $addresseeFormatted,
        ];
    }

    private function formatUser($user, $authUser, string $role): ?array
    {
        if (! $user) {
            return null;
        }

        $isFollowing = false;
        if ($user->getAttribute('is_following') !== null) {
            $isFollowing = (bool) $user->getAttribute('is_following');
        } elseif ($authUser && Schema::hasTable('user_follows')) {
            $authUserId = (string) $authUser->id;
            $targetId = (string) $user->id;
            if ($authUserId !== $targetId) {
                $isFollowing = UserFollow::query()
                    ->where('follower_id', $authUserId)
                    ->where('following_id', $targetId)
                    ->whereIn('status', ['accepted', 'pending'])
                    ->exists();
            }
        }

        $isPro = false;
        $rawVerified = $user->is_verified ?? null;
        if ($rawVerified !== null && (bool) $rawVerified) {
            $isPro = true;
        } elseif (method_exists($user, 'isPaidMember')) {
            $isPro = (bool) $user->isPaidMember();
        } else {
            $status = strtolower(trim((string) ($user->effective_membership_status ?? $user->membership_status ?? '')));
            $isPro = $status !== '' && ! in_array($status, ['free_peer', 'free_trial_peer', 'visitor', 'suspended', 'free peer', 'free'], true);
        }

        $status = 'none';
        if ($this->is_approved) {
            $status = 'connected';
        } else {
            $status = $role === 'requester' ? 'pending_received' : 'pending_sent';
        }

        $companyName = $this->resolveCompanyName($user);
        $city = $this->resolveCity($user);
        $level4Category = $this->resolveLevel4Category($user);
        $profilePhotoUrl = $this->buildProfilePhotoUrl($user);

        return [
            'id' => (string) $user->id,
            'display_name' => $user->display_name,
            'name' => $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'profile_photo_url' => $profilePhotoUrl,
            'company_name' => $companyName,
            'company' => $companyName,
            'business_name' => $companyName,
            'city' => $city,
            'category' => $level4Category,
            'level4_category' => $level4Category,
            'level_4_category' => $level4Category,
            'business_sub_category' => $level4Category,
            'designation' => $user->designation ?? null,
            'life_impacted_count' => (int) ($user->life_impacted_count ?? 0),
            'is_following' => $isFollowing,
            'is_pro' => $isPro,
            'is_connected' => (bool) $this->is_approved,
            'connection_status' => $status,
        ];
    }

    private function resolveCompanyName($user): ?string
    {
        return filled($user->company_name ?? null)
            ? (string) $user->company_name
            : (filled($user->business_name ?? null) ? (string) $user->business_name : null);
    }

    private function resolveCity($user): ?string
    {
        if ($user->relationLoaded('city') && $user->city) {
            return is_object($user->city) ? ($user->city->name ?? null) : (is_array($user->city) ? ($user->city['name'] ?? null) : (string) $user->city);
        }

        return filled($user->city ?? null)
            ? (string) (is_array($user->city) ? ($user->city['name'] ?? null) : $user->city)
            : (filled($user->city_of_residence ?? null) ? (string) $user->city_of_residence : null);
    }

    private function resolveLevel4Category($user): ?string
    {
        if ($user->relationLoaded('level4Category') && $user->level4Category) {
            return $user->level4Category->name ?? null;
        }

        if (filled($user->business_sub_category ?? null)) {
            return trim((string) $user->business_sub_category);
        }

        return $user->category ?? null;
    }

    private function buildProfilePhotoUrl($user): ?string
    {
        $fileId = $user->profile_photo_file_id
            ?? $user->profile_photo_id
            ?? null;

        if ($fileId) {
            return url('/api/v1/files/'.$fileId);
        }

        return $user->profile_photo_url ?? null;
    }
}
