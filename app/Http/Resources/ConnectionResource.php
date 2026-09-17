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

        return [
            'id' => $this->id,
            'is_approved' => (bool) $this->is_approved,
            'approved_at' => $this->approved_at,
            'created_at' => $this->created_at,
            'requester' => $this->formatUser($this->requester, $authUser, 'requester'),
            'addressee' => $this->formatUser($this->addressee, $authUser, 'addressee'),
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

        return [
            'id' => $user->id,
            'display_name' => $user->display_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'profile_photo_url' => $user->profile_photo_url,
            'company_name' => $user->company_name,
            'city' => $this->resolveCity($user),
            'category' => $user->level4Category?->name ?? null,
            'designation' => $user->designation ?? null,
            'life_impacted_count' => (int) ($user->life_impacted_count ?? 0),
            'is_following' => $isFollowing,
            'is_pro' => $isPro,
            'is_connected' => (bool) $this->is_approved,
            'connection_status' => $status,
        ];
    }

    private function resolveCity($user): ?string
    {
        if (! $user) {
            return null;
        }

        $cityRelation = $user->relationLoaded('city')
            ? $user->getRelationValue('city')
            : null;

        if ($cityRelation) {
            return $cityRelation->name;
        }

        return $user->city_name ?? $user->city ?? null;
    }
}
