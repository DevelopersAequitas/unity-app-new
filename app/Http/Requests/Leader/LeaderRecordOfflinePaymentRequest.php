<?php

declare(strict_types=1);

namespace App\Http\Requests\Leader;

use Illuminate\Foundation\Http\FormRequest;

class LeaderRecordOfflinePaymentRequest extends FormRequest
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
            'peer_id' => ['required', 'string'],
            'circle_id' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_mode' => ['required', 'string', 'max:50'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'payment_date' => ['nullable', 'date'],
            'type' => ['nullable', 'string', 'max:100'],
        ];
    }
}
