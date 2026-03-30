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

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'name' => $fullName !== '' ? $fullName : null,
            'email' => $this->email,
            'phone' => $this->phone,
            'company_name' => $this->company_name,
            'city_name' => $this->city?->name ?? $this->city,
            'country_name' => $this->country,
            'membership_status' => $membershipStatus,
            'membership_label' => match ($membershipStatus) {
                User::STATUS_FREE_TRIAL => 'Free Trial Peer',
                User::STATUS_FREE => 'Free Peer',
                default => $membershipStatus,
            },
            'coins' => (int) ($this->coins_balance ?? 0),
            'last_login_at' => $this->last_login_at,
            'status' => $this->status,
            'profile_image_url' => $this->profile_photo_url,
            'created_at' => $this->created_at,
        ];
    }
}
