<?php

namespace App\Http\Requests\Activity;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to_user_id' => ['required', 'uuid', 'exists:users,id'],
            'referral_type' => [
                'required',
                'string',
                Rule::in([
                    'customer_referral',
                    'b2b_referral',
                    'b2g_referral',
                    'collaborative_projects',
                    'referral_partnerships',
                    'vendor_referrals',
                    'others',
                ]),
            ],
            'referral_date' => ['required', 'date_format:Y-m-d'],
            'referral_of' => ['required', 'string'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email'],
            'address' => ['nullable', 'string'],
            'hot_value' => ['required', 'integer', 'min:1', 'max:5'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
