<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\WhatsApp;

use App\Services\WhatsApp\MetaWhatsAppService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Validator;

class SendWhatsAppTestMessageRequest extends FormRequest
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
            'recipient' => ['required', 'string', 'min:8', 'max:20'],
            'template_name' => ['required', 'string'],
            'language' => ['nullable', 'string', 'max:15'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['nullable', 'string'],
            'header_image_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'header_image_url' => ['nullable', 'url', 'max:2048'],
            'header_video_file' => ['nullable', 'file', 'mimes:mp4,3gp', 'max:16384'],
            'header_video_url' => ['nullable', 'url', 'max:2048'],
            'header_document_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'header_document_url' => ['nullable', 'url', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $recipient = (string) ($validator->getData()['recipient'] ?? $this->input('recipient'));
            $allowedRecipients = (array) Config::get('whatsapp.testing.allowed_recipients', ['+916353025164', '+917041151714']);
            if (empty($allowedRecipients)) {
                $allowedRecipients = [(string) Config::get('whatsapp.testing.recipient', '+916353025164')];
            }
            $isRestricted = (bool) Config::get('whatsapp.testing.restrict_to_test_recipient', false);

            if ($isRestricted) {
                $normalizedInput = MetaWhatsAppService::normalizePhone($recipient);
                $normalizedAllowed = array_map([MetaWhatsAppService::class, 'normalizePhone'], $allowedRecipients);

                if (! in_array($normalizedInput, $normalizedAllowed, true)) {
                    $allowedList = implode(', ', $allowedRecipients);
                    $validator->errors()->add(
                        'recipient',
                        "Local WhatsApp testing is restricted to the configured test recipients: {$allowedList}. Messages cannot be sent to {$recipient} during development."
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recipient.required' => 'Recipient mobile number is required.',
            'template_name.required' => 'Please select a WhatsApp template to send.',
        ];
    }
}
