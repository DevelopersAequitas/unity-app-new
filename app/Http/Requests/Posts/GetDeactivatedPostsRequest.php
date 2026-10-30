<?php

declare(strict_types=1);

namespace App\Http\Requests\Posts;

use Illuminate\Foundation\Http\FormRequest;

class GetDeactivatedPostsRequest extends FormRequest
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
            'user_id' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Resolve target user filter. If not explicitly specified, defaults to the authenticated user.
     */
    public function targetUserId(): ?string
    {
        $inputUserId = $this->query('user_id') ?: $this->input('user_id');

        if ($inputUserId !== null && $inputUserId !== '') {
            return (string) $inputUserId;
        }

        $user = $this->user('sanctum') ?: $this->user();

        return $user?->id ? (string) $user->id : null;
    }
}
