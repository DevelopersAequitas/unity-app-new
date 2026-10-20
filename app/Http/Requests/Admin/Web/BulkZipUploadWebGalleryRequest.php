<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Web;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BulkZipUploadWebGalleryRequest extends FormRequest
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
            'zip_file' => ['required', 'file', 'mimes:zip', 'max:102400'], // max 100MB
            'caption' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'circle' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'zip_file.required' => 'Please select a ZIP archive containing images.',
            'zip_file.file' => 'The uploaded file must be a valid file.',
            'zip_file.mimes' => 'The bulk file must be a .zip compressed archive.',
            'zip_file.max' => 'The ZIP archive size cannot exceed 100MB.',
        ];
    }
}
