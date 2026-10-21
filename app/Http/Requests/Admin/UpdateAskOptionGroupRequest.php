<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAskOptionGroupRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'input_type' => ['required', 'string', 'in:multi_select,single_select,text'],
            'flows' => ['nullable', 'array'],
            'flows.*' => ['string', 'in:collaboration,referral,help'],
        ];
    }
}
