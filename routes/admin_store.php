<?php

use App\Http\Controllers\Api\V1\Admin\Store\AdminStoreCategoryController;
use App\Http\Controllers\Api\V1\Admin\Store\AdminStoreConfigController;
use App\Http\Controllers\Api\V1\Admin\Store\AdminStoreDashboardController;
use App\Http\Controllers\Api\V1\Admin\Store\AdminStoreEntitlementController;
use App\Http\Controllers\Api\V1\Admin\Store\AdminStoreMembershipController;
use App\Http\Controllers\Api\V1\Admin\Store\AdminStoreNotificationController;
use App\Http\Controllers\Api\V1\Admin\Store\AdminStoreOrderController;
use App\Http\Controllers\Api\V1\Admin\Store\AdminStorePolicyController;
use App\Http\Controllers\Api\V1\Admin\Store\AdminStoreProductController;
use App\Http\Controllers\Api\V1\Admin\Store\AdminStoreReportController;
use App\Http\Controllers\Api\V1\Admin\Store\AdminStoreReturnController;
use App\Http\Controllers\Api\V1\Admin\Store\AdminStoreSupportController;
use App\Http\Controllers\Api\V1\Admin\Store\AdminStoreVariantController;
use App\Http\Controllers\Api\V1\Admin\Store\AdminStoreWalletAdjustmentController;
use App\Http\Controllers\Api\V1\Admin\Store\AdminStoreWalletController;
use App\Http\Controllers\Api\V1\Admin\Store\AdminStoreWishlistController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Peers Store & Coin Wallet Admin API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('admin/v1')->middleware(['auth:sanctum'])->group(function () {
    // Dashboard
    Route::get('dashboard', [AdminStoreDashboardController::class, 'index']);
    Route::get('dashboard/summary', [AdminStoreDashboardController::class, 'summary']);
    Route::get('dashboard/pending-actions', [AdminStoreDashboardController::class, 'pendingActions']);

    // Categories
    Route::prefix('categories')->group(function () {
        Route::get('/', [AdminStoreCategoryController::class, 'index']);
        Route::post('/', [AdminStoreCategoryController::class, 'store']);
        Route::put('/{id}', [AdminStoreCategoryController::class, 'update'])->whereUuid('id');
        Route::delete('/{id}', [AdminStoreCategoryController::class, 'destroy'])->whereUuid('id');
    });

    // Products
    Route::prefix('products')->group(function () {
        Route::get('/', [AdminStoreProductController::class, 'index']);
        Route::post('/', [AdminStoreProductController::class, 'store']);
        Route::get('/{id}', [AdminStoreProductController::class, 'show'])->whereUuid('id');
        Route::put('/{id}', [AdminStoreProductController::class, 'update'])->whereUuid('id');
        Route::post('/{id}/disable', [AdminStoreProductController::class, 'disable'])->whereUuid('id');
        Route::post('/{id}/images', [AdminStoreProductController::class, 'addImage'])->whereUuid('id');
        Route::delete('/{id}/images/{imageId}', [AdminStoreProductController::class, 'removeImage'])->whereUuid('id')->whereUuid('imageId');

        // Variants for Product
        Route::post('/{id}/variants', [AdminStoreVariantController::class, 'store'])->whereUuid('id');
    });

    // Variants & Inventory
    Route::prefix('variants')->group(function () {
        Route::put('/{id}', [AdminStoreVariantController::class, 'update'])->whereUuid('id');
        Route::post('/{id}/stock-adjustment', [AdminStoreVariantController::class, 'adjustStock'])->whereUuid('id');
        Route::get('/{id}/inventory', [AdminStoreVariantController::class, 'inventoryHistory'])->whereUuid('id');
    });

    // Orders & Fulfillment
    Route::prefix('orders')->group(function () {
        Route::get('/', [AdminStoreOrderController::class, 'index']);
        Route::get('/{id}', [AdminStoreOrderController::class, 'show'])->whereUuid('id');
        Route::post('/{id}/status', [AdminStoreOrderController::class, 'updateStatus'])->whereUuid('id');
        Route::post('/{id}/notes', [AdminStoreOrderController::class, 'addNote'])->whereUuid('id');
        Route::get('/{id}/packing-slip', [AdminStoreOrderController::class, 'packingSlip'])->whereUuid('id');
        Route::post('/{id}/shipment', [AdminStoreOrderController::class, 'createShipment'])->whereUuid('id');
        Route::post('/{id}/pickup/verify', [AdminStoreOrderController::class, 'verifyPickup'])->whereUuid('id');
    });

    // Returns & Refunds
    Route::prefix('returns')->group(function () {
        Route::get('/', [AdminStoreReturnController::class, 'index']);
        Route::get('/{id}', [AdminStoreReturnController::class, 'show'])->whereUuid('id');
        Route::post('/{id}/approve', [AdminStoreReturnController::class, 'approve'])->whereUuid('id');
        Route::post('/{id}/reject', [AdminStoreReturnController::class, 'reject'])->whereUuid('id');
        Route::post('/{id}/receive', [AdminStoreReturnController::class, 'receive'])->whereUuid('id');
        Route::post('/{id}/inspect', [AdminStoreReturnController::class, 'inspect'])->whereUuid('id');
        Route::post('/{id}/refund', [AdminStoreReturnController::class, 'refund'])->whereUuid('id');
    });

    // Peer Wallets
    Route::prefix('wallets')->group(function () {
        Route::get('/', [AdminStoreWalletController::class, 'index']);
        Route::get('/{id}', [AdminStoreWalletController::class, 'show'])->whereUuid('id');
        Route::post('/{id}/freeze', [AdminStoreWalletController::class, 'freeze'])->whereUuid('id');
        Route::post('/{id}/unfreeze', [AdminStoreWalletController::class, 'unfreeze'])->whereUuid('id');
    });

    // Wallet Adjustments (Maker-Checker)
    Route::prefix('wallet-adjustments')->group(function () {
        Route::get('/', [AdminStoreWalletAdjustmentController::class, 'index']);
        Route::post('/', [AdminStoreWalletAdjustmentController::class, 'store']);
        Route::post('/{id}/approve', [AdminStoreWalletAdjustmentController::class, 'approve'])->whereUuid('id');
        Route::post('/{id}/reject', [AdminStoreWalletAdjustmentController::class, 'reject'])->whereUuid('id');
    });

    // Bonus Grants
    Route::prefix('bonus-grants')->group(function () {
        Route::get('/', [AdminStoreWalletAdjustmentController::class, 'index']);
        Route::post('/', [AdminStoreWalletAdjustmentController::class, 'createBonusGrant']);
        Route::post('/{id}/approve', [AdminStoreWalletAdjustmentController::class, 'approve'])->whereUuid('id');
        Route::post('/{id}/reject', [AdminStoreWalletAdjustmentController::class, 'reject'])->whereUuid('id');
    });

    // Membership Admin
    Route::prefix('membership')->group(function () {
        Route::get('plans', [AdminStoreMembershipController::class, 'plans']);
        Route::post('plans', [AdminStoreMembershipController::class, 'storePlan']);
        Route::put('plans/{id}', [AdminStoreMembershipController::class, 'updatePlan'])->whereUuid('id');
        Route::get('ledger/{userId}', [AdminStoreMembershipController::class, 'ledger'])->whereUuid('userId');
    });

    // Entitlements
    Route::prefix('entitlements')->group(function () {
        Route::get('/', [AdminStoreEntitlementController::class, 'index']);
        Route::get('/{id}', [AdminStoreEntitlementController::class, 'show'])->whereUuid('id');
        Route::post('grant', [AdminStoreEntitlementController::class, 'grant']);
        Route::post('/{id}/revoke', [AdminStoreEntitlementController::class, 'revoke'])->whereUuid('id');
    });

    // Notification Logs & Templates
    Route::prefix('notifications')->group(function () {
        Route::get('templates', [AdminStoreNotificationController::class, 'templates']);
        Route::get('templates/{id}', [AdminStoreNotificationController::class, 'showTemplate']);
        Route::get('logs', [AdminStoreNotificationController::class, 'logs']);
        Route::post('logs/{id}/resend', [AdminStoreNotificationController::class, 'resendLog'])->whereUuid('id');
    });

    // Support Tickets
    Route::prefix('support/tickets')->group(function () {
        Route::get('/', [AdminStoreSupportController::class, 'index']);
        Route::get('/{id}', [AdminStoreSupportController::class, 'show'])->whereUuid('id');
        Route::post('/{id}/assign', [AdminStoreSupportController::class, 'assign'])->whereUuid('id');
        Route::post('/{id}/messages', [AdminStoreSupportController::class, 'reply'])->whereUuid('id');
        Route::post('/{id}/resolve', [AdminStoreSupportController::class, 'resolve'])->whereUuid('id');
    });

    // Store Configuration
    Route::prefix('config')->group(function () {
        Route::get('/', [AdminStoreConfigController::class, 'index']);
        Route::get('/{key}', [AdminStoreConfigController::class, 'show']);
        Route::put('/{key}', [AdminStoreConfigController::class, 'update']);
    });

    // Policy Pages
    Route::prefix('policies')->group(function () {
        Route::get('/', [AdminStorePolicyController::class, 'index']);
        Route::post('/', [AdminStorePolicyController::class, 'store']);
        Route::put('/{id}', [AdminStorePolicyController::class, 'update'])->whereUuid('id');
        Route::post('/{id}/publish', [AdminStorePolicyController::class, 'publish'])->whereUuid('id');
    });

    // Wishlists (Rahul bhai's request)
    Route::prefix('wishlists')->group(function () {
        Route::get('/', [AdminStoreWishlistController::class, 'index']);
        Route::get('stats', [AdminStoreWishlistController::class, 'stats']);
        Route::get('user/{userId}', [AdminStoreWishlistController::class, 'userWishlist'])->whereUuid('userId');
        Route::delete('{id}', [AdminStoreWishlistController::class, 'destroy'])->whereUuid('id');
    });

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('sales', [AdminStoreReportController::class, 'sales']);
        Route::get('coin-redemption', [AdminStoreReportController::class, 'coinRedemption']);
        Route::get('coin-issuance', [AdminStoreReportController::class, 'coinIssuance']);
        Route::get('membership-renewals', [AdminStoreReportController::class, 'membershipRenewals']);
        Route::post('export', [AdminStoreReportController::class, 'export']);
        Route::get('download/{token}', [AdminStoreReportController::class, 'download']);
    });
});

// Public download route for browser direct links
Route::get('admin/v1/reports/download/{token}', [AdminStoreReportController::class, 'download']);
