<?php

declare(strict_types=1);

namespace App\Http\Requests\Ask;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $status = $this->input('status');
        $statusId = $this->input('status_id');
        $statusLabel = $this->input('status_label');

        if (! $status && $statusId !== null) {
            $mapped = match ((string) $statusId) {
                '4', '5' => 'fulfilled',
                '6', '7' => 'closed',
                default => 'published',
            };
            $this->merge(['status' => $mapped]);
        } elseif (! $status && $statusLabel) {
            $normalized = strtolower(trim((string) $statusLabel));
            $mapped = match ($normalized) {
                'got the business', 'deal closed', 'fulfilled', 'got things done', 'completed' => 'fulfilled',
                'did not get the business', 'declined', 'closed', 'rejected', 'not a good fit' => 'closed',
                default => 'published',
            };
            $this->merge(['status' => $mapped]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required_without_all:status_id,status_label', 'nullable', 'string'],
            'status_id' => ['nullable'],
            'status_label' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'outcome_status' => ['nullable', 'string', 'max:255'],
            'approx_value' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'share_story' => ['nullable', 'boolean'],
            'anonymous_total' => ['nullable', 'boolean'],
        ];
    }
}
