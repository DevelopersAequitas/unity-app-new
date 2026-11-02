<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ProcessSubscriptionCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'member_user_id' => ['required', 'uuid', 'exists:users,id'],
            'circle_id' => ['nullable', 'uuid', 'exists:circles,id'],
            'payment_id' => ['nullable', 'string', 'max:120'],
            'base_amount' => ['nullable', 'numeric', 'min:1'],
            'is_renewal' => ['nullable', 'boolean'],
            'introducer_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'channel_key' => ['nullable', 'string', 'max:60'],
            'role_code' => ['nullable', 'string', 'max:50'],
            'payment_date' => ['nullable', 'date'],
        ];
    }
}
