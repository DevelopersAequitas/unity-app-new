<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Web;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreWebPodcastMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalize = function (?string $val): ?string {
            if ($val === null) {
                return null;
            }
            $val = trim($val);
            if ($val === '') {
                return null;
            }
            if (! preg_match('~^(?:f|ht)tps?://~i', $val)) {
                return 'https://'.$val;
            }

            return $val;
        };

        $merged = [];
        if ($this->has('youtube_url') && $this->input('youtube_url') !== null) {
            $merged['youtube_url'] = $normalize((string) $this->input('youtube_url'));
        }
        if ($this->has('spotify_url') && $this->input('spotify_url') !== null) {
            $merged['spotify_url'] = $normalize((string) $this->input('spotify_url'));
        }
        if ($this->has('apple_podcast_url') && $this->input('apple_podcast_url') !== null) {
            $merged['apple_podcast_url'] = $normalize((string) $this->input('apple_podcast_url'));
        }
        if ($this->has('external_url') && $this->input('external_url') !== null) {
            $merged['external_url'] = $normalize((string) $this->input('external_url'));
        }

        if (! empty($merged)) {
            $this->merge($merged);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:500'],
            'tag' => ['nullable', 'string', 'max:100'],
            'media_format' => ['nullable', 'string', 'max:100'],
            'duration' => ['nullable', 'string', 'max:50'],
            'youtube_url' => ['nullable', 'string', 'url', 'max:1000'],
            'spotify_url' => ['nullable', 'string', 'url', 'max:1000'],
            'apple_podcast_url' => ['nullable', 'string', 'url', 'max:1000'],
            'external_url' => ['nullable', 'string', 'url', 'max:1000'],
            'cover_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
            'cover_url' => ['nullable', 'string', 'max:1000'],
            'guest_name' => ['nullable', 'string', 'max:255'],
            'guest_title' => ['nullable', 'string', 'max:500'],
            'guest_avatar_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
            'guest_avatar_url' => ['nullable', 'string', 'max:1000'],
            'show_notes' => ['nullable'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Please provide an episode or feature title.',
            'youtube_url.url' => 'Please enter a valid YouTube link (e.g. https://www.youtube.com/watch?v=...).',
            'spotify_url.url' => 'Please enter a valid Spotify link (e.g. https://open.spotify.com/episode/...).',
            'cover_file.image' => 'Cover must be a valid image file (JPG, PNG, WebP).',
            'cover_file.max' => 'Cover image cannot exceed 10MB.',
            'guest_avatar_file.image' => 'Guest avatar must be a valid image file.',
        ];
    }
}
