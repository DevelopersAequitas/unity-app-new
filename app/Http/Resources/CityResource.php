<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        // If a plain string is passed instead of a City model, handle gracefully
        if (is_string($this->resource)) {
            return [
                'id' => null,
                'name' => $this->resource,
                'state' => null,
                'state_code' => null,
                'country' => null,
                'country_code' => null,
                'formatted_location' => $this->resource,
                'display_name' => $this->resource,
            ];
        }

        $cityName = trim((string) ($this->name ?? $this->city_name ?? ''));
        $stateName = $this->state ? trim((string) $this->state) : null;
        $stateCode = null;

        if ($stateName !== null && $stateName !== '') {
            $stateKey = strtolower($stateName);
            if (isset(City::$stateCodes[$stateKey])) {
                $stateCode = City::$stateCodes[$stateKey];
            } elseif (strlen($stateName) <= 3) {
                $stateCode = strtoupper($stateName);
            }
        }

        $countryCode = $this->country_code ? strtoupper(trim((string) $this->country_code)) : null;
        $countryName = (string) ($this->country ?? 'India');

        $formattedLocation = $this->formatted_location;
        if (empty($formattedLocation)) {
            $parts = array_values(array_filter([
                $cityName,
                $stateCode ?: $stateName,
                $countryCode,
            ], fn ($val) => $val !== null && $val !== ''));
            $formattedLocation = implode(', ', $parts);
        }

        return [
            'id' => (string) $this->id,
            'name' => $cityName,
            'state' => $stateName,
            'state_code' => $stateCode,
            'country' => $countryName,
            'country_code' => $countryCode,
            'formatted_location' => $formattedLocation,
            'display_name' => $formattedLocation,
        ];
    }
}
