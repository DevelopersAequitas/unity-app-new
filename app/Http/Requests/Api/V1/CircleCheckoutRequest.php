<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\CircleBillingTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CircleCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'billing_term' => ['required', 'string', Rule::in(CircleBillingTerm::values())],
        ];
    }
}
