<?php

namespace App\Http\Requests\Circle;

use App\Models\Circle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCircleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:200',
            'description' => 'sometimes|string|nullable',
            'purpose' => 'sometimes|string|nullable',
            'announcement' => 'sometimes|string|nullable',
            'city_id' => 'sometimes|uuid|exists:cities,id|nullable',
            'type' => ['sometimes', 'nullable', Rule::in(Circle::TYPE_OPTIONS)],
            'industry_tags' => 'sometimes|array',
            'industry_tags.*' => 'string|max:50',
            'calendar' => 'sometimes|array',
            'meeting_mode' => ['sometimes', 'nullable', Rule::in(Circle::MEETING_MODE_OPTIONS)],
            'meeting_frequency' => ['sometimes', 'nullable', Rule::in(Circle::MEETING_FREQUENCY_OPTIONS)],
            'meeting_repeat' => 'sometimes|nullable|array',
            'launch_date' => 'sometimes|nullable|date',
            'director_user_id' => 'sometimes|nullable|uuid|exists:users,id',
            'industry_director_user_id' => 'sometimes|nullable|uuid|exists:users,id',
            'ded_user_id' => 'sometimes|nullable|uuid|exists:users,id',
            'cover_file_id' => 'sometimes|nullable|uuid',
            'circle_image_file_id' => 'sometimes|nullable|uuid',
        ];
    }
}
