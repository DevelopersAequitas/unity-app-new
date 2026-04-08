<?php

namespace App\Http\Requests\Leadership;

use Illuminate\Foundation\Http\FormRequest;

class SendLeadershipMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message_type' => ['required', 'in:text'],
            'message_text' => ['required', 'string', 'max:5000'],
            'reply_to_message_id' => ['nullable', 'uuid', 'exists:leadership_group_messages,id'],
        ];
    }
}
