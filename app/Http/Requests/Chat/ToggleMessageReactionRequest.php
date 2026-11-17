<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class ToggleMessageReactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'emoji' => ['required', 'string', 'max:32'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('emoji') && is_string($this->input('emoji'))) {
            $this->merge([
                'emoji' => trim($this->input('emoji')),
            ]);
        }
    }
}
