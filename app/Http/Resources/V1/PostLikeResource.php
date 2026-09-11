<?php

namespace App\Http\Resources\V1;

use App\Models\CircleCategory;
use App\Models\CircleCategoryLevel4;
use App\Models\City;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        $designation = null;
        $subCategory = null;
        $coins = 0;

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
            $subCategory = $this->resolveSubCategory($user);
            $designation = $user->designation ?? $user->job_title ?? null;
            $coins = (int) ($user->coins_balance ?? 0);
        }

        return [
            'liked_at' => $this->created_at,
            'name' => $name,
            'city' => $city,
            'company_name' => $business,
            'designation' => $designation,
            'level4_category' => $subCategory,
            'user' => new UserMiniResource($this->whenLoaded('user')),
        ];
    }

    private function resolveCity($user): ?string
    {
        if (! $user) {
            return null;
        }

        if ($user->relationLoaded('city')) {
            $cityRelation = $user->getRelation('city');
            if ($cityRelation instanceof City) {
                return $cityRelation->name ?? null;
            }
        }

        if ($user->relationLoaded('cityRelation')) {
            $cityRelation = $user->getRelation('cityRelation');
            if ($cityRelation instanceof City) {
                return $cityRelation->name ?? null;
            }
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

    private function resolveSubCategory($user): ?string
    {
        if (! $user) {
            return null;
        }

        if ($user->relationLoaded('level4Category') && $user->level4Category) {
            return $user->level4Category->name ?? null;
        }

        if (filled($user->business_sub_category)) {
            return trim((string) $user->business_sub_category);
        }

        if (! empty($user->business_category_id) && class_exists(CircleCategoryLevel4::class) && Schema::hasTable('circle_category_level4')) {
            $cat = CircleCategoryLevel4::find($user->business_category_id);
            if ($cat && filled($cat->name)) {
                return trim((string) $cat->name);
            }
        }

        if ($user->relationLoaded('circleMembers')) {
            $membership = $user->circleMembers->first();
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
                    ->where('user_id', (string) $user->id)
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
}
