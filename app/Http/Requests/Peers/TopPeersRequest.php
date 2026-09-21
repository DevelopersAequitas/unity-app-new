<?php

declare(strict_types=1);

namespace App\Http\Requests\Peers;

use Illuminate\Foundation\Http\FormRequest;

class TopPeersRequest extends FormRequest
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
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'circle_id' => ['nullable', 'string'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'from_at' => ['nullable', 'date'],
            'to_at' => ['nullable', 'date'],
            'type' => ['nullable', 'string', 'in:given,received,all,initiated,attended'],
            'sort_by' => ['nullable', 'string', 'in:count,amount'],
            'search' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }
}
