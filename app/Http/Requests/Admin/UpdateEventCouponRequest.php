<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $couponId = $this->route('id') ?? $this->route('coupon');

        return [
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('event_coupons', 'code')->ignore($couponId)],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'discount_type' => ['sometimes', 'string', 'in:full,percentage,fixed'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['nullable', 'boolean'],
            'event_id' => ['nullable', 'uuid', 'exists:events,id'],
            'occurrence_id' => ['nullable', 'uuid', 'exists:event_occurrences,id'],
        ];
    }
}
