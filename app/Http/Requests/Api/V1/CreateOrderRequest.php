<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'membership_plan_id' => ['required', 'uuid', 'exists:membership_plans,id'],
            'gst_number' => ['nullable', 'string', 'max:50'],
        ];
    }
}
