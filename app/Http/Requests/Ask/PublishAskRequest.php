<?php

declare(strict_types=1);

namespace App\Http\Requests\Ask;

use Illuminate\Foundation\Http\FormRequest;

class PublishAskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'post_to_timeline' => ['nullable', 'boolean'],
            'publish_to_timeline' => ['nullable', 'boolean'],
            'visibility' => ['nullable', 'string'],
            'visibility_type' => ['nullable', 'string'],
            'district_id' => ['nullable', 'string'],
            'circle_id' => ['nullable', 'string'],
            'content_text' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
