<?php

declare(strict_types=1);

namespace App\Http\Requests\Leader;

use Illuminate\Foundation\Http\FormRequest;

class LeaderUpdateRoleMatrixRequest extends FormRequest
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
            'role_id' => ['required', 'string'],
            'enabled_capabilities' => ['required', 'array'],
            'enabled_capabilities.*' => ['string'],
        ];
    }
}
