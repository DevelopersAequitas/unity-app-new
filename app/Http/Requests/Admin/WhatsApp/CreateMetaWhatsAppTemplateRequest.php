<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreateMetaWhatsAppTemplateRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'min:2',
                'max:512',
                'regex:/^[a-z0-9_]+$/',
            ],
            'category' => [
                'required',
                'string',
                'in:MARKETING,UTILITY,AUTHENTICATION',
            ],
            'language' => [
                'required',
                'string',
                'min:2',
                'max:15',
            ],
            'header_type' => [
                'nullable',
                'string',
                'in:NONE,TEXT,IMAGE,VIDEO,DOCUMENT',
            ],
            'header_text' => [
                'nullable',
                'string',
                'max:60',
                'required_if:header_type,TEXT',
            ],
            'header_image_file' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png',
                'max:5120',
            ],
            'header_video_file' => [
                'nullable',
                'file',
                'mimes:mp4,3gp',
                'max:16384',
            ],
            'header_document_file' => [
                'nullable',
                'file',
                'mimes:pdf',
                'max:10240',
            ],
            'header_handle' => [
                'nullable',
                'string',
            ],
            'body_text' => [
                'required',
                'string',
                'min:1',
                'max:1024',
            ],
            'footer_text' => [
                'nullable',
                'string',
                'max:60',
            ],
            'buttons' => [
                'nullable',
                'array',
                'max:3',
            ],
            'buttons.*.type' => [
                'required',
                'string',
                'in:QUICK_REPLY,URL,PHONE_NUMBER',
            ],
            'buttons.*.text' => [
                'required',
                'string',
                'max:25',
            ],
            'buttons.*.url' => [
                'nullable',
                'string',
                'url',
                'required_if:buttons.*.type,URL',
            ],
            'buttons.*.phone_number' => [
                'nullable',
                'string',
                'required_if:buttons.*.type,PHONE_NUMBER',
            ],
            'example_body_params' => [
                'nullable',
                'array',
            ],
            'example_body_params.*' => [
                'nullable',
                'string',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'Template name must be only lowercase letters, numbers, and underscores (e.g. membership_expiry_alert).',
            'category.in' => 'Category must be one of: MARKETING, UTILITY, or AUTHENTICATION.',
            'body_text.required' => 'Template body message content is required.',
            'header_image_file.mimes' => 'Sample header image must be a JPG, JPEG, or PNG file.',
            'header_image_file.max' => 'Sample header image size must not exceed 5MB.',
            'header_video_file.mimes' => 'Sample header video must be an MP4 or 3GP file.',
            'header_video_file.max' => 'Sample header video size must not exceed 16MB.',
            'header_document_file.mimes' => 'Sample header document must be a PDF file.',
            'header_document_file.max' => 'Sample header document size must not exceed 10MB.',
        ];
    }

    /**
     * Custom validation rules after standard rules.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $headerType = strtoupper((string) $this->input('header_type', 'NONE'));
            if ($headerType === 'IMAGE' && ! $this->hasFile('header_image_file') && empty($this->input('header_handle'))) {
                $validator->errors()->add('header_image_file', 'Please upload a sample image (JPG, JPEG, or PNG) for Meta template review and approval.');
            } elseif ($headerType === 'VIDEO' && ! $this->hasFile('header_video_file') && empty($this->input('header_handle'))) {
                $validator->errors()->add('header_video_file', 'Please upload a sample video (MP4) for Meta template review and approval.');
            } elseif ($headerType === 'DOCUMENT' && ! $this->hasFile('header_document_file') && empty($this->input('header_handle'))) {
                $validator->errors()->add('header_document_file', 'Please upload a sample PDF document for Meta template review and approval.');
            }

            // Check for invalid single curly braces like {1}, {2}
            $body = (string) $this->input('body_text', '');
            if (preg_match('/(?<!\{)\{(\d+)\}(?!\})/', $body, $matches)) {
                $validator->errors()->add(
                    'body_text',
                    "Invalid variable syntax '{$matches[0]}'. WhatsApp templates strictly require double curly braces like '{{{$matches[1]}}}' to avoid Meta approval rejection."
                );
            }
        });
    }
}
