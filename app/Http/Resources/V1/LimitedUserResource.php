<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LimitedUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
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

        $authUser = auth('sanctum')->user();
        $isBookmark = false;
        if ($authUser) {
            $bookmarks = $authUser->bookmarks ?? [];
            $isBookmark = in_array((string) $user->id, $bookmarks, true);
        }

        return [
            'id' => $user->id,
            'name' => $name !== '' ? trim((string) $name) : null,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'city' => $cityName,
            'business' => $user->company_name,
            'total_life_impact' => (int) ($user->life_impacted_count ?? 0),
            'life_impacted_count' => (int) ($user->life_impacted_count ?? 0),
            'profile_photo_image' => $user->profile_photo_url,
            'designation' => $user->designation,
            'level4_category' => $user->level4Category ? $user->level4Category->name : null,
            'is_bookmark' => $isBookmark,
        ];
    }
}
