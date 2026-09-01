<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AdminStoreCommissionRateRequest extends FormRequest
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
            'role_id' => ['required', 'string', 'max:100', 'unique:leader_commission_rates,role_id'],
            'role_name' => ['required', 'string', 'max:150'],
            'direct_referral_cut_percentage' => ['required', 'numeric', 'min:0.0', 'max:100.0'],
            'app_join_cut_percentage' => ['required', 'numeric', 'min:0.0', 'max:100.0'],
            'renewal_cut_percentage' => ['nullable', 'numeric', 'min:0.0', 'max:100.0'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
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
            'role_id.required' => 'The Role Key identifier is required.',
            'role_id.unique' => 'A commission configuration for this Role Key already exists.',
            'role_name.required' => 'The Display Role Name is required.',
            'direct_referral_cut_percentage.min' => 'Referral cut percentage cannot be negative.',
            'direct_referral_cut_percentage.max' => 'Referral cut percentage cannot exceed 100%.',
            'app_join_cut_percentage.min' => 'App join cut percentage cannot be negative.',
            'app_join_cut_percentage.max' => 'App join cut percentage cannot exceed 100%.',
            'renewal_cut_percentage.min' => 'Renewal cut percentage cannot be negative.',
            'renewal_cut_percentage.max' => 'Renewal cut percentage cannot exceed 100%.',
        ];
    }
}
