<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Finance;

use Illuminate\Foundation\Http\FormRequest;

class UpsertCommissionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role_id' => ['nullable', 'uuid'],
            'role_code' => ['required', 'string', 'max:50'],
            'role_name' => ['nullable', 'string', 'max:120'],
            'channel_key' => ['required', 'string', 'exists:finance_acquisition_channels,channel_key'],
            'category_code' => ['nullable', 'string', 'max:10'],
            'allocation_target' => ['nullable', 'string', 'in:circle_budget,district_budget,industry_budget,leader_wallet'],
            'base_amount_inr' => ['nullable', 'numeric', 'min:0'],
            'share_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'payout_amount' => ['nullable', 'numeric', 'min:0'],
            'conditions_list' => ['nullable', 'array'],
            'conditions_list.*' => ['string'],
            'responsibilities_list' => ['nullable', 'array'],
            'responsibilities_list.*' => ['string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}

