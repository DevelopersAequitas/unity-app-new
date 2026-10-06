<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\CoinsLedger;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InternalCoinController extends BaseApiController
{
    public function credit(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'amount' => 'required|integer|min:1',
            'bucket' => 'nullable|string|in:EARNED,BONUS',
            'source_event_id' => 'required|string|max:255',
            'reference_type' => 'nullable|string|max:100',
            'reference_id' => 'nullable|uuid',
            'remark' => 'nullable|string|max:500',
        ]);

        $userId = $request->input('user_id');
        $amount = (int) $request->input('amount');
        $bucket = $request->input('bucket', 'EARNED');
        $sourceEventId = $request->input('source_event_id');
        $refType = $request->input('reference_type', 'ACTIVITY');
        $refId = $request->input('reference_id');
        $remark = $request->input('remark', 'Coin Credit');

        // Idempotency: source_event_id check
        $existing = CoinsLedger::where('idempotency_key', $sourceEventId)->first();
        if ($existing) {
            return $this->success($existing, 'Credit already processed (idempotent)');
        }

        return DB::transaction(function () use ($userId, $amount, $bucket, $sourceEventId, $refType, $refId, $remark) {
            $user = User::where('id', $userId)->lockForUpdate()->firstOrFail();

            $newBalance = (int) $user->coins_balance + $amount;

            $txId = (string) Str::uuid();
            $ledger = CoinsLedger::create([
                'transaction_id' => $txId,
                'user_id' => $user->id,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'bucket' => $bucket,
                'reference_type' => $refType,
                'reference_id' => $refId,
                'idempotency_key' => $sourceEventId,
                'reference' => $remark,
                'created_by' => $user->id,
                'created_at' => now(),
            ]);

            $user->update([
                'coins_balance' => $newBalance,
                'wallet_lifetime_earned' => ($user->wallet_lifetime_earned ?? 0) + $amount,
                'wallet_version' => ($user->wallet_version ?? 0) + 1,
            ]);

            return $this->success($ledger, 'Coins credited successfully', 201);
        });
    }

    public function reverse(Request $request): JsonResponse
    {
        $request->validate([
            'source_event_id' => 'required|string',
            'reason' => 'required|string|max:500',
        ]);

        $sourceEventId = $request->input('source_event_id');
        $reason = $request->input('reason');

        $originalLedger = CoinsLedger::where('idempotency_key', $sourceEventId)->first();
        if (! $originalLedger) {
            return $this->error('Original transaction not found', 404);
        }

        $reversalKey = 'REV_'.$sourceEventId;
        $existingRev = CoinsLedger::where('idempotency_key', $reversalKey)->first();
        if ($existingRev) {
            return $this->success($existingRev, 'Reversal already processed (idempotent)');
        }

        return DB::transaction(function () use ($originalLedger, $reversalKey, $reason) {
            $user = User::where('id', $originalLedger->user_id)->lockForUpdate()->firstOrFail();

            $reversalAmount = -$originalLedger->amount;
            $newBalance = max(0, (int) $user->coins_balance + $reversalAmount);

            $ledger = CoinsLedger::create([
                'transaction_id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'amount' => $reversalAmount,
                'balance_after' => $newBalance,
                'bucket' => $originalLedger->bucket,
                'reference_type' => 'REVERSAL',
                'reference_id' => $originalLedger->transaction_id,
                'idempotency_key' => $reversalKey,
                'reference' => "Reversal: {$reason}",
                'created_by' => $user->id,
                'created_at' => now(),
            ]);

            $user->update([
                'coins_balance' => $newBalance,
                'wallet_version' => ($user->wallet_version ?? 0) + 1,
            ]);

            return $this->success($ledger, 'Coins reversed successfully');
        });
    }
}
