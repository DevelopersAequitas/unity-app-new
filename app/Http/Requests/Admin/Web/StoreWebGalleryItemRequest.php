<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Web;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreWebGalleryItemRequest extends FormRequest
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
            'image' => ['required_without:image_url', 'nullable', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:10240'],
            'image_url' => ['required_without:image', 'nullable', 'string', 'max:1000'],
            'caption' => ['nullable', 'string', 'max:2000'],
            'title' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'circle' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'string', 'max:20'],
            'gallery_id' => ['nullable', 'string', 'uuid'],
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
            'image.required_without' => 'Please choose an image file or enter an image URL.',
            'image_url.required_without' => 'Please choose an image file or enter an image URL.',
            'image.image' => 'The uploaded file must be a valid image (JPG, PNG, WebP).',
            'image.max' => 'The image size cannot exceed 10MB.',
        ];
    }
}
