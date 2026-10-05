<?php

namespace App\Http\Controllers\Api\V1\Admin\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\User;
use App\Services\Store\StoreWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStoreWalletController extends BaseApiController
{
    protected StoreWalletService $walletService;

    public function __construct(StoreWalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = User::query()->select([
            'id', 'first_name', 'last_name', 'display_name', 'email', 'phone',
            'coins_balance', 'wallet_state', 'wallet_lifetime_earned', 'wallet_lifetime_spent', 'wallet_version',
        ]);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ILIKE', "%{$search}%")
                    ->orWhere('last_name', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%")
                    ->orWhere('phone', 'ILIKE', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 20);
        $wallets = $query->paginate($perPage);

        return $this->success($wallets, 'Peer wallets retrieved');
    }

    public function show(string $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        $info = $this->walletService->getWalletInfo($user);
        $info['user'] = [
            'id' => $user->id,
            'name' => $user->display_name ?? ($user->first_name . ' ' . $user->last_name),
            'email' => $user->email,
            'phone' => $user->phone,
        ];

        return $this->success($info, 'Wallet details retrieved');
    }

    public function freeze(Request $request, string $userId): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:500']);
        $user = User::findOrFail($userId);

        $user->update([
            'wallet_state' => 'FROZEN',
            'wallet_frozen_at' => now(),
            'wallet_frozen_reason' => $request->input('reason'),
        ]);

        return $this->success($user, 'Wallet frozen successfully');
    }

    public function unfreeze(string $userId): JsonResponse
    {
        $user = User::findOrFail($userId);

        $user->update([
            'wallet_state' => 'ACTIVE',
            'wallet_frozen_at' => null,
            'wallet_frozen_reason' => null,
        ]);

        return $this->success($user, 'Wallet unfrozen successfully');
    }
}
