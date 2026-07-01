<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class LimitedUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $user = $this->resource;

        $name = $user->display_name
            ?? trim(($user->first_name ?? '').' '.($user->last_name ?? ''));

        $cityName = null;
        $cityRelation = $user->relationLoaded('city') ? $user->getRelation('city') : null;
        if ($cityRelation instanceof \App\Models\City) {
            $cityName = $cityRelation->name;
        } else {
            $cityName = is_string($user->city) ? $user->city : ($user->city_of_residence ?? null);
        }

        return [
            'id' => $user->id,
            'name' => $name !== '' ? trim((string) $name) : null,
            'profile_photo_image' => $user->profile_photo_url,
            'city' => $cityName,
            'business_name' => $user->company_name,
            'total_life_impact' => (int) ($user->life_impacted_count ?? 0),
            'company_name' => $user->company_name,
            'timezone' => $user->timezone ?? null,
        ];
    }
}
