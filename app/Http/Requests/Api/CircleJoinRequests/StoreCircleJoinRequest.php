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
            'circle_id' => ['required', 'uuid', 'exists:circles,id'],
            'reason_for_joining' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
