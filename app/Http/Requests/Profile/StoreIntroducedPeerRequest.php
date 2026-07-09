<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreIntroducedPeerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'peer_id' => [
                'required',
                'uuid',
                'exists:users,id',
                $userId ? 'not_in:'.$userId : '',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'peer_id.not_in' => 'You cannot introduce yourself.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $validator->errors()->first('peer_id') ?: 'Validation error.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
