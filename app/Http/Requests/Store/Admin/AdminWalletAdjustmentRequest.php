<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminWalletAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|uuid|exists:users,id',
            'adjustment_type' => 'required|string|in:CREDIT,DEBIT',
            'coins' => 'required|integer|min:1',
            'bucket' => 'required|string|in:EARNED,BONUS',
            'reason' => 'required|string|max:500',
        ];
    }
}
