<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Web;

use Illuminate\Foundation\Http\FormRequest;

class StoreWebSuccessStoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'person_name' => ['required', 'string', 'max:150'],
            'designation' => ['nullable', 'string', 'max:150'],
            'company' => ['nullable', 'string', 'max:150'],
            'story_title' => ['nullable', 'string', 'max:255'],
            'quote' => ['nullable', 'string', 'max:3000'],
            'youtube_url' => ['required', 'string', 'url'],
            'custom_cover_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'], // up to 10MB
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'person_name.required' => 'Please provide the name of the person or member.',
            'youtube_url.required' => 'A valid YouTube video link is required.',
            'youtube_url.url' => 'Please enter a valid YouTube URL (e.g. https://www.youtube.com/watch?v=...).',
            'custom_cover_image.image' => 'The cover must be a valid image file (JPG, PNG, WebP).',
            'custom_cover_image.max' => 'The cover image size must not exceed 10MB.',
        ];
    }
}
