<?php

declare(strict_types=1);

namespace App\Http\Resources\Ask;

use App\Models\Ask\AskResponseContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property AskResponseContact $resource
 */
class AskResponseContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'response_id' => (string) $this->resource->response_id,
            'full_name' => $this->resource->full_name,
            'company_name' => $this->resource->company_name,
            'designation' => $this->resource->designation,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'alternate_phone' => $this->resource->alternate_phone,
            'notes' => $this->resource->notes,
            'metadata' => $this->resource->metadata ?? (object) [],
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
