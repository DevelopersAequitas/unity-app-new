<?php

declare(strict_types=1);

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class SendChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required_without:template_name', 'nullable', 'string', 'max:4096'],
            'template_name' => ['nullable', 'string', 'max:255'],
            'language_code' => ['nullable', 'string', 'max:10'],
            'components' => ['nullable', 'array'],
            'media_url' => ['nullable', 'string', 'url'],
            'type' => ['nullable', 'string', 'in:TEXT,TEMPLATE,IMAGE,DOCUMENT,AUDIO,VIDEO,INTERACTIVE'],
        ];
    }
}
