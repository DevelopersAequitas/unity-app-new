<?php

declare(strict_types=1);

namespace App\Http\Requests\Ask;

use Illuminate\Foundation\Http\FormRequest;

class SetTimelinePreferenceRequest extends FormRequest
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
            'publish_to_timeline' => ['nullable', 'boolean'],
            'post_to_timeline' => ['nullable', 'boolean'],
        ];
    }

    public function wantsTimeline(): bool
    {
        if ($this->has('post_to_timeline')) {
            return (bool) $this->input('post_to_timeline');
        }

        return (bool) $this->input('publish_to_timeline', true);
    }
}
