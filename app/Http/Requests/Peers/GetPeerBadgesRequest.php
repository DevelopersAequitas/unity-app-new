<?php

declare(strict_types=1);

namespace App\Http\Requests\Peers;

use Illuminate\Foundation\Http\FormRequest;

class GetPeerBadgesRequest extends FormRequest
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
            'status' => ['nullable', 'string', 'in:earned,locked,all'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'string', 'max:50'],
        ];
    }
}
