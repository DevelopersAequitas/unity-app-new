<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GeoNearbyPeerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'company_name' => $this->company_name,
            'designation' => $this->designation,
            'business_type' => $this->business_type,
            'profile_photo_url' => $this->resolveProfilePhotoUrl(),
            'city' => $this->resolveCity(),
            'distance_km' => round((float) $this->distance_km, 2),
            'last_seen_at' => $this->geo_last_seen_at,
            'connection_status' => $this->connection_status,
            'can_send_connection_request' => (bool) ($this->can_send_connection_request ?? true),
        ];
    }

    private function resolveProfilePhotoUrl(): ?string
    {
        if ($this->profile_photo_file_id) {
            return url('/api/v1/files/' . $this->profile_photo_file_id);
        }

        return $this->getRawOriginal('profile_photo_url');
    }

    private function resolveCity(): ?array
    {
        $city = $this->relationLoaded('cityRelation')
            ? $this->getRelationValue('cityRelation')
            : null;

        if ($city) {
            return [
                'id' => $city->id,
                'name' => $city->name,
            ];
        }

        if (! empty($this->city)) {
            return [
                'id' => null,
                'name' => $this->city,
            ];
        }

        return null;
    }
}
