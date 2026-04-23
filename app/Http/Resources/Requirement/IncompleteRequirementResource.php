<?php

namespace App\Http\Resources\Requirement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncompleteRequirementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->whenLoaded('user');
        $firstName = (string) data_get($user, 'first_name', '');
        $lastName = (string) data_get($user, 'last_name', '');
        $fullName = trim($firstName . ' ' . $lastName);

        return [
            'id' => (string) $this->id,
            'subject' => $this->subject,
            'description' => $this->description,
            'media' => $this->media ?? [],
            'region_filter' => $this->region_filter ?? [],
            'category_filter' => $this->category_filter ?? [],
            'status' => $this->status,
            'submitted_at' => optional($this->created_at)?->toISOString(),
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
            'user' => [
                'id' => (string) data_get($user, 'id', ''),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'display_name' => data_get($user, 'display_name'),
                'full_name' => data_get($user, 'display_name') ?: $fullName,
                'company_name' => data_get($user, 'company_name'),
                'designation' => data_get($user, 'designation'),
                'profile_photo_file_id' => data_get($user, 'profile_photo_file_id'),
                'profile_photo_url' => $this->resolveProfilePhotoUrl($user),
                'membership_status' => data_get($user, 'membership_status'),
            ],
        ];
    }

    private function resolveProfilePhotoUrl(mixed $user): ?string
    {
        if (! $user) {
            return null;
        }

        $photoFileId = data_get($user, 'profile_photo_file_id');

        if ($photoFileId) {
            return url('/api/v1/files/' . $photoFileId);
        }

        return data_get($user, 'profile_photo_url') ?: $user->getRawOriginal('profile_photo_url');
    }
}
