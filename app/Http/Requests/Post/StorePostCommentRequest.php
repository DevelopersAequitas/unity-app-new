<?php

namespace App\Http\Requests\Post;

use Illuminate\Foundation\Http\FormRequest;

class StorePostCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => 'required|string',
            'parent_id' => 'nullable|uuid|exists:post_comments,id',
            'mentions' => 'nullable|array',
            'mentions.*.id' => 'nullable|uuid',
            'mentions.*.name' => 'nullable|string|max:255',
            'tagged_peer_ids' => 'nullable|array',
            'tagged_peer_ids.*' => 'uuid',
        ];
    }
}
