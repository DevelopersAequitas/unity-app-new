<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityReferralResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Compatibility fields
            'title' => $this->referral_of,
            'description' => $this->remarks,
            'status' => $this->status ?? null,
            'source_module' => $this->source_module ?? 'referral',
            
            // Raw database fields
            'referral_type' => $this->referral_type,
            'referral_date' => $this->referral_date,
            'referral_of' => $this->referral_of,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'hot_value' => is_numeric($this->hot_value) ? (int) $this->hot_value : $this->hot_value,
            'remarks' => $this->remarks,
            
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'given_by_user' => $this->formatSafeUser($this->relationLoaded('givenByUser') ? $this->givenByUser : null),
            'received_by_user' => $this->formatSafeUser($this->relationLoaded('receivedByUser') ? $this->receivedByUser : null),
        ];
    }

    private function formatSafeUser(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $resolvedCity = $user->city_of_residence ?: (is_string($user->city) ? $user->city : data_get($user, 'city.name'));

        return [
            'id' => (string) $user->id,
            'display_name' => $user->display_name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'company_name' => $user->company_name,
            'designation' => $user->designation,
            'city' => $resolvedCity,
            'membership_status' => $user->membership_status,
            'profile_photo_url' => $user->profile_photo_url,
            'public_profile_slug' => $user->public_profile_slug,
        ];
    }
}
