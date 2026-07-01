<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        // If a plain string is passed instead of a City model, handle gracefully
        if (is_string($this->resource)) {
            return [
                'id' => null,
                'name' => $this->resource,
            ];
        }

        // Normal case: City model exists
        return [
            'id' => $this->id ?? null,
            'name' => $this->name ?? $this->city_name ?? null,
            'state' => $this->state ?? null,
            'country' => $this->country ?? null,
        ];
    }
}
