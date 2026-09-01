<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ConnectionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'is_approved' => (bool) $this->is_approved,
            'approved_at' => $this->approved_at,
            'created_at' => $this->created_at,
            'requester' => [
                'id' => $this->requester?->id,
                'display_name' => $this->requester?->display_name,
                'first_name' => $this->requester?->first_name,
                'last_name' => $this->requester?->last_name,
                'profile_photo_url' => $this->requester?->profile_photo_url,
                'company_name' => $this->requester?->company_name,
                'city' => $this->resolveCity($this->requester),
                'category' => $this->requester?->level4Category?->name ?? null,
                'designation' => $this->requester?->designation ?? null,
            ],
            'addressee' => [
                'id' => $this->addressee?->id,
                'display_name' => $this->addressee?->display_name,
                'first_name' => $this->addressee?->first_name,
                'last_name' => $this->addressee?->last_name,
                'profile_photo_url' => $this->addressee?->profile_photo_url,
                'company_name' => $this->addressee?->company_name,
                'city' => $this->resolveCity($this->addressee),
                'category' => $this->addressee?->level4Category?->name ?? null,
                'designation' => $this->addressee?->designation ?? null,
            ],
        ];
    }

    private function resolveCity($user): ?string
    {
        if (! $user) {
            return null;
        }

        $cityRelation = $user->relationLoaded('city')
            ? $user->getRelationValue('city')
            : null;

        if ($cityRelation) {
            return $cityRelation->name;
        }

        return $user->city_name ?? $user->city ?? null;
    }
}
