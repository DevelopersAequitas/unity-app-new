<?php

namespace App\Http\Requests\Circle;

use App\Models\Circle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCircleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'purpose' => 'nullable|string',
            'announcement' => 'nullable|string',
            'template_id' => 'nullable|uuid|exists:circle_templates,id',
            'city_id' => 'nullable|uuid|exists:cities,id',
            'type' => ['nullable', Rule::in(Circle::TYPE_OPTIONS)],
            'industry_tags' => 'sometimes|array',
            'industry_tags.*' => 'string|max:50',
            'calendar' => 'sometimes|array',
            'meeting_mode' => ['nullable', Rule::in(Circle::MEETING_MODE_OPTIONS)],
            'meeting_frequency' => ['nullable', Rule::in(Circle::MEETING_FREQUENCY_OPTIONS)],
            'meeting_repeat' => 'nullable|array',
            'launch_date' => 'nullable|date',
            'director_user_id' => 'nullable|uuid|exists:users,id',
            'industry_director_user_id' => 'nullable|uuid|exists:users,id',
            'ded_user_id' => 'nullable|uuid|exists:users,id',
            'cover_file_id' => 'nullable|uuid',
            'circle_image_file_id' => 'nullable|uuid',
        ];
    }
}
