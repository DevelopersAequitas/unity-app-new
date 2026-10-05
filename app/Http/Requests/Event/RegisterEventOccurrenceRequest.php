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
            'referral_code' => ['nullable', 'string', 'max:255'],
            'invited_by' => ['nullable', 'string', 'max:255'],
            'invited_by_type' => ['nullable', 'string', 'max:50'],
            'invited_by_user_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
