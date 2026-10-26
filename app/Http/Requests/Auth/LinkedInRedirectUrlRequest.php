<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LinkedInRedirectUrlRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'redirect_uri' => ['nullable', 'string', 'url'],
            'state' => ['nullable', 'string', 'max:255'],
            'redirect' => ['nullable', 'boolean'],
        ];
    }
}
