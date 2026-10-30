<?php

declare(strict_types=1);

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class AiChatbotMessageRequest extends FormRequest
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
            'message' => ['nullable', 'string', 'max:4000', 'required_without_all:prompt,audio_base64'],
            'prompt' => ['nullable', 'string', 'max:4000', 'required_without_all:message,audio_base64'],
            'session_id' => ['nullable', 'string', 'max:64'],
            'audio_base64' => ['nullable', 'string'],
            'audio_mime_type' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function resolvedMessage(): string
    {
        $text = trim((string) ($this->input('message') ?: $this->input('prompt', '')));
        if ($text === '' && filled($this->input('audio_base64'))) {
            return '🎤 Voice Message';
        }

        return $text;
    }
}

