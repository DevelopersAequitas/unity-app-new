<?php

namespace App\Services\Store;

use App\Constants\StoreErrorCodes;
use App\Models\CoinsLedger;
use App\Models\Store\OrderPayment;
use App\Models\User;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreWalletService
{
    public function getWalletInfo(User $user): array
    {
        $earnedCredits = (int) CoinsLedger::where('user_id', $user->id)
            ->where('amount', '>', 0)
            ->where('bucket', 'EARNED')
            ->sum('amount');

        $bonusCredits = (int) CoinsLedger::where('user_id', $user->id)
            ->where('amount', '>', 0)
            ->where('bucket', 'BONUS')
            ->sum('amount');

        $totalSpent = (int) abs(CoinsLedger::where('user_id', $user->id)
            ->where('amount', '<', 0)
            ->sum('amount'));

        $balance = (int) ($user->coins_balance ?? 0);

        // Approximate available split based on historical credits & spend
        $availableBonus = max(0, min($balance, $bonusCredits));
        $availableEarned = max(0, $balance - $availableBonus);

        return [
            'balance' => $balance,
            'earned_balance' => $availableEarned,
            'bonus_balance' => $availableBonus,
            'lifetime_earned' => (int) ($user->wallet_lifetime_earned ?? $earnedCredits),
            'lifetime_spent' => (int) ($user->wallet_lifetime_spent ?? $totalSpent),
            'lifetime_purchased' => (int) ($user->wallet_lifetime_purchased ?? 0),
            'wallet_state' => (string) ($user->wallet_state ?? 'ACTIVE'),
            'wallet_version' => (int) ($user->wallet_version ?? 0),
        ];
    }

    public function getWalletStatus(User $user): array
    {
        $state = (string) ($user->wallet_state ?? 'ACTIVE');

        return [
            'wallet_state' => $state,
            'frozen' => $state === 'FROZEN',
            'frozen_at' => $user->wallet_frozen_at ? $user->wallet_frozen_at->toIso8601String() : null,
            'frozen_reason' => $user->wallet_frozen_reason,
        ];
    }

    public function getWalletSummary(User $user): array
    {
        $info = $this->getWalletInfo($user);

        $spentThisMonth = (int) abs(CoinsLedger::where('user_id', $user->id)
            ->where('amount', '<', 0)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('amount'));

        $earnedThisMonth = (int) CoinsLedger::where('user_id', $user->id)
            ->where('amount', '>', 0)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('amount');

        return [
            'current_balance' => $info['balance'],
            'earned' => $info['earned_balance'],
            'bonus' => $info['bonus_balance'],
            'spent_this_month' => $spentThisMonth,
            'earned_this_month' => $earnedThisMonth,
        ];
    }

    public function getLedger(User $user, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = CoinsLedger::where('user_id', $user->id);

        if (! empty($filters['bucket'])) {
            $query->where('bucket', $filters['bucket']);
        }

        if (! empty($filters['reference_type'])) {
            $query->where('reference_type', $filters['reference_type']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Determine Bonus-first, then Earned split for spending coins.
     */
    public function calculateSpendSplit(User $user, int $totalCoins): array
    {
        $walletInfo = $this->getWalletInfo($user);
        $availableBonus = $walletInfo['bonus_balance'];
        $availableEarned = $walletInfo['earned_balance'];
        $currentBalance = $walletInfo['balance'];

        if ($currentBalance < $totalCoins) {
            throw new Exception(StoreErrorCodes::INSUFFICIENT_COINS, 400);
        }

        $fromBonus = min($totalCoins, $availableBonus);
        $fromEarned = $totalCoins - $fromBonus;

        return [
            'bonus' => $fromBonus,
            'earned' => $fromEarned,
            'total' => $totalCoins,
        ];
    }

    /**
     * Debit coins atomically during order placement or renewal.
     * Must be called inside an active DB transaction with lock on User row.
     */
    public function executeSpendDebit(
        User $lockedUser,
        int $totalCoins,
        string $referenceType,
        string $referenceId,
        ?string $idempotencyKey = null,
        ?string $remark = null
    ): array {
        if ($lockedUser->wallet_state === 'FROZEN') {
            throw new Exception(StoreErrorCodes::WALLET_FROZEN, 403);
        }
        if ($lockedUser->wallet_state === 'CLOSED') {
            throw new Exception(StoreErrorCodes::WALLET_CLOSED, 403);
        }

        if ($lockedUser->coins_balance < $totalCoins) {
            throw new Exception(StoreErrorCodes::INSUFFICIENT_COINS, 400);
        }

        if ($idempotencyKey) {
            $existing = CoinsLedger::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return [
                    'already_processed' => true,
                    'ledger_id' => $existing->transaction_id,
                    'bonus_coins' => 0,
                    'earned_coins' => 0,
                ];
            }
        }

        $split = $this->calculateSpendSplit($lockedUser, $totalCoins);
        $bonusDebit = $split['bonus'];
        $earnedDebit = $split['earned'];

        $newBalance = $lockedUser->coins_balance - $totalCoins;
        $ledgerEntries = [];

        // 1. If Bonus coins debited
        if ($bonusDebit > 0) {
            $bonusTxId = (string) Str::uuid();
            $interBalance = $lockedUser->coins_balance - $bonusDebit;
            $ledgerEntries[] = CoinsLedger::create([
                'transaction_id' => $bonusTxId,
                'user_id' => $lockedUser->id,
                'amount' => -$bonusDebit,
                'balance_after' => $interBalance,
                'bucket' => 'BONUS',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey ? $idempotencyKey . '_BONUS' : null,
                'reference' => $remark ?? "Store purchase ({$referenceType})",
                'created_by' => $lockedUser->id,
                'created_at' => now(),
            ]);
        }

        // 2. If Earned coins debited
        if ($earnedDebit > 0) {
            $earnedTxId = (string) Str::uuid();
            $ledgerEntries[] = CoinsLedger::create([
                'transaction_id' => $earnedTxId,
                'user_id' => $lockedUser->id,
                'amount' => -$earnedDebit,
                'balance_after' => $newBalance,
                'bucket' => 'EARNED',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey ? $idempotencyKey . '_EARNED' : null,
                'reference' => $remark ?? "Store purchase ({$referenceType})",
                'created_by' => $lockedUser->id,
                'created_at' => now(),
            ]);
        }

        // Update User wallet cache
        $lockedUser->update([
            'coins_balance' => $newBalance,
            'wallet_lifetime_spent' => ($lockedUser->wallet_lifetime_spent ?? 0) + $totalCoins,
            'wallet_version' => ($lockedUser->wallet_version ?? 0) + 1,
        ]);

        return [
            'already_processed' => false,
            'bonus_coins' => $bonusDebit,
            'earned_coins' => $earnedDebit,
            'ledger_entries' => $ledgerEntries,
            'new_balance' => $newBalance,
        ];
    }

    /**
     * Restore exact Bonus / Earned split during refund.
     */
    public function executeRefundCredit(
        User $lockedUser,
        int $bonusCoins,
        int $earnedCoins,
        string $referenceType,
        string $referenceId,
        ?string $idempotencyKey = null,
        ?string $reason = null
    ): array {
        $totalRefund = $bonusCoins + $earnedCoins;
        if ($totalRefund <= 0) {
            return [];
        }

        $runningBalance = (int) $lockedUser->coins_balance;
        $ledgerEntries = [];

        if ($bonusCoins > 0) {
            $runningBalance += $bonusCoins;
            $ledgerEntries[] = CoinsLedger::create([
                'transaction_id' => (string) Str::uuid(),
                'user_id' => $lockedUser->id,
                'amount' => $bonusCoins,
                'balance_after' => $runningBalance,
                'bucket' => 'BONUS',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey ? $idempotencyKey . '_REFUND_BONUS' : null,
                'reference' => $reason ?? "Store refund ({$referenceType})",
                'created_by' => $lockedUser->id,
                'created_at' => now(),
            ]);
        }

        if ($earnedCoins > 0) {
            $runningBalance += $earnedCoins;
            $ledgerEntries[] = CoinsLedger::create([
                'transaction_id' => (string) Str::uuid(),
                'user_id' => $lockedUser->id,
                'amount' => $earnedCoins,
                'balance_after' => $runningBalance,
                'bucket' => 'EARNED',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey ? $idempotencyKey . '_REFUND_EARNED' : null,
                'reference' => $reason ?? "Store refund ({$referenceType})",
                'created_by' => $lockedUser->id,
                'created_at' => now(),
            ]);
        }

        $lockedUser->update([
            'coins_balance' => $runningBalance,
            'wallet_lifetime_spent' => max(0, ($lockedUser->wallet_lifetime_spent ?? 0) - $totalRefund),
            'wallet_version' => ($lockedUser->wallet_version ?? 0) + 1,
        ]);

        return [
            'refund_coins' => $totalRefund,
            'bonus_restored' => $bonusCoins,
            'earned_restored' => $earnedCoins,
            'new_balance' => $runningBalance,
            'ledger_entries' => $ledgerEntries,
        ];
    }
}
