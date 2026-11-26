<?php

declare(strict_types=1);

namespace App\Http\Requests\SpeedNetworking;

use Illuminate\Foundation\Http\FormRequest;

class SpeedNetworkingMatchActionRequest extends FormRequest
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
            'action' => ['required', 'string', 'in:connect,skip,block,accept,accepted'],
            'peer_id' => ['nullable', 'string'],
            'channel_id' => ['nullable', 'string'],
            'match_id' => ['nullable', 'string'],
            'reason' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ];
    }
}
