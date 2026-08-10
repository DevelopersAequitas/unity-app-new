<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SendSupportTicketEmailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $rules = [
            'action' => ['nullable', 'string', 'in:send_email,send_notification,send_both'],
            'status' => ['nullable', 'string', 'in:open,in_progress,resolved,closed'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,pdf,doc,docx,xls,xlsx,zip,rar,txt'],
        ];

        if ($this->input('action') === 'send_notification') {
            $rules['subject'] = ['nullable', 'string', 'max:255'];
            $rules['message'] = ['nullable', 'string', 'max:10000'];
        } else {
            $rules['subject'] = ['required', 'string', 'max:255'];
            $rules['message'] = ['required', 'string', 'max:10000'];
        }

        return $rules;
    }
}
