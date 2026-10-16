<?php

declare(strict_types=1);

namespace App\Http\Requests\Event;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreEventFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // If event_id is in route but missing in payload, merge it
        if (! $this->has('event_id') && $this->route('eventId')) {
            $this->merge([
                'event_id' => $this->route('eventId'),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'uuid', 'exists:events,id'],
            'occurrence_id' => ['nullable', 'uuid'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'feedback' => ['nullable', 'string', 'max:2000'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'would_recommend' => ['nullable', 'boolean'],
            'notification_id' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.required' => 'The rating field is required and must be between 1 and 5.',
            'rating.integer' => 'The rating field is required and must be between 1 and 5.',
            'rating.min' => 'The rating field is required and must be between 1 and 5.',
            'rating.max' => 'The rating field is required and must be between 1 and 5.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 400));
    }
}
