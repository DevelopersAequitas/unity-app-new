<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Web;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWebPodcastMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
