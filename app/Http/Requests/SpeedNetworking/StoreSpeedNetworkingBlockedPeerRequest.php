<?php

declare(strict_types=1);

namespace App\Http\Requests\SpeedNetworking;

use Illuminate\Foundation\Http\FormRequest;

class StoreSpeedNetworkingBlockedPeerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'peer_id' => ['required_without:peer_ids', 'nullable', 'string'],
            'peer_ids' => ['nullable', 'array'],
            'peer_ids.*' => ['string'],
            'event_id' => ['nullable', 'string'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
