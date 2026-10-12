<?php

use App\Http\Controllers\Api\V1\Store\AddressController;
use App\Http\Controllers\Api\V1\Store\CartController;
use App\Http\Controllers\Api\V1\Store\CheckoutController;
use App\Http\Controllers\Api\V1\Store\DigitalLibraryController;
use App\Http\Controllers\Api\V1\Store\InternalCoinController;
use App\Http\Controllers\Api\V1\Store\MembershipController;
use App\Http\Controllers\Api\V1\Store\OrderController;
use App\Http\Controllers\Api\V1\Store\ReturnController;
use App\Http\Controllers\Api\V1\Store\ServiceabilityController;
use App\Http\Controllers\Api\V1\Store\StoreCatalogController;
use App\Http\Controllers\Api\V1\Store\StoreConfigController;
use App\Http\Controllers\Api\V1\Store\StoreNotificationController;
use App\Http\Controllers\Api\V1\Store\StorePolicyController;
use App\Http\Controllers\Api\V1\Store\StoreSupportController;
use App\Http\Controllers\Api\V1\Store\StoreWalletController;
use App\Http\Controllers\Api\V1\Store\StoreWebhookController;
use App\Http\Controllers\Api\V1\Store\WishlistController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Peers Store & Coin Wallet API Routes (Mobile & User APIs)
|--------------------------------------------------------------------------
*/

