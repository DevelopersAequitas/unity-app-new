<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\AskAdminController;
use App\Http\Controllers\Api\V1\Ask\AskConfigController;
use App\Http\Controllers\Api\V1\Ask\AskController;
use App\Http\Controllers\Api\V1\Ask\AskFeedController;
use App\Http\Controllers\Api\V1\Ask\AskFlowHubController;
use App\Http\Controllers\Api\V1\Ask\AskMatchController;
use App\Http\Controllers\Api\V1\Ask\AskResponseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Ask / Requirement Discovery System Routes
|--------------------------------------------------------------------------
|
| Core APIs for Collaboration, Referral, Feed, and Help flows.
|
*/

// V1 Peers Asks & Community Feed API Routes (matching Flutter PeersAsksHubScreen)
Route::middleware('auth:sanctum')->prefix('v1/asks')->group(function (): void {
    Route::get('feed', [AskFeedController::class, 'feed']);
    Route::get('/', [AskFeedController::class, 'myAsks']);
    Route::post('{id}/congratulate', [AskFeedController::class, 'congratulate'])->whereUuid('id');
    Route::post('{id}/save', [AskFeedController::class, 'save'])->whereUuid('id');
    Route::post('{id}/close-and-thank', [AskFeedController::class, 'closeAndThank'])->whereUuid('id');

    // 3 Asks Flows Engine Hub Routes (collaboration, referral, help)
    Route::prefix('{flow}')->whereIn('flow', ['collaboration', 'referral', 'help'])->group(function (): void {
        Route::get('global', [AskFlowHubController::class, 'globalFeed']);
        Route::get('my', [AskFlowHubController::class, 'myAsks']);
        Route::get('leaderboard', [AskFlowHubController::class, 'leaderboard']);
        Route::match(['patch', 'post'], '{id}/status', [AskFlowHubController::class, 'updateFlowItemStatus'])->whereUuid('id');
    });
    Route::get('categories', [AskFlowHubController::class, 'categories']);
    Route::patch('responses/{id}/status', [AskFlowHubController::class, 'updateResponseStatus'])->whereUuid('id');

    // Supporting configurations & creation under v1
    Route::get('flows', [AskConfigController::class, 'getFlows']);
    Route::get('flows/{flow}/types', [AskConfigController::class, 'getTypes']);
    Route::get('form-config', [AskConfigController::class, 'getFormConfig']);
    Route::post('/', [AskController::class, 'storeDraft']);

    Route::prefix('{ask}')->whereUuid('ask')->group(function (): void {
        Route::put('/', [AskController::class, 'saveDetails']);
        Route::get('/', [AskController::class, 'show']);
        Route::patch('/', [AskController::class, 'update']);
        Route::put('filters', [AskController::class, 'saveFilters']);
        Route::put('visibility', [AskController::class, 'setVisibility']);
        Route::put('timeline-preference', [AskController::class, 'setTimelinePreference']);
        Route::get('preview', [AskController::class, 'preview']);
        Route::post('publish', [AskController::class, 'publish']);
        Route::patch('status', [AskController::class, 'updateStatus']);
        Route::post('close', [AskController::class, 'closeWithFeedback']);
        Route::get('history', [AskController::class, 'history']);
        Route::post('referral-link', [AskController::class, 'linkReferral']);

        // Peer Response System
        Route::get('respond', [AskResponseController::class, 'respondView']);
        Route::prefix('responses')->group(function (): void {
            Route::post('/', [AskResponseController::class, 'store']);
            Route::get('/', [AskResponseController::class, 'index']);

            Route::prefix('{response}')->whereUuid('response')->group(function (): void {
                Route::get('/', [AskResponseController::class, 'show']);
                Route::patch('/', [AskResponseController::class, 'update']);
                Route::get('history', [AskResponseController::class, 'history']);
                Route::patch('contact', [AskResponseController::class, 'updateContact']);
            });
        });
    });
});

