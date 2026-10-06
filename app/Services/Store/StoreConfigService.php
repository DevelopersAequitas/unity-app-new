<?php

namespace App\Services\Store;

use App\Models\Store\StoreConfig;
use App\Models\User;

class StoreConfigService
{
    public function getStoreConfig(?User $user = null): array
    {
        $storeEnabled = (bool) StoreConfig::getValue('store_enabled', true);
        $minAppVersion = (string) StoreConfig::getValue('min_app_version_for_store', '1.0.0');
        $minDeliveryCoins = (int) StoreConfig::getValue('min_delivery_order_coins', 100000);
        $returnWindowDays = (int) StoreConfig::getValue('return_window_days', 7);
        $pickupHoldDays = (int) StoreConfig::getValue('pickup_hold_days', 7);

        $walletData = [
            'balance' => 0,
            'earned_coins' => 0,
            'bonus_coins' => 0,
            'wallet_state' => 'ACTIVE',
        ];

        if ($user) {
            $walletData['balance'] = (int) ($user->coins_balance ?? 0);
            $walletData['wallet_state'] = (string) ($user->wallet_state ?? 'ACTIVE');
            $walletData['earned_coins'] = (int) ($user->wallet_lifetime_earned ?? 0);
            $walletData['bonus_coins'] = max(0, $walletData['balance'] - $walletData['earned_coins']);
        }

        return [
            'store_enabled' => $storeEnabled,
            'minimum_app_version' => $minAppVersion,
            'current_app_version_supported' => true,
            'coin_balance' => $walletData['balance'],
            'earned_coins' => $walletData['earned_coins'],
            'bonus_coins' => $walletData['bonus_coins'],
            'delivery_minimum_coins' => $minDeliveryCoins,
            'pickup_available' => true,
            'return_window_days' => $returnWindowDays,
            'pickup_hold_days' => $pickupHoldDays,
        ];
    }
}
