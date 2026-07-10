<?php

namespace App\Http\Requests\Api\CircleJoinRequests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCircleJoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'circle_id' => ['nullable', 'uuid', 'exists:circles,id'],
            'category_id' => ['required', 'integer', 'exists:circle_categories,id'],
            'reason_for_joining' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'level4_category_id' => ['nullable', 'integer', 'exists:circle_category_level4,id'],
        ];
    }
}
