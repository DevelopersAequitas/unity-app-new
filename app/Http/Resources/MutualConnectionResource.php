<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MutualConnectionResource extends JsonResource
{
    /**
     * Transform a mutual connection user into the API response shape.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => (string) $this->id,
            'name' => $this->displayName(),
            'username' => $this->username ?? '',
            'profile_photo' => $this->profilePhotoUrl(),
            'headline' => $this->designation ?? '',
            'company' => $this->company_name ?? '',
            'location' => $this->locationName(),
            'mutual_count' => 0,
        ];
    }

    /**
     * Resolve the display name used in mutual connection payloads.
     */
    private function displayName(): string
    {
        $name = trim((string) ($this->display_name ?? ''));

        if ($name !== '') {
            return $name;
        }

        return trim((string) ($this->first_name ?? '') . ' ' . (string) ($this->last_name ?? ''));
    }

    /**
     * Resolve the existing file-backed profile photo URL when available.
     */
    private function profilePhotoUrl(): string
    {
        if ($this->profile_photo_file_id) {
            return url('/api/v1/files/' . $this->profile_photo_file_id);
        }

        return (string) ($this->profile_photo_url ?? '');
    }

    /**
     * Resolve the best available location string.
     */
    private function locationName(): string
    {
        if ($this->relationLoaded('city') && $this->city) {
            return (string) $this->city->name;
        }

        if (is_array($this->getAttribute('city'))) {
            return (string) ($this->getAttribute('city')['name'] ?? '');
        }

        return (string) ($this->getAttribute('city') ?? '');
    }
}
