<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StoreWithdrawalRequest extends FormRequest
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
            'wallet_id' => ['required', 'uuid', 'exists:finance_wallets,id'],
            'leader_user_id' => ['required', 'uuid', 'exists:users,id'],
            'leader_tenure_id' => ['nullable', 'uuid', 'exists:finance_leader_tenures,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'purpose' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payout_method' => ['nullable', 'string', 'in:bank_transfer,upi,cheque,expense_reimbursement'],
        ];
    }
}
