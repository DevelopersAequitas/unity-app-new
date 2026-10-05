<?php

use App\Http\Controllers\Admin\Store\AdminStoreCatalogWebController;
use App\Http\Controllers\Admin\Store\AdminStoreCommunicationWebController;
use App\Http\Controllers\Admin\Store\AdminStoreConfigWebController;
use App\Http\Controllers\Admin\Store\AdminStoreDashboardWebController;
use App\Http\Controllers\Admin\Store\AdminStoreInventoryWebController;
use App\Http\Controllers\Admin\Store\AdminStoreMembershipWebController;
use App\Http\Controllers\Admin\Store\AdminStoreOrderWebController;
use App\Http\Controllers\Admin\Store\AdminStoreReportWebController;
use App\Http\Controllers\Admin\Store\AdminStoreReturnWebController;
use App\Http\Controllers\Admin\Store\AdminStoreServiceabilityWebController;
use App\Http\Controllers\Admin\Store\AdminStoreWalletWebController;
use Illuminate\Support\Facades\Route;

Route::prefix('store')->name('store.')->group(function () {
    // 1. Store Dashboard
    Route::get('/', [AdminStoreDashboardWebController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [AdminStoreDashboardWebController::class, 'index']);

    // 2. Catalog (Categories & Products)
    Route::prefix('catalog')->name('catalog.')->group(function () {
        // Categories
        Route::get('/categories', [AdminStoreCatalogWebController::class, 'categories'])->name('categories');
        Route::post('/categories', [AdminStoreCatalogWebController::class, 'storeCategory'])->name('categories.store');
        Route::put('/categories/{id}', [AdminStoreCatalogWebController::class, 'updateCategory'])->name('categories.update');
        Route::delete('/categories/{id}', [AdminStoreCatalogWebController::class, 'deleteCategory'])->name('categories.delete');

        // Products
        Route::get('/products', [AdminStoreCatalogWebController::class, 'products'])->name('products');
        Route::get('/products/create', [AdminStoreCatalogWebController::class, 'createProduct'])->name('products.create');
        Route::post('/products', [AdminStoreCatalogWebController::class, 'storeProduct'])->name('products.store');
        Route::get('/products/{id}/edit', [AdminStoreCatalogWebController::class, 'editProduct'])->name('products.edit');
        Route::put('/products/{id}', [AdminStoreCatalogWebController::class, 'updateProduct'])->name('products.update');
        Route::post('/products/{id}/archive', [AdminStoreCatalogWebController::class, 'archiveProduct'])->name('products.archive');

        // SKU Variants & Images
        Route::post('/products/{productId}/variants', [AdminStoreCatalogWebController::class, 'storeVariant'])->name('products.variants.store');
        Route::put('/products/variants/{variantId}', [AdminStoreCatalogWebController::class, 'updateVariant'])->name('products.variants.update');
        Route::delete('/products/variants/{variantId}', [AdminStoreCatalogWebController::class, 'deleteVariant'])->name('products.variants.delete');

        Route::post('/products/{productId}/images', [AdminStoreCatalogWebController::class, 'addImage'])->name('products.images.store');
        Route::delete('/products/images/{imageId}', [AdminStoreCatalogWebController::class, 'deleteImage'])->name('products.images.delete');
    });

    // Alias routes for direct categories.* and products.* references
    Route::name('categories.')->group(function () {
        Route::get('/categories-alias', [AdminStoreCatalogWebController::class, 'categories'])->name('index');
        Route::post('/categories-alias', [AdminStoreCatalogWebController::class, 'storeCategory'])->name('store');
        Route::put('/categories-alias/{id}', [AdminStoreCatalogWebController::class, 'updateCategory'])->name('update');
        Route::delete('/categories-alias/{id}', [AdminStoreCatalogWebController::class, 'deleteCategory'])->name('delete');
    });
    Route::name('products.')->group(function () {
        Route::get('/products-alias', [AdminStoreCatalogWebController::class, 'products'])->name('index');
        Route::get('/products-alias/create', [AdminStoreCatalogWebController::class, 'createProduct'])->name('create');
        Route::post('/products-alias', [AdminStoreCatalogWebController::class, 'storeProduct'])->name('store');
        Route::get('/products-alias/{id}/edit', [AdminStoreCatalogWebController::class, 'editProduct'])->name('edit');
        Route::put('/products-alias/{id}', [AdminStoreCatalogWebController::class, 'updateProduct'])->name('update');
        Route::post('/products-alias/{id}/archive', [AdminStoreCatalogWebController::class, 'archiveProduct'])->name('archive');
        Route::post('/products-alias/{productId}/variants', [AdminStoreCatalogWebController::class, 'storeVariant'])->name('variants.store');
        Route::delete('/products-alias/variants/{variantId}', [AdminStoreCatalogWebController::class, 'deleteVariant'])->name('variants.delete');
        Route::post('/products-alias/{productId}/images', [AdminStoreCatalogWebController::class, 'addImage'])->name('images.store');
        Route::delete('/products-alias/images/{imageId}', [AdminStoreCatalogWebController::class, 'deleteImage'])->name('images.delete');
    });

    // 3. Inventory Management
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/', [AdminStoreInventoryWebController::class, 'index'])->name('index');
        Route::get('/low-stock', [AdminStoreInventoryWebController::class, 'lowStock'])->name('low-stock');
        Route::get('/movements', [AdminStoreInventoryWebController::class, 'movements'])->name('movements');
        Route::post('/adjust', [AdminStoreInventoryWebController::class, 'adjustStock'])->name('adjust');
        Route::get('/export', [AdminStoreInventoryWebController::class, 'exportLowStockCsv'])->name('export');
    });

    // 4. Serviceability, Pickup Points & Banners
    Route::prefix('serviceability')->name('serviceability.')->group(function () {
        // Pincodes
        Route::get('/pincodes', [AdminStoreServiceabilityWebController::class, 'pincodes'])->name('pincodes');
        Route::post('/pincodes', [AdminStoreServiceabilityWebController::class, 'storePincode'])->name('pincodes.store');
        Route::post('/pincodes/import', [AdminStoreServiceabilityWebController::class, 'importPincodesCsv'])->name('pincodes.import');
        Route::post('/pincodes/{id}/status', [AdminStoreServiceabilityWebController::class, 'togglePincode'])->name('pincodes.status');
        Route::get('/pincodes/export', [AdminStoreServiceabilityWebController::class, 'exportPincodesCsv'])->name('pincodes.export');

        // Pickup Points
        Route::get('/pickup-points', [AdminStoreServiceabilityWebController::class, 'pickupPoints'])->name('pickup-points');
        Route::post('/pickup-points', [AdminStoreServiceabilityWebController::class, 'storePickupPoint'])->name('pickup-points.store');
        Route::post('/pickup-points/{id}', [AdminStoreServiceabilityWebController::class, 'updatePickupPoint'])->name('pickup-points.update');
        Route::post('/pickup-points/{id}/status', [AdminStoreServiceabilityWebController::class, 'togglePickupPoint'])->name('pickup-points.status');

        // Banners
        Route::get('/banners', [AdminStoreServiceabilityWebController::class, 'banners'])->name('banners');
        Route::post('/banners', [AdminStoreServiceabilityWebController::class, 'storeBanner'])->name('banners.store');
        Route::post('/banners/{id}', [AdminStoreServiceabilityWebController::class, 'updateBanner'])->name('banners.update');
        Route::post('/banners/{id}/status', [AdminStoreServiceabilityWebController::class, 'toggleBanner'])->name('banners.status');
        Route::delete('/banners/{id}', [AdminStoreServiceabilityWebController::class, 'deleteBanner'])->name('banners.destroy');
    });

    // 5. Peer Wallets, Maker-Checker & Economy
    Route::prefix('wallet')->name('wallet.')->group(function () {
        Route::get('/', [AdminStoreWalletWebController::class, 'index'])->name('index');
        Route::get('/economy', [AdminStoreWalletWebController::class, 'economy'])->name('economy');
        Route::get('/adjustments', [AdminStoreWalletWebController::class, 'adjustments'])->name('adjustments');
        Route::post('/adjustments/request', [AdminStoreWalletWebController::class, 'storeAdjustmentRequest'])->name('adjustments.request');
        Route::post('/adjustments/{id}/approve', [AdminStoreWalletWebController::class, 'approveAdjustment'])->name('adjustments.approve');
        Route::post('/adjustments/{id}/reject', [AdminStoreWalletWebController::class, 'rejectAdjustment'])->name('adjustments.reject');

        Route::get('/{id}', [AdminStoreWalletWebController::class, 'show'])->name('show');
        Route::post('/{id}/freeze', [AdminStoreWalletWebController::class, 'freeze'])->name('freeze');
    });

    // 6. Orders & Fulfilment
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [AdminStoreOrderWebController::class, 'index'])->name('index');
        Route::get('/{id}', [AdminStoreOrderWebController::class, 'show'])->name('show');
        Route::post('/{id}/status', [AdminStoreOrderWebController::class, 'updateStatus'])->name('status');
        Route::get('/{id}/packing-slip', [AdminStoreOrderWebController::class, 'packingSlip'])->name('packing-slip');
        Route::post('/{id}/courier/dispatch', [AdminStoreOrderWebController::class, 'dispatchShipment'])->name('courier.dispatch');
        Route::post('/{id}/pickup/verify-pin', [AdminStoreOrderWebController::class, 'verifyPickup'])->name('pickup.verify-pin');
        Route::post('/{id}/cancel', [AdminStoreOrderWebController::class, 'cancelOrder'])->name('cancel');
    });

    // 7. Returns & Refunds
    Route::prefix('returns')->name('returns.')->group(function () {
        Route::get('/', [AdminStoreReturnWebController::class, 'index'])->name('index');
        Route::get('/refunds', [AdminStoreReturnWebController::class, 'refunds'])->name('refunds');
        Route::get('/{id}', [AdminStoreReturnWebController::class, 'show'])->name('show');
        Route::post('/{id}/approve', [AdminStoreReturnWebController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [AdminStoreReturnWebController::class, 'reject'])->name('reject');
    });

    // 8. Memberships & Entitlements
    Route::prefix('membership')->name('membership.')->group(function () {
        Route::get('/', [AdminStoreMembershipWebController::class, 'index'])->name('index');
        Route::post('/tiers', [AdminStoreMembershipWebController::class, 'storePlan'])->name('tiers.store');
        Route::post('/tiers/{id}', [AdminStoreMembershipWebController::class, 'updatePlan'])->name('tiers.update');
        Route::get('/ledger', [AdminStoreMembershipWebController::class, 'ledger'])->name('ledger');
        Route::get('/entitlements', [AdminStoreMembershipWebController::class, 'entitlements'])->name('entitlements');
        Route::post('/entitlements/grant', [AdminStoreMembershipWebController::class, 'grantEntitlement'])->name('entitlements.grant');
        Route::post('/entitlements/{id}/revoke', [AdminStoreMembershipWebController::class, 'revokeEntitlement'])->name('entitlements.revoke');
    });

    // 9. Support & Communication
    Route::prefix('support')->name('support.')->group(function () {
        Route::get('/', [AdminStoreCommunicationWebController::class, 'supportTickets'])->name('index');
        Route::get('/{id}', [AdminStoreCommunicationWebController::class, 'showTicket'])->name('show');
        Route::post('/{id}/reply', [AdminStoreCommunicationWebController::class, 'replyTicket'])->name('reply');
        Route::post('/{id}/status', [AdminStoreCommunicationWebController::class, 'updateTicketStatus'])->name('status');
    });

    Route::prefix('communication')->name('communication.')->group(function () {
        Route::get('/logs', [AdminStoreCommunicationWebController::class, 'notificationLogs'])->name('logs');
        Route::post('/logs/{id}/resend', [AdminStoreCommunicationWebController::class, 'resendNotification'])->name('logs.resend');
    });

    // 10. Reports & Analytics
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/sales', [AdminStoreReportWebController::class, 'sales'])->name('sales');
        Route::get('/sales/export', [AdminStoreReportWebController::class, 'exportSalesCsv'])->name('sales.export');
        Route::get('/coin-economy', [AdminStoreReportWebController::class, 'coinEconomy'])->name('coin-economy');
        Route::get('/products', [AdminStoreReportWebController::class, 'products'])->name('products');
        Route::get('/reconciliation', [AdminStoreReportWebController::class, 'reconciliation'])->name('reconciliation');
    });

    // 11. Configuration, Policies, System Health & Audit Logs
    Route::prefix('config')->name('config.')->group(function () {
        Route::get('/', [AdminStoreConfigWebController::class, 'index'])->name('index');
        Route::post('/update', [AdminStoreConfigWebController::class, 'update'])->name('update');
        Route::post('/maintenance', [AdminStoreConfigWebController::class, 'toggleStoreStatus'])->name('maintenance');
        Route::get('/policies', [AdminStoreConfigWebController::class, 'policies'])->name('policies');
        Route::post('/policies/update', [AdminStoreConfigWebController::class, 'updatePolicy'])->name('policies.update');
        Route::get('/system-health', [AdminStoreConfigWebController::class, 'systemHealth'])->name('system-health');
        Route::get('/audit-log', [AdminStoreConfigWebController::class, 'auditLog'])->name('audit-log');
    });
});
