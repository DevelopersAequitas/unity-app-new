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
            'message' => ['nullable', 'string', 'max:4000', 'required_without:prompt'],
            'prompt' => ['nullable', 'string', 'max:4000', 'required_without:message'],
            'session_id' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function resolvedMessage(): string
    {
        return trim((string) ($this->input('message') ?: $this->input('prompt', '')));
    }
}

