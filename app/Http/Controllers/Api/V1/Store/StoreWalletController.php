<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\Store\StoreWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreWalletController extends BaseApiController
{
    protected StoreWalletService $walletService;

    public function __construct(StoreWalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    public function info(Request $request): JsonResponse
    {
        $user = $request->user();
        $info = $this->walletService->getWalletInfo($user);

        return $this->success($info, 'Wallet details retrieved');
    }

    public function ledger(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['bucket', 'reference_type', 'from', 'to']);
        $perPage = (int) $request->input('per_page', 20);

        $ledger = $this->walletService->getLedger($user, $filters, $perPage);

        return $this->success($ledger, 'Wallet ledger history retrieved');
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $summary = $this->walletService->getWalletSummary($user);

        return $this->success($summary, 'Wallet monthly summary retrieved');
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $status = $this->walletService->getWalletStatus($user);

        return $this->success($status, 'Wallet status retrieved');
    }
}
