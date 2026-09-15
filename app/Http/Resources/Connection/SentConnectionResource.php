<?php

namespace App\Http\Resources\Connection;

use App\Models\UserFollow;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;

class SentConnectionResource extends JsonResource
{
    public function toArray($request): array
    {
        $addressee = $this->addressee;
        $authUser = auth('sanctum')->user() ?: ($request ? $request->user() : null);

        $isFollowing = false;
        if ($addressee?->getAttribute('is_following') !== null) {
            $isFollowing = (bool) $addressee->getAttribute('is_following');
        } elseif ($authUser && $addressee && Schema::hasTable('user_follows')) {
            $authUserId = (string) $authUser->id;
            $targetId = (string) $addressee->id;
            if ($authUserId !== $targetId) {
                $isFollowing = UserFollow::query()
                    ->where('follower_id', $authUserId)
                    ->where('following_id', $targetId)
                    ->whereIn('status', ['accepted', 'pending'])
                    ->exists();
            }
        }

        $isPro = false;
        if ($addressee) {
            $rawVerified = $addressee->is_verified ?? null;
            if ($rawVerified !== null && (bool) $rawVerified) {
                $isPro = true;
            } elseif (method_exists($addressee, 'isPaidMember')) {
                $isPro = (bool) $addressee->isPaidMember();
            } else {
                $status = strtolower(trim((string) ($addressee->effective_membership_status ?? $addressee->membership_status ?? '')));
                $isPro = $status !== '' && ! in_array($status, ['free_peer', 'free_trial_peer', 'visitor', 'suspended', 'free peer', 'free'], true);
            }
        }

        return [
            'requested_at' => $this->created_at,
            'is_approved' => (bool) $this->is_approved,
            'addressee' => $addressee ? [
                'id' => $addressee->id,
                'display_name' => $addressee->display_name,
                'first_name' => $addressee->first_name,
                'last_name' => $addressee->last_name,
                'profile_photo_url' => $this->buildProfilePhotoUrl($addressee),
                'company_name' => $addressee->company_name,
                'city' => $this->resolveCity($addressee),
                'category' => $addressee->level4Category?->name ?? null,
                'designation' => $addressee->designation ?? null,
                'life_impacted_count' => (int) ($addressee->life_impacted_count ?? 0),
                'is_following' => $isFollowing,
                'is_pro' => $isPro,
                'is_connected' => (bool) $this->is_approved,
                'connection_status' => $this->is_approved ? 'connected' : 'pending_sent',
            ] : null,
        ];
    }

    private function buildProfilePhotoUrl($user): ?string
    {
        $fileId = $user->profile_photo_file_id
            ?? $user->profile_photo_id
            ?? null;

        if (! $fileId) {
            return null;
        }

        return 'https://peersunity.com/api/v1/files/'.$fileId;
    }

    private function resolveCity($user): ?string
    {
        $cityRelation = $user->relationLoaded('city')
            ? $user->getRelationValue('city')
            : null;

        if ($cityRelation) {
            return $cityRelation->name;
        }

        return $user->city_name
            ?? $user->city
            ?? null;
    }
}
