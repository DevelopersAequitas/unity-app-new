<?php

declare(strict_types=1);

namespace App\Http\Requests\Post;

use Illuminate\Foundation\Http\FormRequest;

class StorePostSaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $hasRouteId = (bool) ($this->route('id') ?? $this->route('post'));

        return [
            'post_id' => $hasRouteId
                ? ['nullable', 'uuid', 'exists:posts,id']
                : ['required', 'uuid', 'exists:posts,id'],
            'action' => ['nullable', 'string', 'in:save,unsave,toggle'],
        ];
    }
}
