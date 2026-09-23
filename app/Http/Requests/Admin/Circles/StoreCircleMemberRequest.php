<?php

namespace App\Http\Requests\Admin\Circles;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCircleMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $circleId = $this->route('circle')?->id;

        return [
            'user_id' => [
                'required',
                'uuid',
                'exists:users,id',
                Rule::unique('circle_members', 'user_id')->where(fn ($query) => $query->where('circle_id', $circleId)->whereNull('deleted_at')),
            ],
            'role' => ['required', 'string'],
            'level4_category_id' => ['required', 'integer', 'exists:circle_category_level4,id'],
            'level1_category_id' => ['nullable', 'integer', 'exists:circle_categories,id'],
            'joined_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:joined_at'],
            'circle_joined_at' => ['nullable', 'date'],
            'circle_expires_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.unique' => 'This peer is already a member of this circle.',
            'level4_category_id.required' => 'Please select a sub category for the peer.',
            'level4_category_id.exists' => 'The selected sub category is invalid.',
        ];
    }
}
