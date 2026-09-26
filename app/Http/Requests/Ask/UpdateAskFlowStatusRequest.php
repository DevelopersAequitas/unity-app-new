<?php

declare(strict_types=1);

namespace App\Http\Requests\Ask;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAskFlowStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status_id' => ['nullable'],
            'status_label' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'outcome_status' => ['nullable', 'string', 'max:255'],
            'approx_value' => ['nullable'],
            'share_story' => ['nullable', 'boolean'],
            'anonymous_total' => ['nullable', 'boolean'],
        ];
    }
}
