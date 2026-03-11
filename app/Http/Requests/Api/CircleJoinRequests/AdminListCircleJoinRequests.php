<?php

namespace App\Http\Requests\Api\CircleJoinRequests;

use Illuminate\Foundation\Http\FormRequest;

class AdminListCircleJoinRequests extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'circle_id' => ['nullable', 'uuid', 'exists:circles,id'],
            'status' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ];
    }
}
