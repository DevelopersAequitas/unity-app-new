<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualLifeImpactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('reference_date')) {
            $this->merge([
                'reference_date' => now()->toDateString(),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'impact_type' => ['required', 'string', Rule::in(['business_deal', 'qualified_referral', 'testimonial_received', 'manual'])],
            'impact_value' => ['required', 'integer', 'min:1', 'max:10000'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'reference_date' => ['required', 'date'],
            'remark' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