// Public / Authenticated Store Shell & Catalog
Route::prefix('v1')->group(function () {
    // Webhooks (Public with signature/provider validation)
    Route::prefix('webhooks')->group(function () {
        Route::post('courier/tracking', [StoreWebhookController::class, 'courierTracking']);
        Route::post('whatsapp/status', [StoreWebhookController::class, 'whatsappStatus']);
        Route::post('email/status', [StoreWebhookController::class, 'emailStatus']);
        Route::post('sms/status', [StoreWebhookController::class, 'smsStatus']);
    });

    // Policies
    Route::prefix('store/policies')->group(function () {
        Route::get('/', [StorePolicyController::class, 'index']);
        Route::get('/{key}', [StorePolicyController::class, 'show']);
    });

    // Public / App Store Config & Catalog Browsing
    Route::prefix('store')->group(function () {
        Route::get('config', [StoreConfigController::class, 'show']);
        Route::get('banners', [StoreConfigController::class, 'banners']);
        Route::get('categories', [StoreCatalogController::class, 'categories']);
        Route::get('products', [StoreCatalogController::class, 'products']);
        Route::get('products/featured', [StoreCatalogController::class, 'featured']);
        Route::get('products/{id}', [StoreCatalogController::class, 'show'])->whereUuid('id');
        Route::get('search', [StoreCatalogController::class, 'search']);
    });

    // Authenticated Peer Routes
    Route::middleware(['auth:sanctum'])->group(function () {
        // Wallet APIs
        Route::prefix('wallet')->group(function () {
            Route::get('/', [StoreWalletController::class, 'info']);
            Route::get('ledger', [StoreWalletController::class, 'ledger']);
            Route::get('summary', [StoreWalletController::class, 'summary']);
            Route::get('status', [StoreWalletController::class, 'status']);
        });

        // Cart APIs
        $cartGroup = function () {
            Route::get('/', [CartController::class, 'index']);
            Route::post('items', [CartController::class, 'addItem']);
            Route::patch('items/{id}', [CartController::class, 'updateItem'])->whereUuid('id');
            Route::delete('items/{id}', [CartController::class, 'removeItem'])->whereUuid('id');
            Route::post('validate', [CartController::class, 'validateCart']);
        };
        Route::prefix('cart')->group($cartGroup);
        Route::prefix('store/cart')->group($cartGroup);

        // Wishlist APIs
        $wishlistGroup = function () {
            Route::get('/', [WishlistController::class, 'index']);
            Route::post('/', [WishlistController::class, 'addItem']);
            Route::post('toggle', [WishlistController::class, 'toggle']);
            Route::delete('{id}', [WishlistController::class, 'removeItem'])->whereUuid('id');
            Route::delete('product/{productId}', [WishlistController::class, 'removeByProduct'])->whereUuid('productId');
            Route::get('check/{productId}', [WishlistController::class, 'check'])->whereUuid('productId');
            Route::post('{id}/move-to-cart', [WishlistController::class, 'moveToCart'])->whereUuid('id');
        };
        Route::prefix('wishlist')->group($wishlistGroup);
        Route::prefix('store/wishlist')->group($wishlistGroup);

        // Address APIs
        $addressGroup = function () {
            Route::get('/', [AddressController::class, 'index']);
            Route::post('/', [AddressController::class, 'store']);
            Route::get('/{id}', [AddressController::class, 'show'])->whereUuid('id');
            Route::put('/{id}', [AddressController::class, 'update'])->whereUuid('id');
            Route::delete('/{id}', [AddressController::class, 'destroy'])->whereUuid('id');
        };
        Route::prefix('addresses')->group($addressGroup);
        Route::prefix('store/addresses')->group($addressGroup);

        // Serviceability & Pickup
        Route::post('serviceability/check', [ServiceabilityController::class, 'check']);
        Route::post('store/serviceability/check', [ServiceabilityController::class, 'check']);
        Route::get('pickup-points', [ServiceabilityController::class, 'pickupPoints']);
        Route::get('store/pickup-points', [ServiceabilityController::class, 'pickupPoints']);
        Route::get('pickup-points/{id}', [ServiceabilityController::class, 'pickupPointDetails'])->whereUuid('id');
        Route::get('store/pickup-points/{id}', [ServiceabilityController::class, 'pickupPointDetails'])->whereUuid('id');

        // Checkout & OTP
        $checkoutGroup = function () {
            Route::post('quote', [CheckoutController::class, 'createQuote']);
            Route::post('otp/send', [CheckoutController::class, 'sendOtp']);
            Route::post('otp/verify', [CheckoutController::class, 'verifyOtp']);
        };
        Route::prefix('checkout')->group($checkoutGroup);
        Route::prefix('store/checkout')->group($checkoutGroup);

        // Orders & Tracking & Cancel
        $orderGroup = function () {
            Route::get('/', [OrderController::class, 'index']);
            Route::post('/', [OrderController::class, 'placeOrder']);
            Route::get('/{id}', [OrderController::class, 'show'])->whereUuid('id');
            Route::post('/{id}/cancel', [OrderController::class, 'cancel'])->whereUuid('id');
            Route::get('/{id}/status-history', [OrderController::class, 'statusHistory'])->whereUuid('id');
            Route::get('/{id}/receipt', [OrderController::class, 'receipt'])->whereUuid('id');
            Route::get('/{id}/slip', [OrderController::class, 'slip'])->whereUuid('id');
            Route::get('/{id}/tracking', [OrderController::class, 'tracking'])->whereUuid('id');
            Route::post('/{id}/return', [ReturnController::class, 'requestReturn'])->whereUuid('id');
        };
        Route::prefix('orders')->group($orderGroup);
        Route::prefix('store/orders')->group($orderGroup);

        // Returns & Refunds
        $returnGroup = function () {
            Route::get('/', [ReturnController::class, 'index']);
            Route::get('/{id}', [ReturnController::class, 'show'])->whereUuid('id');
            Route::post('/{id}/cancel', [ReturnController::class, 'cancel'])->whereUuid('id');
        };
        Route::prefix('returns')->group($returnGroup);
        Route::prefix('store/returns')->group($returnGroup);
        Route::get('refunds/{id}', [ReturnController::class, 'showRefund'])->whereUuid('id');
        Route::get('store/refunds/{id}', [ReturnController::class, 'showRefund'])->whereUuid('id');

        // Membership (Coin Renewal)
        Route::prefix('membership')->group(function () {
            Route::get('/', [MembershipController::class, 'status']);
            Route::get('plans', [MembershipController::class, 'plans']);
            Route::post('quote', [MembershipController::class, 'quote']);
            Route::post('renew', [MembershipController::class, 'renew']);
        });

        // Digital Library
        Route::prefix('library')->group(function () {
            Route::get('/', [DigitalLibraryController::class, 'index']);
            Route::get('/{id}', [DigitalLibraryController::class, 'show'])->whereUuid('id');
            Route::get('/{id}/access', [DigitalLibraryController::class, 'access'])->whereUuid('id');
        });

        // Support Tickets (Store Scoped)
        Route::prefix('store/support/tickets')->group(function () {
            Route::get('/', [StoreSupportController::class, 'index']);
            Route::post('/', [StoreSupportController::class, 'store']);
            Route::get('/{id}', [StoreSupportController::class, 'show'])->whereUuid('id');
            Route::post('/{id}/messages', [StoreSupportController::class, 'addMessage'])->whereUuid('id');
            Route::post('/{id}/close', [StoreSupportController::class, 'close'])->whereUuid('id');
        });

        // Notifications & Devices (Store Scoped)
        Route::prefix('store/notifications')->group(function () {
            Route::get('/', [StoreNotificationController::class, 'index']);
            Route::get('/{id}', [StoreNotificationController::class, 'show'])->whereUuid('id');
            Route::patch('/{id}/read', [StoreNotificationController::class, 'markAsRead'])->whereUuid('id');
        });
        Route::prefix('store/devices')->group(function () {
            Route::post('/', [StoreNotificationController::class, 'registerDevice']);
            Route::delete('/{device_id}', [StoreNotificationController::class, 'removeDevice']);
        });
    });
});

// Internal APIs (Engine / Services)
Route::prefix('internal/v1/coins')->group(function () {
    Route::post('credit', [InternalCoinController::class, 'credit']);
    Route::post('reverse', [InternalCoinController::class, 'reverse']);
});
