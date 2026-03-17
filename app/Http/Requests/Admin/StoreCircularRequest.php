<?php

namespace App\Http\Requests\Admin;

use App\Models\Circular;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCircularRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:500'],
            'category' => ['required', Rule::in(Circular::CATEGORY_OPTIONS)],
            'priority' => ['required', Rule::in(Circular::PRIORITY_OPTIONS)],
            'publish_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after:publish_date'],
            'content' => ['nullable', 'string'],
            'video_url' => ['nullable', 'url'],
            'cta_label' => ['nullable', 'string', 'max:120'],
            'cta_url' => ['nullable', 'url'],
            'audience_type' => ['required', Rule::in(Circular::AUDIENCE_OPTIONS)],
            'city_id' => ['nullable', 'uuid', 'exists:cities,id'],
            'circle_id' => ['nullable', 'uuid', 'exists:circles,id'],
            'send_push_notification' => ['nullable', 'boolean'],
            'allow_comments' => ['nullable', 'boolean'],
            'is_pinned' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(Circular::STATUS_OPTIONS)],
            'featured_image_file_id' => ['nullable', 'uuid', 'exists:files,id'],
            'attachment_file_id' => ['nullable', 'uuid', 'exists:files,id'],
        ];
    }
}
