<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Wallet\WalletTopupRequest;
use App\Http\Resources\WalletTransactionResource;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WalletController extends BaseApiController
{
    public function myTransactions(Request $request)
    {
        $authUser = $request->user();

        $query = WalletTransaction::where('user_id', $authUser->id);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $data = [
            'items' => WalletTransactionResource::collection($paginator),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];

        return $this->success($data);
    }

    public function topup(WalletTopupRequest $request)
    {
        $authUser = $request->user();
        $data = $request->validated();

        $amount = (float) $data['amount'];
        $currency = $data['currency'] ?? 'INR';

        $paymentRef = 'PGW_'.Str::upper(Str::random(16));
        $gatewayOrderId = 'DUMMY_GATEWAY_ORDER_'.Str::upper(Str::random(12));

        $metadata = $data['metadata'] ?? [];
        $metadata['currency'] = $currency;
        $metadata['gateway_order_id'] = $gatewayOrderId;

        $tx = new WalletTransaction;
        $tx->user_id = $authUser->id;
        $tx->amount = $amount;
        $tx->type = 'topup';
        $tx->payment_ref = $paymentRef;
        $tx->status = 'initiated';
        $tx->metadata = $metadata;
        $tx->save();

        $resource = new WalletTransactionResource($tx);

        return $this->success([
            'transaction' => $resource,
            'payment' => [
                'gateway' => 'dummy',
                'order_id' => $gatewayOrderId,
                'amount' => $amount,
                'currency' => $currency,
                'payment_ref' => $paymentRef,
            ],
        ], 'Topup transaction created', 201);
    }

    public function paymentWebhook(Request $request)
    {
        $paymentRef = $request->input('payment_ref');
        $incomingStatus = $request->input('status');

        if (! $paymentRef || ! in_array($incomingStatus, ['success', 'failed'], true)) {
            return $this->error('Invalid webhook payload', 422);
        }

        $tx = WalletTransaction::where('payment_ref', $paymentRef)->first();

        if (! $tx) {
            return $this->error('Transaction not found', 404);
        }

        if (in_array($tx->status, ['success', 'failed'], true)) {
            return $this->success(new WalletTransactionResource($tx), 'Transaction already finalized');
        }

        $tx->status = $incomingStatus === 'success' ? 'success' : 'failed';

        $metadata = $tx->metadata ?? [];
        $metadata['gateway_txn_id'] = $request->input('gateway_transaction_id');
        $metadata['gateway_status'] = $incomingStatus;
        $metadata['webhook_received_at'] = now()->toIso8601String();
        $metadata['raw_webhook'] = $request->all();

        $tx->metadata = $metadata;
        $tx->save();

        Log::info('Wallet payment webhook processed', [
            'payment_ref' => $paymentRef,
            'status' => $incomingStatus,
            'transaction_id' => $tx->id,
        ]);

        return $this->success(new WalletTransactionResource($tx), 'Transaction status updated');
    }
}
