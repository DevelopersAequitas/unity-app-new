<?php

namespace App\Http\Requests\Activity;

use Illuminate\Foundation\Http\FormRequest;

class StoreTestimonialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to_user_id' => ['required', 'uuid', 'exists:users,id'],
            'content' => ['required', 'string'],
            'media_id' => ['nullable', 'uuid'],
            'referral_id' => ['nullable', 'uuid', 'exists:referrals,id'],
        ];
    }
}
