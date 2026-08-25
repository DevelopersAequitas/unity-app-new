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
            'commission_rates.*.direct_referral_cut_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'commission_rates.*.app_join_cut_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
