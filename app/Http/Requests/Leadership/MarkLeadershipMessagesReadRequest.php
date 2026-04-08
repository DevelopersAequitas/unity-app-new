<?php

namespace App\Http\Requests\Leadership;

use Illuminate\Foundation\Http\FormRequest;

class MarkLeadershipMessagesReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message_ids' => ['required', 'array', 'min:1'],
            'message_ids.*' => ['required', 'uuid', 'distinct', 'exists:leadership_group_messages,id'],
        ];
    }
}
