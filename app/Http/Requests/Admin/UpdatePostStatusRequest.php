<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required_without_all:active,is_active', 'nullable', 'string', 'in:active,inactive,approved,pending,rejected,published,hidden,flagged'],
            'active' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'moderation_status' => ['nullable', 'string', 'in:approved,pending,rejected,flagged'],
        ];
    }
}
