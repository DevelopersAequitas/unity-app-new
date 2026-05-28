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
            'deal_date' => ['required', 'date'],
            'deal_amount' => ['required', 'numeric', 'min:0'],
            'business_type' => ['nullable', 'string'],
            'comment' => ['nullable', 'string'],
            'creative_media' => ['nullable'],
            'creative_media.*' => ['file', 'mimes:jpg,jpeg,png,webp,gif,mp4,mov,avi,webm,pdf', 'max:20480'],
        ];
    }
}
