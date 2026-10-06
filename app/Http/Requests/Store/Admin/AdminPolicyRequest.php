<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => 'required|string|max:100',
            'title' => 'required|string|max:300',
            'content' => 'required|string',
            'version' => 'nullable|integer|min:1',
            'status' => 'nullable|string|in:DRAFT,PUBLISHED,ARCHIVED',
        ];
    }
}
