<?php

namespace App\Http\Requests\Admin\Circles;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCircleMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string'],
        ];
    }
}
