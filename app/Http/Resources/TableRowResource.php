<?php

namespace App\Http\Resources;

use App\Support\ActivityHistory\OtherUserDetailsResolver;
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
            unset(
                $attributes['other_user_name'],
                $attributes['other_user_profile_photo_url'],
                $attributes['other_user_designation'],
                $attributes['other_user_company_name'],
                $attributes['other_user_city'],
                $attributes['other_user_level4_category'],
                $attributes['other_user_life_impacted_count']
            );

            $detailsResolver = app(OtherUserDetailsResolver::class);
            $attributes['other_user'] = $detailsResolver->resolve($request->user(), $this->resource);

            $fromId = $attributes['from_user_id'] ?? $attributes['initiator_user_id'] ?? null;
            $toId = $attributes['to_user_id'] ?? $attributes['peer_user_id'] ?? null;

            if ($fromId) {
                $attributes['given_by'] = $detailsResolver->resolveUserById((string) $fromId);
            }
            if ($toId) {
                $attributes['given_to'] = $detailsResolver->resolveUserById((string) $toId);
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
