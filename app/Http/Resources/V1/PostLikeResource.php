<?php

namespace App\Http\Resources\V1;

use App\Models\CircleCategory;
use App\Models\City;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class PostLikeResource extends JsonResource
{
    public function toArray($request): array
    {
        $user = $this->user;

        $name = null;
        $city = null;
        $business = null;
        $category = null;

        if ($user) {
            $name = $user->display_name
                ?: ($user->name
                    ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')));

            if (empty($name) && ! empty($user->email)) {
                $name = Str::before($user->email, '@');
            }
            $name = filled($name) ? trim((string) $name) : null;

            $city = $this->resolveCity($user);
            $business = $user->company_name ?: ($user->business_name ?? null);
            $category = $this->resolveCategory($user);
        }

        return [
            'liked_at' => $this->created_at,
            'name' => $name,
            'city' => $city,
            'business' => $business,
            'category' => $category,
            'user' => new UserMiniResource($this->whenLoaded('user')),
        ];
    }

    private function resolveCity($user): ?string
    {
        if (! $user) {
            return null;
        }

        if ($user->relationLoaded('city') && $user->city instanceof City) {
            return $user->city->name ?? null;
        }

        if ($user->relationLoaded('cityRelation') && $user->cityRelation instanceof City) {
            return $user->cityRelation->name ?? null;
        }

        $city = $user->getAttribute('city');
        if (is_array($city)) {
            return $city['name'] ?? null;
        }
        if (is_object($city)) {
            return $city->name ?? null;
        }
        if (is_string($city) && trim($city) !== '') {
            $trimmedCity = trim($city);
            if (str_starts_with($trimmedCity, '{')) {
                $decoded = json_decode($trimmedCity, true);
                if (is_array($decoded) && ! empty($decoded['name'])) {
                    return trim((string) $decoded['name']);
                }
            }

            return $trimmedCity;
        }

        $cityName = $user->getAttribute('city_name');
        if (is_string($cityName) && trim($cityName) !== '') {
            return trim($cityName);
        }

        $businessCity = $user->getAttribute('business_city');
        if (is_string($businessCity) && trim($businessCity) !== '') {
            return trim($businessCity);
        }

        $cityOfResidence = $user->getAttribute('city_of_residence');
        if (is_string($cityOfResidence) && trim($cityOfResidence) !== '') {
            return trim($cityOfResidence);
        }

        return null;
    }

    private function resolveCategory($user): ?string
    {
        if (! $user) {
            return null;
        }

        if ($user->relationLoaded('businessCategory') && $user->businessCategory instanceof CircleCategory) {
            return $user->businessCategory->name ?? null;
        }

        if ($user->relationLoaded('mainBusinessCategory') && $user->mainBusinessCategory instanceof CircleCategory) {
            return $user->mainBusinessCategory->name ?? null;
        }

        if ($user->businessCategory && is_object($user->businessCategory)) {
            return $user->businessCategory->name ?? null;
        }

        if ($user->mainBusinessCategory && is_object($user->mainBusinessCategory)) {
            return $user->mainBusinessCategory->name ?? null;
        }

        $category = $user->getAttribute('business_category');
        if (is_string($category) && trim($category) !== '') {
            return trim($category);
        }

        $subCategory = $user->getAttribute('business_sub_category');
        if (is_string($subCategory) && trim($subCategory) !== '') {
            return trim($subCategory);
        }

        return null;
    }
}
