<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AdminUpdateCommissionRatesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'commission_rates' => ['required', 'array', 'min:1'],
            'commission_rates.*.role_id' => ['required', 'string', 'max:100'],
            'commission_rates.*.role_name' => ['nullable', 'string', 'max:150'],
            'commission_rates.*.direct_referral_cut_percentage' => ['required', 'numeric', 'min:0.0', 'max:100.0'],
            'commission_rates.*.app_join_cut_percentage' => ['required', 'numeric', 'min:0.0', 'max:100.0'],
            'commission_rates.*.renewal_cut_percentage' => ['nullable', 'numeric', 'min:0.0', 'max:100.0'],
            'commission_rates.*.description' => ['nullable', 'string', 'max:500'],
            'commission_rates.*.is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'commission_rates.required' => 'Commission rates matrix cannot be empty.',
            'commission_rates.*.direct_referral_cut_percentage.min' => 'Referral cut percentage cannot be negative.',
            'commission_rates.*.direct_referral_cut_percentage.max' => 'Referral cut percentage cannot exceed 100%.',
            'commission_rates.*.app_join_cut_percentage.min' => 'App join cut percentage cannot be negative.',
            'commission_rates.*.app_join_cut_percentage.max' => 'App join cut percentage cannot exceed 100%.',
            'commission_rates.*.renewal_cut_percentage.min' => 'Renewal cut percentage cannot be negative.',
            'commission_rates.*.renewal_cut_percentage.max' => 'Renewal cut percentage cannot exceed 100%.',
        ];
    }
}
