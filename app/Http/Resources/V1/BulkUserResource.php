<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class BulkUserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->displayName(),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'mobile' => $this->phone,
            'profile_image' => $this->profileImageUrl(),
        ];
    }

    private function displayName(): ?string
    {
        $name = $this->display_name
            ?? trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        if ($name === '' && ! empty($this->email)) {
            $name = Str::before($this->email, '@');
        }

        return $name !== '' ? $name : null;
    }

    private function profileImageUrl(): ?string
    {
        $fileId = $this->profile_photo_file_id
            ?? $this->profile_photo_id
            ?? null;

        return $fileId ? url("/api/v1/files/{$fileId}") : null;
    }
}
