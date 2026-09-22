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
            'content_text' => 'nullable|string|max:5000',
            'image_id' => ['nullable', 'uuid'],
            'circle_id' => 'nullable|uuid|exists:circles,id',
            'visibility' => 'required|in:public,connections,members,circle,private',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:100',
            'media' => 'nullable|array',
            'media.*.id' => 'required_with:media|uuid|exists:files,id',
            'media.*.type' => 'required_with:media|string|max:50',
            'mentions' => 'nullable|array',
            'mentions.*.id' => 'required_with:mentions|uuid|exists:users,id',
            'mentions.*.name' => 'nullable|string|max:255',
            'tagged_peer_ids' => 'nullable|array',
            'tagged_peer_ids.*' => 'uuid|exists:users,id',
            'sponsored' => 'nullable|boolean',
        ];
    }
}
