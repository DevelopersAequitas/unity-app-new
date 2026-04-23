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
            'message_type' => ['required', 'in:text,image,audio,file'],
            'message_text' => ['nullable', 'string', 'max:5000', 'required_if:message_type,text'],
            'file_id' => ['nullable', 'uuid', 'required_if:message_type,image,audio,file', 'exists:files,id'],
            'reply_to_message_id' => ['nullable', 'uuid', 'exists:leadership_group_messages,id'],
        ];
    }
}
