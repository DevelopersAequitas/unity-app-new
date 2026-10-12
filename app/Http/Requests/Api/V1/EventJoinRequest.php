<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class EventJoinRequest extends FormRequest
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'request_reason' => ['nullable', 'string', 'max:2000'],
            'referral_code' => ['nullable', 'string', 'max:100'],
            'inviter_code' => ['nullable', 'string', 'max:100'],
            'invited_by_referral_code' => ['nullable', 'string', 'max:100'],
            'invited_by_user_id' => ['nullable', 'string', 'max:100'],
            'invited_by' => ['nullable', 'string', 'max:100'],
        ];
    }
}
