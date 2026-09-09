<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StorePeerTestimonialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if (! $this->has('given_to_user_id') && ($this->has('to_peer_id') || $this->has('peer_user_id') || $this->has('peer_id'))) {
            $merge['given_to_user_id'] = $this->input('to_peer_id') ?? ($this->input('peer_user_id') ?? $this->input('peer_id'));
        }
        if (! $this->has('message') && ! $this->has('testimonial_text') && $this->has('content')) {
            $merge['message'] = $this->input('content');
        }
        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'given_to_user_id' => ['required', 'uuid', 'exists:users,id'],
            'message' => ['required_without:testimonial_text', 'nullable', 'string'],
            'testimonial_text' => ['required_without:message', 'nullable', 'string'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }
}
