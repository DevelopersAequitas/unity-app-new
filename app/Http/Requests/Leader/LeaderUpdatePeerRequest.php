<?php

declare(strict_types=1);

namespace App\Http\Requests\Leader;

use Illuminate\Foundation\Http\FormRequest;

class LeaderUpdatePeerRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'company' => ['sometimes', 'string', 'max:255'],
            'designation' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'email' => ['sometimes', 'email', 'max:255'],
            'hide_phone' => ['sometimes', 'boolean'],
            'hide_email' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', 'in:Active,active,Inactive,inactive,Pending,pending'],
            'intro_video_url' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
