<?php

namespace App\Http\Resources\V1;

use App\Http\Resources\UserResource;
use App\Models\City;
use Illuminate\Http\Request;

class LimitedUserResource extends UserResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $parentData = parent::toArray($request);
        $user = $this->resource;

        $name = $user->display_name
            ?? trim(($user->first_name ?? '').' '.($user->last_name ?? ''));

        $cityName = null;
        $cityRelation = $user->relationLoaded('city') ? $user->getRelation('city') : null;
        if ($cityRelation instanceof City) {
            $cityName = $cityRelation->name;
        } else {
            $cityName = is_string($user->city) ? $user->city : ($user->city_of_residence ?? null);
        }

        return array_merge($parentData, [
            'name' => $name !== '' ? trim((string) $name) : null,
            'profile_photo_image' => $user->profile_photo_url,
            'city' => $cityName,
            'business_name' => $user->company_name,
            'total_life_impact' => (int) ($user->life_impacted_count ?? 0),
            'company_name' => $user->company_name,
            'level4_category' => $user->level4Category ? $user->level4Category->name : null,
        ]);
    }
}
