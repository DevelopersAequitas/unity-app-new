<?php

namespace App\Http\Requests\Leadership;

use App\Models\LeadershipGroupMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SendLeadershipMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $messageType = $this->input('message_type');

        if (! $messageType) {
            $messageType = 'text';

            if ($this->hasFile('attachment')) {
                $mime = (string) ($this->file('attachment')?->getMimeType() ?: $this->file('attachment')?->getClientMimeType());

                if (str_starts_with($mime, 'image/')) {
                    $messageType = 'image';
                } elseif (str_starts_with($mime, 'audio/')) {
                    $messageType = 'audio';
                } elseif (str_starts_with($mime, 'video/')) {
                    $messageType = 'video';
                } else {
                    $messageType = 'file';
                }
            }
        }

        $this->merge([
            'message_type' => $messageType,
        ]);
    }

    public function rules(): array
    {
        return [
            'message_type' => ['nullable', 'in:text,image,audio,video,file'],
            'message_text' => ['nullable', 'string', 'max:5000', 'required_without:attachment'],
            'attachment' => ['nullable', 'file', 'max:102400', 'required_without:message_text'],
            'reply_to_message_id' => ['nullable', 'uuid', 'exists:leadership_group_messages,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($replyToId = $this->input('reply_to_message_id')) {
                $circleId = (string) $this->route('circle');

                $belongsToCircle = LeadershipGroupMessage::query()
                    ->where('id', $replyToId)
                    ->where('circle_id', $circleId)
                    ->whereNull('deleted_at')
                    ->exists();

                if (! $belongsToCircle) {
                    $validator->errors()->add('reply_to_message_id', 'The reply message must belong to this circle.');
                }
            }
        });
    }
}
