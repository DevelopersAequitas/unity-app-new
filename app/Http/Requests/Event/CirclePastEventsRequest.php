<?php

declare(strict_types=1);

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;

class CirclePastEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('circle_id') && $this->route('circle_id')) {
            $this->merge([
                'circle_id' => $this->route('circle_id'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'circle_id' => ['required', 'string', 'uuid'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
