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
        $countryCode = 'IN';

        $cityRelation = $user->relationLoaded('city') ? $user->getRelation('city') : null;
        if ($cityRelation instanceof City) {
            $cityName = $cityRelation->name;
            $countryCode = $cityRelation->country_code ?: ($cityRelation->country ? ($cityRelation->country === 'India' ? 'IN' : strtoupper(substr((string) $cityRelation->country, 0, 2))) : 'IN');
        } else {
            $cityName = is_string($user->city) ? $user->city : ($user->city_of_residence ?? null);
            if (! empty($user->country)) {
                $countryCode = $user->country === 'India' ? 'IN' : strtoupper(substr((string) $user->country, 0, 2));
            }
        }

        $formattedCity = null;
        if (filled($cityName)) {
            $cityName = trim((string) $cityName);
            if (str_contains($cityName, ',')) {
                $formattedCity = $cityName;
            } else {
                $formattedCity = "{$cityName}, {$countryCode}";
            }
        }

        $authUser = auth('sanctum')->user();
        $isBookmark = false;
        if ($authUser) {
            $bookmarks = $authUser->bookmarks ?? [];
            $isBookmark = in_array((string) $user->id, $bookmarks, true);
        }

        $rawVerified = $user->is_verified ?? null;
        if ($rawVerified !== null) {
            $isVerified = (bool) $rawVerified;
        } elseif (method_exists($user, 'isPaidMember')) {
            $isVerified = (bool) $user->isPaidMember();
        } else {
            $isVerified = false;
        }

        return [
            'id' => $user->id,
            'name' => $name !== '' ? trim((string) $name) : null,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'city' => $formattedCity,
            'company_name' => $user->company_name,
            'life_impacted_count' => (int) ($user->life_impacted_count ?? 0),
            'profile_photo_image' => $user->profile_photo_url,
            'designation' => $user->designation,
            'level4_category' => $user->level4Category ? $user->level4Category->name : null,
            'is_bookmark' => $isBookmark,
            'is_verified' => $isVerified,
            'match_percentage' => (int) ($user->match_percentage ?? 0),
        ];
    }
}
