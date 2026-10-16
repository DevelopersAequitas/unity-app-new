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
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'circle' => ['required', 'string', 'max:100'],
            'year' => ['required', 'string', 'max:20'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:10240'],
            'image_url' => ['nullable', 'string', 'max:1000'],
            'caption' => ['nullable', 'string', 'max:2000'],
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
            'title.required' => 'Please provide a title for the gallery photo.',
            'category.required' => 'Please select an event category.',
            'city.required' => 'Please select or enter a city.',
            'circle.required' => 'Please select or enter a circle.',
            'year.required' => 'Please select a year.',
            'image.image' => 'The uploaded file must be a valid image (JPG, PNG, WebP).',
            'image.max' => 'The image size cannot exceed 10MB.',
        ];
    }
}
