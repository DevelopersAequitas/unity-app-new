<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMetaWhatsAppTemplateRequest extends FormRequest
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
            'category' => [
                'nullable',
                'string',
                'in:MARKETING,UTILITY,AUTHENTICATION',
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
            ],
            'header_image_file' => [
                'nullable',
                'file',
                'image',
                'mimes:jpeg,png,jpg',
                'max:5120',
            ],
            'header_video_file' => [
                'nullable',
                'file',
                'mimetypes:video/mp4,video/3gpp',
                'max:16384',
            ],
            'header_document_file' => [
                'nullable',
                'file',
                'mimes:pdf',
                'max:10240',
            ],
            'existing_header_handle' => [
                'nullable',
                'string',
            ],
            'body_text' => [
                'required',
                'string',
                'max:1024',
            ],
            'body_sample' => [
                'nullable',
                'string',
                'max:255',
            ],
            'footer_text' => [
                'nullable',
                'string',
                'max:60',
            ],
            'button_type' => [
                'nullable',
                'string',
                'in:NONE,QUICK_REPLY,CALL_TO_ACTION',
            ],
            'buttons' => [
                'nullable',
                'array',
            ],
            'buttons.*.type' => [
                'nullable',
                'string',
                'in:QUICK_REPLY,URL,PHONE_NUMBER',
            ],
            'buttons.*.text' => [
                'nullable',
                'string',
                'max:25',
            ],
            'buttons.*.url' => [
                'nullable',
                'url',
                'max:2048',
            ],
            'buttons.*.phone_number' => [
                'nullable',
                'string',
                'max:20',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body_text.required' => 'The WhatsApp template body text is required.',
            'body_text.max' => 'Body text must not exceed 1024 characters.',
            'header_text.max' => 'Header text cannot exceed 60 characters.',
            'footer_text.max' => 'Footer text cannot exceed 60 characters.',
        ];
    }
}
