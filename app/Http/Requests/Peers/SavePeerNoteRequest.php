<?php

declare(strict_types=1);

namespace App\Http\Requests\Peers;

use Illuminate\Foundation\Http\FormRequest;

class SavePeerNoteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->exists('note') && $this->exists('notes')) {
            $this->merge([
                'note' => $this->input('notes'),
            ]);
        }
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:5000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'source' => ['sometimes', 'nullable', 'string', 'in:profile,speed_networking'],
        ];
    }
}
