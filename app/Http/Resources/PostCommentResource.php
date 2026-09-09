<?php

namespace App\Http\Resources;

use App\Models\CircleCategoryLevel4;
use App\Models\City;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PostCommentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'user_id' => $this->user_id,
            'parent_id' => $this->parent_id,
            'content' => $this->content,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => $this->whenLoaded('user', function () {
                $user = $this->user;
                if (! $user) {
                    return null;
                }

                $subCategory = $this->resolveSubCategory($user);
                $cityName = $this->resolveCity($user);
                $companyName = $user->company_name ?: ($user->business_name ?? null);
                $designation = $user->designation ?? $user->job_title ?? null;
                $coins = (int) ($user->coins_balance ?? 0);

                return [
                    'id' => $user->id,
                    'display_name' => $user->display_name,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'profile_photo_url' => $user->profile_photo_url,
                    'city' => $cityName,
                    'designation' => $designation,
                    'company_name' => $companyName,
                    'level4_category' => $subCategory,
                    'impact_coins' => $coins,
                ];
            }),
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
