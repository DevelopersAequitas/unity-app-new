<?php

namespace App\Http\Requests\Post;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content_text' => 'nullable|string',
            'image_id' => ['nullable', 'uuid'],
            'circle_id' => 'nullable|uuid|exists:circles,id',
            'visibility' => 'required|in:public,members,circle,private',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'sponsored' => 'nullable|boolean',
        ];
    }
}
