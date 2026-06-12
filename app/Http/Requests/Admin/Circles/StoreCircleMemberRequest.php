<?php

namespace App\Http\Requests\Admin\Circles;

use App\Models\CircleMember;
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
            'role' => ['required', Rule::in(CircleMember::roleOptions())],
        ];
    }
}
