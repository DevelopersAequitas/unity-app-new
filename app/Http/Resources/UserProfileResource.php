<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'first_name'         => $this->first_name,
            'last_name'          => $this->last_name,
            'display_name'       => $this->display_name,
            'email'              => $this->email,
            'phone'              => $this->phone,

            'company_name'       => $this->company_name,
            'designation'        => $this->designation,
            'business_type'      => $this->business_type,

            'about'              => $this->short_bio,

            'gender'             => $this->gender,
            'dob'                => optional($this->dob)?->format('Y-m-d'),

            'experience_years'   => $this->experience_years,
            'experience_summary' => $this->experience_summary,
            'life_impacted_count' => (int) ($this->life_impacted_count ?? 0),

            'city'               => $this->city,

            'skills'             => $this->skills ?? [],
            'interests'          => $this->interests ?? [],

            'social_links'       => $this->social_links,

            'profile_photo_id'   => $this->profile_photo_file_id,
            'cover_photo_id'     => $this->cover_photo_file_id,

            'profile_photo_url'  => $this->profile_photo_file_id
                ? url("/api/v1/files/{$this->profile_photo_file_id}")
                : null,
            'cover_photo_url'    => $this->cover_photo_file_id
                ? url("/api/v1/files/{$this->cover_photo_file_id}")
                : null,

            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
        ];
    }
}
