<?php

namespace App\Http\Requests\Activity;

use Illuminate\Foundation\Http\FormRequest;

class StoreBusinessDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to_user_id' => ['required', 'uuid', 'exists:users,id'],
            'deal_date' => ['required', 'date_format:Y-m-d'],
            'deal_amount' => ['required', 'numeric', 'min:0'],
            'business_type' => ['required', 'in:new,repeat'],
            'comment' => ['nullable', 'string'],
            'referral_id' => ['nullable', 'uuid', 'exists:referrals,id'],
        ];
    }
}
