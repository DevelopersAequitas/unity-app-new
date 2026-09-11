<?php

declare(strict_types=1);

use App\Leader\Controllers\LeaderActivitiesController;
use App\Leader\Controllers\LeaderAuthController;
use App\Leader\Controllers\LeaderDashboardController;
use App\Leader\Controllers\LeaderFinanceController;
use App\Leader\Controllers\LeaderNotificationsController;
use App\Leader\Controllers\LeaderPeersController;
use App\Leader\Controllers\LeaderReportsController;
use App\Leader\Controllers\LeaderRoleManagementController;
use App\Leader\Controllers\LeaderSystemController;
use App\Leader\Controllers\LeaderTeamsController;
use Illuminate\Support\Facades\Route;

// ── Public System & Auth Endpoints ──────────────────────────────────────────
Route::get('/leader/system/app-config', [LeaderSystemController::class, 'appConfig']);

Route::prefix('leader/auth')->group(function () {
    Route::post('send-otp', [LeaderAuthController::class, 'sendOtp']);
    Route::post('verify-otp', [LeaderAuthController::class, 'verifyOtp']);
});

Route::prefix('leader')->group(function () {
    Route::post('send-otp', [LeaderAuthController::class, 'sendOtp']);
    Route::post('verify-otp', [LeaderAuthController::class, 'verifyOtp']);
});

// ── Protected Leader Endpoints ──────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'leader.user'])->group(function () {
    // Auth & Profile (Collision renamed)
    Route::get('/leader/profile', [LeaderAuthController::class, 'profile']);
    Route::get('/auth/profile', [LeaderAuthController::class, 'profile']);
    Route::put('/auth/profile', [LeaderAuthController::class, 'updateProfile']);
    Route::post('/auth/profile/avatar', [LeaderAuthController::class, 'uploadAvatar']);

    // Tab 0: Dashboard
    Route::get('/dashboard/metrics', [LeaderDashboardController::class, 'metrics']);
    Route::get('/dashboard/top-impacters', [LeaderDashboardController::class, 'topImpacters']);

    // Tab 1: Peers & Celebrations
    Route::get('/peers', [LeaderPeersController::class, 'index']);
    Route::get('/peers/celebrations', [LeaderPeersController::class, 'celebrations']);
    Route::post('/peers/p2p-meetings', [LeaderPeersController::class, 'storeP2pMeeting']);
    Route::get('/peers/{id}/meetings', [LeaderPeersController::class, 'meetings']);
    Route::get('/peers/{id}/activities', [LeaderPeersController::class, 'activities']);
    Route::get('/peers/{id}', [LeaderPeersController::class, 'show'])->whereUuid('id');
    Route::post('/peers/{id}/send-wish', [LeaderPeersController::class, 'sendWish']);

    // Tab 2: Teams & Circles
    Route::get('/teams/summary', [LeaderTeamsController::class, 'summary']);
    Route::get('/teams/industries', [LeaderTeamsController::class, 'industries']);
    Route::get('/industries', [LeaderTeamsController::class, 'industries']);
    Route::get('/teams/circles', [LeaderTeamsController::class, 'circles']);
    Route::get('/teams/circles/{circle_id}/peers', [LeaderTeamsController::class, 'circlePeers'])->whereUuid('circle_id');
    Route::get('/teams/circles/{id}', [LeaderTeamsController::class, 'showCircle'])->whereUuid('id');
    Route::get('/teams/circles/{id}/sub-industries', [LeaderTeamsController::class, 'subIndustries']);
    Route::get('/teams/circles/{id}/events', [LeaderTeamsController::class, 'events']);

    // Tab 3: Finance & Accounts
    Route::get('/finance/metrics', [LeaderFinanceController::class, 'metrics']);
    Route::get('/finance/transactions', [LeaderFinanceController::class, 'transactions']);
    Route::put('/finance/commission-rates', [LeaderFinanceController::class, 'updateCommissionRates']);
    Route::post('/finance/transactions/record-offline', [LeaderFinanceController::class, 'recordOfflinePayment']);

    // Tab 4: Reports & Analytics
    Route::get('/reports', [LeaderReportsController::class, 'index']);
    Route::post('/reports', [LeaderReportsController::class, 'store']);
    Route::get('/reports/attendance-trend', [LeaderReportsController::class, 'attendanceTrend']);
    Route::get('/reports/{id}', [LeaderReportsController::class, 'show']);
    Route::get('/reports/{id}/download', [LeaderReportsController::class, 'download']);

    // Activities (Collision renamed for testimonials and impacts)
    Route::get('/referrals', [LeaderActivitiesController::class, 'referrals']);
    Route::get('/referral', [LeaderActivitiesController::class, 'referrals']);
    Route::post('/referrals', [LeaderActivitiesController::class, 'storeReferral']);

    Route::get('/leader/testimonials', [LeaderActivitiesController::class, 'testimonials']);
    Route::get('/leader/testimonial', [LeaderActivitiesController::class, 'testimonials']);
    Route::post('/leader/testimonials', [LeaderActivitiesController::class, 'storeTestimonial']);

    Route::get('/peers-by-coins', [LeaderActivitiesController::class, 'peersByCoins']);
    Route::get('/coins', [LeaderActivitiesController::class, 'peersByCoins']);

    Route::get('/leader/impacts', [LeaderActivitiesController::class, 'impacts']);
    Route::get('/leader/life-impacts', [LeaderActivitiesController::class, 'impacts']);
    Route::get('/leader/impact', [LeaderActivitiesController::class, 'impacts']);
    Route::post('/leader/impacts', [LeaderActivitiesController::class, 'storeImpact']);

    Route::get('/p2p-meetings', [LeaderActivitiesController::class, 'p2pMeetings']);
    Route::get('/peer-meetings', [LeaderActivitiesController::class, 'p2pMeetings']);
    Route::get('/p2p-meeting', [LeaderActivitiesController::class, 'p2pMeetings']);
    Route::post('/p2p-meetings', [LeaderActivitiesController::class, 'storeP2pMeeting']);

    Route::get('/business-deals', [LeaderActivitiesController::class, 'businessDeals']);
    Route::get('/business-deal', [LeaderActivitiesController::class, 'businessDeals']);
    Route::post('/business-deals', [LeaderActivitiesController::class, 'storeBusinessDeal']);

    Route::get('/requirements', [LeaderActivitiesController::class, 'requirements']);
    Route::get('/requirement', [LeaderActivitiesController::class, 'requirements']);

    // Notifications
    Route::get('/notifications', [LeaderNotificationsController::class, 'index']);
    Route::post('/notifications/mark-read', [LeaderNotificationsController::class, 'markRead']);
    Route::post('/notifications/mark-all-read', [LeaderNotificationsController::class, 'markAllRead']);
    Route::post('/notifications/mark-read-all', [LeaderNotificationsController::class, 'markAllRead']);
    Route::get('/notifications/unread-count', [LeaderNotificationsController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [LeaderNotificationsController::class, 'markReadSingle'])->whereUuid('id');
    Route::post('/notifications/read-all', [LeaderNotificationsController::class, 'markAllRead']);

    // Tab 5: Role & Permission Management
    Route::get('/roles/matrix', [LeaderRoleManagementController::class, 'matrix']);
    Route::put('/roles/matrix', [LeaderRoleManagementController::class, 'updateMatrix']);
    Route::post('/roles', [LeaderRoleManagementController::class, 'store']);
    Route::put('/roles/{id}', [LeaderRoleManagementController::class, 'update'])->whereUuid('id');
    Route::delete('/roles/{id}', [LeaderRoleManagementController::class, 'destroy'])->whereUuid('id');
});
