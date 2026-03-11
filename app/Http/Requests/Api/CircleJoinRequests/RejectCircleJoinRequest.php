<?php

namespace App\Http\Requests\Api\CircleJoinRequests;

use Illuminate\Foundation\Http\FormRequest;

class RejectCircleJoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
