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

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:fulfilled,closed,cancelled,in_progress,draft,published,expired'],
            'outcome_status' => ['nullable', 'string', 'in:formalised,in_discussion,parted_ways,no_response,deal_closed,yes_fully'],
            'approx_value' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'share_story' => ['nullable', 'boolean'],
            'anonymous_total' => ['nullable', 'boolean'],
        ];
    }
}
