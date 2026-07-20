<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;

class TopIntroducerResource extends LimitedUserResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $data = parent::toArray($request);
        $data['rank'] = (int) ($this->rank ?? 0);
        $data['introduced_members_count'] = (int) ($this->introduced_members_count ?? 0);

        return $data;
    }
}
