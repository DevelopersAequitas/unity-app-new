<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ProcessWithdrawalActionRequest extends FormRequest
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
            'payout_method' => ['nullable', 'string', 'max:40'],
            'payout_reference_utr' => ['nullable', 'string', 'max:100'],
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
