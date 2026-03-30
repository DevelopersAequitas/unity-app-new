<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicMemberResource extends JsonResource
{
    public function toArray($request): array
    {
        $fullName = trim((string) ($this->display_name ?: implode(' ', array_filter([$this->first_name, $this->last_name]))));
        $membershipStatus = $this->effective_membership_status ?? $this->membership_status;
        $resolvedName = $fullName !== '' ? $fullName : $this->email;
        $cityRelation = $this->relationLoaded('city') ? $this->getRelation('city') : null;
        $cityName = $cityRelation?->name ?? $this->getAttribute('city');
        $countryName = data_get($cityRelation, 'country_name') ?? data_get($cityRelation, 'country');

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'display_name' => $this->display_name,
            'name' => $resolvedName,
            'email' => $this->email,
            'phone' => $this->phone,
            'designation' => $this->designation,
            'company_name' => $this->company_name,
            'profile_photo_url' => $this->profile_photo_url,
            'profile_image_url' => $this->profile_photo_url,
            'city_id' => $this->city_id,
            'city_name' => $cityName,
            'country_name' => $countryName,
            'membership_status' => $membershipStatus,
            'membership_label' => match ($membershipStatus) {
                User::STATUS_FREE_TRIAL => 'Free Trial Peer',
                User::STATUS_FREE => 'Free Peer',
                default => $membershipStatus,
            },
            'membership_expiry' => $this->membership_expiry,
            'membership_starts_at' => $this->membership_starts_at,
            'membership_ends_at' => $this->membership_ends_at,
            'last_payment_at' => $this->last_payment_at,
            'public_profile_slug' => $this->public_profile_slug,
            'coins' => (int) ($this->coins_balance ?? 0),
            'coins_balance' => (int) ($this->coins_balance ?? 0),
            'coin_medal_rank' => $this->coin_medal_rank,
            'coin_milestone_title' => $this->coin_milestone_title,
            'contribution_award_name' => $this->contribution_award_name,
            'last_login_at' => $this->last_login_at,
            'status' => $this->status,
            'active_circle_id' => $this->active_circle_id,
            'active_circle_addon_name' => $this->active_circle_addon_name,
            'active_circle_name' => $this->activeCircle?->name,
            'circle_joined_at' => $this->circle_joined_at,
            'circle_expires_at' => $this->circle_expires_at,
            'created_at' => $this->created_at,
        ];
    }
}
