<?php

namespace App\Http\Controllers\Api\V1\Admin\Store;

use App\Constants\StoreErrorCodes;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Store\Admin\AdminBonusGrantRequest;
use App\Http\Requests\Store\Admin\AdminWalletAdjustmentRequest;
use App\Models\CoinsLedger;
use App\Models\Store\WalletAdjustmentRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminStoreWalletAdjustmentController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = WalletAdjustmentRequest::with(['user', 'requester', 'approver']);
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $perPage = (int) $request->input('per_page', 20);
        $adjustments = $query->orderBy('requested_at', 'desc')->paginate($perPage);

        return $this->success($adjustments, 'Wallet adjustment requests retrieved');
    }

    public function store(AdminWalletAdjustmentRequest $request): JsonResponse
    {
        $admin = $request->user();
        $data = $request->validated();

        $adj = WalletAdjustmentRequest::create([
            'user_id' => $data['user_id'],
            'requested_by' => $admin ? $admin->id : $data['user_id'],
            'adjustment_type' => $data['adjustment_type'],
            'coins' => $data['coins'],
            'bucket' => $data['bucket'],
            'reason' => $data['reason'],
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);

        return $this->success($adj, 'Wallet adjustment request created (pending maker-checker approval)', 201);
    }

    public function createBonusGrant(AdminBonusGrantRequest $request): JsonResponse
    {
        $admin = $request->user();
        $data = $request->validated();

        $adj = WalletAdjustmentRequest::create([
            'user_id' => $data['user_id'],
            'requested_by' => $admin ? $admin->id : $data['user_id'],
            'adjustment_type' => 'CREDIT',
            'coins' => $data['coins'],
            'bucket' => 'BONUS',
            'reason' => $data['reason'],
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);

        return $this->success($adj, 'Bonus coin grant created (pending maker-checker approval)', 201);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $admin = $request->user();
        $adj = WalletAdjustmentRequest::findOrFail($id);

        if ($adj->status !== 'PENDING') {
            return $this->error('Adjustment is not in pending state', 422);
        }

        // Maker-Checker validation: Admin cannot approve their own adjustment request
        if ($admin && $adj->requested_by === $admin->id) {
            return $this->error('Maker-Checker Violation: You cannot approve your own adjustment request', 403, [
                'code' => StoreErrorCodes::MAKER_CHECKER_VIOLATION,
            ]);
        }

        return DB::transaction(function () use ($adj, $admin) {
            $user = User::where('id', $adj->user_id)->lockForUpdate()->firstOrFail();

            $amount = $adj->adjustment_type === 'CREDIT' ? $adj->coins : -$adj->coins;
            $newBalance = max(0, (int) $user->coins_balance + $amount);

            $txId = (string) Str::uuid();
            $ledger = CoinsLedger::create([
                'transaction_id' => $txId,
                'user_id' => $user->id,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'bucket' => $adj->bucket,
                'reference_type' => 'WALLET_ADJUSTMENT',
                'reference_id' => $adj->id,
                'reference' => "Adjustment: {$adj->reason}",
                'created_by' => $admin ? $admin->id : $user->id,
                'created_at' => now(),
            ]);

            $user->update([
                'coins_balance' => $newBalance,
                'wallet_version' => ($user->wallet_version ?? 0) + 1,
            ]);

            $adj->update([
                'status' => 'APPROVED',
                'approved_by' => $admin ? $admin->id : null,
                'approved_at' => now(),
                'ledger_transaction_id' => $ledger->transaction_id,
            ]);

            return $this->success([
                'adjustment' => $adj,
                'ledger' => $ledger,
                'new_balance' => $newBalance,
            ], 'Wallet adjustment approved and executed successfully');
        });
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:500']);
        $adj = WalletAdjustmentRequest::findOrFail($id);

        $adj->update([
            'status' => 'REJECTED',
            'rejected_at' => now(),
            'rejection_reason' => $request->input('reason'),
        ]);

        return $this->success($adj, 'Wallet adjustment request rejected');
    }
}
