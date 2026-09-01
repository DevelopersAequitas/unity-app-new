<?php

declare(strict_types=1);

namespace App\Http\Requests\Leader;

use Illuminate\Foundation\Http\FormRequest;

class LeaderUpdateCommissionRatesRequest extends FormRequest
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
            'commission_rates' => ['required', 'array', 'min:1'],
            'commission_rates.*.role_id' => ['required', 'string'],
            'commission_rates.*.role_name' => ['nullable', 'string'],
            'commission_rates.*.direct_referral_cut_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'commission_rates.*.app_join_cut_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'commission_rates.*.renewal_cut_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'commission_rates.*.direct_referral_cut_percentage.max' => 'The direct referral cut percentage must be between 0.0 and 100.0.',
            'commission_rates.*.direct_referral_cut_percentage.min' => 'The direct referral cut percentage must be between 0.0 and 100.0.',
            'commission_rates.*.app_join_cut_percentage.max' => 'The app join cut percentage must be between 0.0 and 100.0.',
            'commission_rates.*.app_join_cut_percentage.min' => 'The app join cut percentage must be between 0.0 and 100.0.',
            'commission_rates.*.renewal_cut_percentage.max' => 'The renewal cut percentage must be between 0.0 and 100.0.',
            'commission_rates.*.renewal_cut_percentage.min' => 'The renewal cut percentage must be between 0.0 and 100.0.',
            'commission_rates.*.direct_referral_cut_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'commission_rates.*.app_join_cut_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
