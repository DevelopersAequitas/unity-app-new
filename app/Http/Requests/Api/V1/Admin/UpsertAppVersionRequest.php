<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpsertAppVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latest_version' => ['required', 'string'],
            'min_version' => ['required', 'string'],
            'update_type' => ['required', 'in:force,optional'],
        ];
    }
}
