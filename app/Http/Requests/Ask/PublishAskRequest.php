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
            'visibility' => ['nullable', 'string', 'in:global,district,circle,all_peers'],
            'content_text' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
