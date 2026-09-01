<?php

declare(strict_types=1);

namespace App\Http\Requests\Leader;

use Illuminate\Foundation\Http\FormRequest;

class LeaderSendWishRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:birthday,anniversary'],
            'message' => ['required', 'string', 'max:1000'],
        ];
    }
}
