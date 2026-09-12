<?php

namespace App\Http\Resources;

use App\Models\CircleCategoryLevel4;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            'level4_category' => $this->resolveLevel4Category(),
            'profile_photo_url' => $this->resolveProfilePhotoUrl(),
            'city' => $this->resolveCity(),
            'location' => $this->resolveLocation(),
            'distance_km' => round((float) $this->distance_km, 2),
            'life_impacted_count' => (int) ($this->life_impacted_count ?? 0),
            'last_seen_at' => $this->geo_last_seen_at,
            'connection_status' => $this->connection_status,
            'can_send_connection_request' => (bool) ($this->can_send_connection_request ?? true),
        ];
    }

    /**
     * Resolve Level 4 Category Name with multi-layer fallbacks.
     */
    private function resolveLevel4Category(): ?string
    {
        if ($this->relationLoaded('level4Category') && $this->level4Category) {
            return $this->level4Category->name ?? null;
        }

        if (filled($this->business_sub_category)) {
            return trim((string) $this->business_sub_category);
        }

        if (! empty($this->business_category_id) && class_exists(CircleCategoryLevel4::class) && Schema::hasTable('circle_category_level4')) {
            $cat = CircleCategoryLevel4::find($this->business_category_id);
            if ($cat && filled($cat->name)) {
                return trim((string) $cat->name);
            }
        }

        if ($this->relationLoaded('circleMembers')) {
            $membership = $this->circleMembers->first();
            if ($membership) {
                if ($membership->relationLoaded('level4Category') && $membership->level4Category) {
                    return $membership->level4Category->name ?? null;
                }
                if (! empty($membership->level_4_category_id) && class_exists(CircleCategoryLevel4::class) && Schema::hasTable('circle_category_level4')) {
                    $cat = CircleCategoryLevel4::find($membership->level_4_category_id);
                    if ($cat && filled($cat->name)) {
                        return trim((string) $cat->name);
                    }
                }
            }
        }

        if (Schema::hasTable('circle_members') && Schema::hasColumn('circle_members', 'level_4_category_id') && class_exists(CircleCategoryLevel4::class) && Schema::hasTable('circle_category_level4')) {
            try {
                $level4Id = DB::table('circle_members')
                    ->where('user_id', (string) $this->id)
                    ->whereNotNull('level_4_category_id')
                    ->where('level_4_category_id', '>', 0)
                    ->value('level_4_category_id');

                if ($level4Id) {
                    $name = DB::table('circle_category_level4')->where('id', $level4Id)->value('name');
                    if (filled($name)) {
                        return trim((string) $name);
                    }
                }
            } catch (\Throwable) {
            }
        }

        if (Schema::hasTable('joined_circle_categories') && Schema::hasColumn('joined_circle_categories', 'level_4_category_id') && class_exists(CircleCategoryLevel4::class) && Schema::hasTable('circle_category_level4')) {
            try {
                $level4Id = DB::table('joined_circle_categories')
                    ->where('user_id', (string) $this->id)
                    ->whereNotNull('level_4_category_id')
                    ->where('level_4_category_id', '>', 0)
                    ->value('level_4_category_id');

                if ($level4Id) {
                    $name = DB::table('circle_category_level4')->where('id', $level4Id)->value('name');
                    if (filled($name)) {
                        return trim((string) $name);
                    }
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    private function resolveProfilePhotoUrl(): ?string
    {
        return $this->profile_photo_file_id
            ? url('/api/v1/files/'.$this->profile_photo_file_id)
            : null;
    }

    private function resolveLocation(): array
    {
        return [
            'latitude' => (float) $this->geo_latitude,
            'longitude' => (float) $this->geo_longitude,
        ];
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

        $rawCity = $this->city;

        if (is_array($rawCity)) {
            return [
                'id' => $rawCity['id'] ?? null,
                'name' => $this->normalizeCityName($rawCity['name'] ?? $rawCity),
            ];
        }

        if (is_object($rawCity)) {
            return [
                'id' => $rawCity->id ?? null,
                'name' => $this->normalizeCityName($rawCity->name ?? $rawCity),
            ];
        }

        $normalizedCityName = $this->normalizeCityName($rawCity);

        if ($normalizedCityName !== null) {
            return [
                'id' => null,
                'name' => $normalizedCityName,
            ];
        }

        return null;
    }

    private function normalizeCityName(mixed $cityValue): ?string
    {
        if (is_string($cityValue)) {
            $cityValue = trim($cityValue);

            if ($cityValue === '') {
                return null;
            }

            $decodedJson = json_decode($cityValue, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedJson)) {
                $name = trim((string) ($decodedJson['name'] ?? ''));

                return $name !== '' ? $name : null;
            }

            if (preg_match('/name\\s*:\\s*"?([^",}]+)"?/i', $cityValue, $matches)) {
                $name = trim($matches[1]);

                return $name !== '' ? $name : null;
            }

            return $cityValue;
        }

        if (is_array($cityValue)) {
            $name = trim((string) ($cityValue['name'] ?? ''));

            return $name !== '' ? $name : null;
        }

        if (is_object($cityValue)) {
            $name = trim((string) ($cityValue->name ?? ''));

            return $name !== '' ? $name : null;
        }

        return null;
    }
}
