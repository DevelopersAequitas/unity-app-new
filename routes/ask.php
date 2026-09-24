<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Ask\AskConfigController;
use App\Http\Controllers\Api\V1\Ask\AskController;
use App\Http\Controllers\Api\V1\Ask\AskMatchController;
use App\Http\Controllers\Api\V1\Ask\AskResponseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Ask / Requirement Discovery System Routes
|--------------------------------------------------------------------------
|
| 26 Core APIs for Collaboration, Referral, and Help flows.
|
*/

Route::middleware('auth:sanctum')->prefix('asks')->group(function (): void {
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
