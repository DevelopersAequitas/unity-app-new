<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VisitorLeadResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'referral_link_id' => $this->referral_link_id,
            'converted_user_id' => $this->converted_user_id,
            'converted_at' => $this->converted_at,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'referral_link' => $this->whenLoaded('referralLink', function () {
                return [
                    'id' => $this->referralLink->id,
                    'token' => $this->referralLink->token,
                    'status' => $this->referralLink->status,
                    'expires_at' => $this->referralLink->expires_at,
                ];
            }),
            'converted_user' => $this->whenLoaded('convertedUser', function () {
                return [
                    'id' => $this->convertedUser->id,
                    'display_name' => $this->convertedUser->display_name,
                    'first_name' => $this->convertedUser->first_name,
                    'last_name' => $this->convertedUser->last_name,
                    'profile_photo_url' => $this->convertedUser->profile_photo_url,
                    'is_online' => (bool) ($this->convertedUser->is_online ?? false),
                ];
            }),
        ];
    }
}
