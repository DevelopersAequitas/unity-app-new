<?php

namespace App\Http\Requests\CircleChat;

use App\Models\CircleChatMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SendCircleChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message_type' => ['required', 'in:text,image,video'],
            'message_text' => ['nullable', 'string', 'required_if:message_type,text'],
            'attachment' => ['nullable', 'file', 'required_if:message_type,image,video', 'max:102400'],
            'reply_to_message_id' => ['nullable', 'uuid', 'exists:circle_chat_messages,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = (string) $this->input('message_type');
            $file = $this->file('attachment');

            if (in_array($type, ['image', 'video'], true) && $file) {
                $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType());

                $imageMimes = ['image/jpeg', 'image/png', 'image/webp'];
                $videoMimes = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska'];

                if ($type === 'image' && ! in_array($mime, $imageMimes, true)) {
                    $validator->errors()->add('attachment', 'The attachment must be a jpg, jpeg, png, or webp image.');
                }

                if ($type === 'video' && ! in_array($mime, $videoMimes, true)) {
                    $validator->errors()->add('attachment', 'The attachment must be mp4, mov, avi, or mkv video.');
                }
            }

            if ($replyToId = $this->input('reply_to_message_id')) {
                $circleId = (string) $this->route('circle');

                $belongsToCircle = CircleChatMessage::query()
                    ->where('id', $replyToId)
                    ->where('circle_id', $circleId)
                    ->exists();

                if (! $belongsToCircle) {
                    $validator->errors()->add('reply_to_message_id', 'The reply message must belong to this circle.');
                }
            }
        });
    }
}
