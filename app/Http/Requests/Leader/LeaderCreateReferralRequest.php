<?php

declare(strict_types=1);

namespace App\Http\Requests\Leader;

use Illuminate\Foundation\Http\FormRequest;

class LeaderCreateReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'to_peer_id' => ['required', 'string'],
            'prospect_name' => ['required', 'string', 'max:255'],
            'prospect_company' => ['nullable', 'string', 'max:255'],
            'prospect_phone' => ['nullable', 'string', 'max:50'],
            'prospect_email' => ['nullable', 'email', 'max:255'],
            'estimated_deal_value' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
