<?php

namespace App\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;

class SubmitStoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => $this->trimValue($this->input('title')),
            'story' => $this->trimValue($this->input('story')),
            'short_description' => $this->trimValue($this->input('short_description')),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'story' => ['required', 'string'],
            'short_description' => ['nullable', 'string'],
            'cover_image' => ['nullable'], // can be file or UUID string
            'attachments' => ['nullable', 'array'],
        ];
    }

    private function trimValue(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }
}
