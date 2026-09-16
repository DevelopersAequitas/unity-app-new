<?php

namespace App\Http\Resources;

use App\Support\ActivityHistory\OtherUserDetailsResolver;
use App\Support\ActivityHistory\OtherUserProfilePhotoUrlResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TableRowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $attributes = $this->extractAttributes();

        if (isset($attributes['media'])) {
            $attributes['media'] = $this->resolveMedia($attributes['media']);
        }

        if ($this->hasOtherUserContext($attributes)) {
            $detailsResolver = app(OtherUserDetailsResolver::class);
            $otherUser = $detailsResolver->resolve($request->user(), $this->resource);

            if ($otherUser) {
                if (empty($attributes['other_user_name'])) {
                    $attributes['other_user_name'] = $otherUser['name'];
                }
                $attributes['other_user_profile_photo_url'] = $otherUser['profile_photo_url'];
                $attributes['other_user_designation'] = $otherUser['designation'];
                $attributes['other_user_company_name'] = $otherUser['company_name'];
                $attributes['other_user_city'] = $otherUser['city'];
                $attributes['other_user_level4_category'] = $otherUser['level4_category'];
                $attributes['other_user_life_impacted_count'] = $otherUser['life_impacted_count'];
                $attributes['other_user'] = $otherUser;
            } else {
                $photoResolver = app(OtherUserProfilePhotoUrlResolver::class);
                $attributes['other_user_profile_photo_url'] = $photoResolver->resolve($request->user(), $this->resource);
            }
        }

        return $attributes;
    }

    private function resolveMedia(mixed $media): mixed
    {
        if (is_string($media)) {
            $decoded = json_decode($media, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $media = $decoded;
            }
        }

        if (! is_array($media)) {
            return $media;
        }

        return collect($media)->map(function ($item) {
            if (! is_array($item)) {
                return $item;
            }

            $id = $item['id'] ?? $item['file_id'] ?? null;
            $type = $item['type'] ?? $item['media_type'] ?? 'image';

            return [
                'id' => $id,
                'type' => $type,
                'url' => $id ? url('/api/v1/files/'.$id) : ($item['url'] ?? null),
            ];
        })->all();
    }

    private function extractAttributes(): array
    {
        if (is_object($this->resource) && method_exists($this->resource, 'getAttributes')) {
            return $this->resource->getAttributes();
        }

        return (array) $this->resource;
    }

    private function hasOtherUserContext(array $attributes): bool
    {
        if (array_key_exists('initiator_user_id', $attributes) && array_key_exists('peer_user_id', $attributes)) {
            return true;
        }

        return array_key_exists('from_user_id', $attributes) && array_key_exists('to_user_id', $attributes);
    }
}
