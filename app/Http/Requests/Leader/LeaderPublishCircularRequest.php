<?php

declare(strict_types=1);

namespace App\Http\Requests\Leader;

use Illuminate\Foundation\Http\FormRequest;

class LeaderPublishCircularRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'target_roles' => ['required', 'array', 'min:1'],
            'target_roles.*' => ['required', 'string'],
            'priority' => ['required', 'string', 'in:General,Important,Urgent,general,important,urgent'],
            'attachment_url' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
