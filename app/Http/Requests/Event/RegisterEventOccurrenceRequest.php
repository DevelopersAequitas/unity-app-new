<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;

class RegisterEventOccurrenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source' => ['sometimes', 'string', 'in:app,admin,scanner,zoho_form'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'inviter_code' => ['nullable', 'string', 'max:50'],
            'invited_by_referral_code' => ['nullable', 'string', 'max:50'],
            'referral_code' => ['nullable', 'string', 'max:50'],
            'invited_by_user_id' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'request_reason' => ['nullable', 'string', 'max:1000'],
            'business_category_id' => ['nullable'],
            'category_id' => ['nullable'],
            'visitor_business_category_id' => ['nullable'],
        ];
    }
}
