<?php

namespace App\Services\Store;

use App\Models\Store\NotificationEvent;
use App\Models\Store\Order;
use App\Models\Store\OrderPayment;
use App\Models\Store\Refund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreRefundService
{
    protected StoreWalletService $walletService;

    public function __construct(StoreWalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    public function processOrderRefund(
        Order $order,
        User $user,
        int $refundCoins,
        string $reasonCode,
        ?string $reasonDetail = null,
        ?string $returnId = null,
        ?string $idempotencyKey = null,
        ?User $adminUser = null
    ): Refund {
        return DB::transaction(function () use (
            $order,
            $user,
            $refundCoins,
            $reasonCode,
            $reasonDetail,
            $returnId,
            $idempotencyKey,
            $adminUser
        ) {
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ($idempotencyKey) {
                $existing = Refund::whereHas('ledgerTransaction', function ($q) use ($idempotencyKey) {
                    $q->where('idempotency_key', 'LIKE', $idempotencyKey.'%');
                })->first();

                if ($existing) {
                    return $existing;
                }
            }

            // Read original payments split
            $payments = OrderPayment::where('order_id', $lockedOrder->id)->get();
            $totalOriginalPaid = $payments->sum('coins');

            $originalBonus = (int) $payments->where('bucket', 'BONUS')->sum('coins');
            $originalEarned = (int) $payments->where('bucket', 'EARNED')->sum('coins');

            // Calculate proportional split for refund
            $bonusRatio = $totalOriginalPaid > 0 ? ($originalBonus / $totalOriginalPaid) : 0;
            $refundBonus = (int) round($refundCoins * $bonusRatio);
            $refundEarned = $refundCoins - $refundBonus;

            $refundNo = 'REF-'.strtoupper(Str::random(10));
            $refundId = (string) Str::uuid();

            // Execute Refund Credit in Ledger & Balance
            $refundResult = $this->walletService->executeRefundCredit(
                $lockedUser,
                $refundBonus,
                $refundEarned,
                'REFUND',
                $refundId,
                $idempotencyKey,
                "Refund for Order #{$lockedOrder->order_no}: ".($reasonDetail ?? $reasonCode)
            );

            $primaryLedgerId = ! empty($refundResult['ledger_entries']) ? $refundResult['ledger_entries'][0]->transaction_id : null;

            $refund = Refund::create([
                'id' => $refundId,
                'refund_no' => $refundNo,
                'order_id' => $lockedOrder->id,
                'return_id' => $returnId,
                'user_id' => $lockedUser->id,
                'refund_coins' => $refundCoins,
                'reason' => $reasonCode.($reasonDetail ? ": {$reasonDetail}" : ''),
                'status' => 'COMPLETED',
                'processed_by' => $adminUser ? $adminUser->id : null,
                'processed_at' => now(),
                'ledger_transaction_id' => $primaryLedgerId,
                'created_at' => now(),
            ]);

            // Create Notification Event
            NotificationEvent::create([
                'event_key' => 'refund.completed',
                'user_id' => $lockedUser->id,
                'reference_type' => 'REFUND',
                'reference_id' => $refund->id,
                'payload' => [
                    'refund_no' => $refundNo,
                    'order_no' => $lockedOrder->order_no,
                    'refund_coins' => $refundCoins,
                    'bonus_restored' => $refundBonus,
                    'earned_restored' => $refundEarned,
                ],
                'status' => 'PENDING',
                'available_at' => now(),
                'created_at' => now(),
            ]);

            return $refund;
        });
    }
}
