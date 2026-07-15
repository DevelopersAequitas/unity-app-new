<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTutorialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $url = $this->input('youtube_url');
        if (is_string($url)) {
            $this->merge([
                'video_id' => $this->extractVideoId($url),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'youtube_url' => ['required', 'string', 'url'],
            'video_id' => ['required', 'string', 'size:11', 'unique:tutorials,video_id'],
        ];
    }

    public function messages(): array
    {
        return [
            'video_id.required' => 'Could not extract a valid YouTube video ID from the provided URL.',
            'video_id.size' => 'The extracted YouTube video ID must be exactly 11 characters.',
            'video_id.unique' => 'This YouTube video has already been added.',
        ];
    }

    private function extractVideoId(string $url): ?string
    {
        // 1. Matches youtu.be/ID
        if (preg_match('/youtu\.be\/([^#?&]+)/', $url, $matches)) {
            return $matches[1];
        }

        // 2. Matches youtube.com/watch?v=ID
        if (preg_match('/[?&]v=([^#?&]+)/', $url, $matches)) {
            return $matches[1];
        }

        // 3. Matches youtube.com/shorts/ID or embed/ID or v/ID
        if (preg_match('/youtube\.com\/(?:shorts|embed|v)\/([^#?&]+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
