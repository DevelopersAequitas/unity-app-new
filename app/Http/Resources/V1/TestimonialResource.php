<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class TestimonialResource extends JsonResource
{
    public function toArray($request): array
    {
        $testimonial = $this->resource;

        return [
            'id' => $testimonial->id,
            'given_by_user_id' => $testimonial->from_user_id,
            'given_to_user_id' => $testimonial->to_user_id,
            'message' => $testimonial->content,
            'testimonial_text' => $testimonial->content,
            'rating' => $testimonial->rating,
            'media' => $this->resolveMedia($testimonial->media),
            'created_at' => $testimonial->created_at,
            'updated_at' => $testimonial->updated_at,
            'given_by' => $this->formatUser($testimonial->fromUser),
            'given_to' => $this->formatUser($testimonial->toUser),
        ];
    }

    protected function resolveMedia(?array $media): array
    {
        if (empty($media)) {
            return [];
        }

        return collect($media)->map(function ($item) {
            $id   = $item['id']   ?? null;
            $type = $item['type'] ?? 'image';

            return [
                'id'   => $id,
                'type' => $type,
                'url'  => $id ? url('/api/v1/files/' . $id) : null,
            ];
        })->all();
    }

    protected function formatUser($user): ?array
    {
        if (!$user) {
            return null;
        }

        $profilePhotoId = $user->profile_photo_file_id ?? $user->profile_photo_id;
        $profilePhotoUrl = $profilePhotoId
            ? url('/api/v1/files/' . $profilePhotoId)
            : ($user->profile_photo_url ?? null);

        return [
            'id' => $user->id,
            'display_name' => $user->display_name ?? $user->first_name . ' ' . $user->last_name,
            'company_name' => $user->company_name,
            'designation' => $user->designation,
            'profile_photo_url' => $profilePhotoUrl,
        ];
    }
}
