<?php

declare(strict_types=1);

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
            'latest_version' => ['nullable', 'string', 'required_without_all:latest_version_android,latest_version_ios'],
            'latest_version_android' => ['nullable', 'string'],
            'latest_version_ios' => ['nullable', 'string'],
            'min_version' => ['required', 'string'],
            'update_type' => ['required', 'in:force,optional'],
        ];
    }
}