Route::middleware('auth:sanctum')->prefix('asks')->group(function (): void {
    // Peers Community Feed & Actions
    Route::get('feed', [AskFeedController::class, 'feed']);
    Route::post('{id}/congratulate', [AskFeedController::class, 'congratulate'])->whereUuid('id');
    Route::post('{id}/save', [AskFeedController::class, 'save'])->whereUuid('id');
    Route::post('{id}/close-and-thank', [AskFeedController::class, 'closeAndThank'])->whereUuid('id');

    // 3 Asks Flows Engine Hub Routes (collaboration, referral, help)
    Route::prefix('{flow}')->whereIn('flow', ['collaboration', 'referral', 'help'])->group(function (): void {
        Route::get('global', [AskFlowHubController::class, 'globalFeed']);
        Route::get('my', [AskFlowHubController::class, 'myAsks']);
        Route::get('leaderboard', [AskFlowHubController::class, 'leaderboard']);
        Route::match(['patch', 'post'], '{id}/status', [AskFlowHubController::class, 'updateFlowItemStatus'])->whereUuid('id');
    });
    Route::get('categories', [AskFlowHubController::class, 'categories']);
    Route::patch('responses/{id}/status', [AskFlowHubController::class, 'updateResponseStatus'])->whereUuid('id');

    // Part 1 — Dynamic Configuration (APIs 1-3)
    Route::get('flows', [AskConfigController::class, 'getFlows']);
    Route::get('flows/{flow}/types', [AskConfigController::class, 'getTypes']);
    Route::get('form-config', [AskConfigController::class, 'getFormConfig']);

    // Part 2 & 3 — Ask Creation, Editing, Details & Lifecycle (APIs 4-14, 24, 26/27)
    Route::post('/', [AskController::class, 'storeDraft']);
    Route::get('/', [AskController::class, 'index']);

    Route::prefix('{ask}')->whereUuid('ask')->group(function (): void {
        Route::put('/', [AskController::class, 'saveDetails']);
        Route::get('/', [AskController::class, 'show']);
        Route::patch('/', [AskController::class, 'update']);
        Route::put('filters', [AskController::class, 'saveFilters']);
        Route::put('visibility', [AskController::class, 'setVisibility']);
        Route::put('timeline-preference', [AskController::class, 'setTimelinePreference']);
        Route::get('preview', [AskController::class, 'preview']);
        Route::post('publish', [AskController::class, 'publish']);
        Route::patch('status', [AskController::class, 'updateStatus']);
        Route::post('close', [AskController::class, 'closeWithFeedback']);
        Route::get('history', [AskController::class, 'history']);
        Route::post('referral-link', [AskController::class, 'linkReferral']);

        // Part 4 — Matching Engine (APIs 15-17)
        Route::prefix('matches')->group(function (): void {
            Route::post('generate', [AskMatchController::class, 'generate']);
            Route::get('/', [AskMatchController::class, 'index']);
            Route::patch('{match}', [AskMatchController::class, 'update'])->whereUuid('match');
        });

        // Part 5 & 6 — Peer Response System (APIs 18-23, 25)
        Route::get('respond', [AskResponseController::class, 'respondView']);
        Route::prefix('responses')->group(function (): void {
            Route::post('/', [AskResponseController::class, 'store']);
            Route::get('/', [AskResponseController::class, 'index']);

            Route::prefix('{response}')->whereUuid('response')->group(function (): void {
                Route::get('/', [AskResponseController::class, 'show']);
                Route::patch('/', [AskResponseController::class, 'update']);
                Route::get('history', [AskResponseController::class, 'history']);
                Route::patch('contact', [AskResponseController::class, 'updateContact']);
            });
        });
    });
});

/*
|--------------------------------------------------------------------------
| Ask Admin API Routes
|--------------------------------------------------------------------------
|
| Management & statistics endpoints for Admin applications.
|
*/
Route::middleware(['auth:sanctum'])->prefix('admin/asks')->group(function (): void {
    Route::get('/', [AskAdminController::class, 'index']);
    Route::get('stats', [AskAdminController::class, 'stats']);

    Route::prefix('{ask}')->whereUuid('ask')->group(function (): void {
        Route::get('/', [AskAdminController::class, 'show']);
        Route::patch('status', [AskAdminController::class, 'updateStatus']);
        Route::delete('/', [AskAdminController::class, 'destroy']);
    });
});
