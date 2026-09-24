<?php

declare(strict_types=1);

namespace App\Http\Requests\Ask;

use Illuminate\Foundation\Http\FormRequest;

class CreateAskDraftRequest extends FormRequest
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
            'flow_id' => ['required_without:flow', 'string'],
            'flow' => ['sometimes', 'string'],
            'type_id' => ['required_without:type', 'string'],
            'type' => ['sometimes', 'string'],
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }
}
