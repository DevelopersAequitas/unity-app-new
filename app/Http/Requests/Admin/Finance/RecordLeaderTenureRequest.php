<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Finance;

use Illuminate\Foundation\Http\FormRequest;

class RecordLeaderTenureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('scope_type') && is_string($this->input('scope_type'))) {
            $this->merge([
                'scope_type' => strtolower(trim($this->input('scope_type'))),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'role_code' => ['required', 'string', 'max:50'],
            'scope_type' => ['required', 'string', 'in:circle,district,industry,global'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'tenure_start_at' => ['nullable', 'date'],
            'expected_end_at' => ['nullable', 'date'],
        ];
    }
}
