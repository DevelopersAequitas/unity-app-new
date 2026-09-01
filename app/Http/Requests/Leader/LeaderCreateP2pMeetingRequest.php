<?php

declare(strict_types=1);

namespace App\Http\Requests\Leader;

use Illuminate\Foundation\Http\FormRequest;

class LeaderCreateP2pMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'peer_id' => ['required', 'string'],
            'meeting_date' => ['nullable', 'date'],
            'meeting_time' => ['nullable', 'string'],
            'meeting_place' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }
}
