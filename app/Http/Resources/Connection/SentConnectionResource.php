<?php

namespace App\Http\Resources\Connection;

use Illuminate\Http\Resources\Json\JsonResource;

class SentConnectionResource extends JsonResource
{
    public function toArray($request): array
    {
        $addressee = $this->addressee;

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
