<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StorePeerTestimonialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
