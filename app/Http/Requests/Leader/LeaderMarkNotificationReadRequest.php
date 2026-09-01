<?php

declare(strict_types=1);

namespace App\Http\Requests\Leader;

use Illuminate\Foundation\Http\FormRequest;

class LeaderMarkNotificationReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
<<<<<<< HEAD
            'notification_ids' => ['nullable'],
            'notification_id' => ['nullable', 'string'],
            'id' => ['nullable', 'string'],
            'ids' => ['nullable', 'array'],
=======
            'notification_ids' => ['required', 'array'],
>>>>>>> be4dcd0b01c2f48201ccc286cb3a426a32738d5f
        ];
    }
}
