<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\LeadAdminApiController;
use App\Http\Controllers\Api\Admin\PendingRequestAdminController;
use App\Http\Controllers\Api\V1\Ask\AskFlowHubController;
use App\Http\Controllers\Api\V1\CircularController;
use App\Leader\Controllers\LeaderActivitiesController;
use App\Leader\Controllers\LeaderAskFlowController;
use App\Leader\Controllers\LeaderAuthController;
use App\Leader\Controllers\LeaderDashboardController;
use App\Leader\Controllers\LeaderFinanceController;
use App\Leader\Controllers\LeaderMemberController;
use App\Leader\Controllers\LeaderNotificationsController;
use App\Leader\Controllers\LeaderPeersController;
use App\Leader\Controllers\LeaderReportsController;
use App\Leader\Controllers\LeaderRoleManagementController;
use App\Leader\Controllers\LeaderSystemController;
use App\Leader\Controllers\LeaderTeamsController;
use Illuminate\Support\Facades\Route;

// ── Public System & Auth Endpoints ──────────────────────────────────────────
Route::get('/leader/system/app-config', [LeaderSystemController::class, 'appConfig']);
Route::get('/leaders/system/app-config', [LeaderSystemController::class, 'appConfig']);

foreach (['leader/auth', 'leaders/auth', 'leader', 'leaders'] as $authPrefix) {
    Route::prefix($authPrefix)->group(function () {
        Route::post('send-otp', [LeaderAuthController::class, 'sendOtp']);
        Route::post('verify-otp', [LeaderAuthController::class, 'verifyOtp']);
    });
}

// ── Protected Leader Endpoints ──────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'leader.user'])->group(function () {
    // Auth & Profile
    Route::get('/leader/profile', [LeaderAuthController::class, 'profile']);
    Route::get('/auth/profile', [LeaderAuthController::class, 'profile']);
    Route::get('/leader/auth/profile', [LeaderAuthController::class, 'profile']);
    Route::put('/auth/profile', [LeaderAuthController::class, 'updateProfile']);
    Route::put('/leader/auth/profile', [LeaderAuthController::class, 'updateProfile']);
    Route::put('/leader/profile', [LeaderAuthController::class, 'updateProfile']);
    Route::post('/auth/profile/avatar', [LeaderAuthController::class, 'uploadAvatar']);
    Route::post('/leader/auth/profile/avatar', [LeaderAuthController::class, 'uploadAvatar']);
    Route::post('/leader/profile/avatar', [LeaderAuthController::class, 'uploadAvatar']);

    // Tab 0: Dashboard
    Route::get('/dashboard/metrics', [LeaderDashboardController::class, 'metrics']);
    Route::get('/leader/dashboard/metrics', [LeaderDashboardController::class, 'metrics']);
    Route::get('/dashboard/top-impacters', [LeaderDashboardController::class, 'topImpacters']);
    Route::get('/leader/dashboard/top-impacters', [LeaderDashboardController::class, 'topImpacters']);

    // Tab 1: Peers & Celebrations
    Route::get('/peers', [LeaderPeersController::class, 'index']);
    Route::get('/leader/peers', [LeaderPeersController::class, 'index']);
    Route::get('/peers/celebrations', [LeaderPeersController::class, 'celebrations']);
    Route::get('/leader/peers/celebrations', [LeaderPeersController::class, 'celebrations']);
    Route::post('/peers/p2p-meetings', [LeaderPeersController::class, 'storeP2pMeeting']);
    Route::post('/leader/peers/p2p-meetings', [LeaderPeersController::class, 'storeP2pMeeting']);
    Route::get('/peers/{id}/meetings', [LeaderPeersController::class, 'meetings']);
    Route::get('/leader/peers/{id}/meetings', [LeaderPeersController::class, 'meetings']);
    Route::get('/peers/{id}/activities', [LeaderPeersController::class, 'activities']);
    Route::get('/leader/peers/{id}/activities', [LeaderPeersController::class, 'activities']);
    Route::get('/peers/{id}', [LeaderPeersController::class, 'show'])->whereUuid('id');
    Route::get('/leader/peers/{id}', [LeaderPeersController::class, 'show'])->whereUuid('id');
    Route::post('/peers/{id}/send-wish', [LeaderPeersController::class, 'sendWish']);
    Route::post('/leader/peers/{id}/send-wish', [LeaderPeersController::class, 'sendWish']);

    // Tab 2: Teams & Circles
    Route::get('/teams/summary', [LeaderTeamsController::class, 'summary']);
    Route::get('/leader/teams/summary', [LeaderTeamsController::class, 'summary']);
    Route::get('/teams/industries', [LeaderTeamsController::class, 'industries']);
    Route::get('/leader/teams/industries', [LeaderTeamsController::class, 'industries']);
    Route::get('/industries', [LeaderTeamsController::class, 'industries']);
    Route::get('/leader/industries', [LeaderTeamsController::class, 'industries']);
    Route::get('/teams/circles', [LeaderTeamsController::class, 'circles']);
    Route::get('/leader/teams/circles', [LeaderTeamsController::class, 'circles']);
    Route::get('/teams/circles/{circle_id}/peers', [LeaderTeamsController::class, 'circlePeers'])->whereUuid('circle_id');
    Route::get('/leader/teams/circles/{circle_id}/peers', [LeaderTeamsController::class, 'circlePeers'])->whereUuid('circle_id');
    Route::get('/teams/circles/{id}', [LeaderTeamsController::class, 'showCircle'])->whereUuid('id');
    Route::get('/leader/teams/circles/{id}', [LeaderTeamsController::class, 'showCircle'])->whereUuid('id');
    Route::get('/teams/circles/{id}/sub-industries', [LeaderTeamsController::class, 'subIndustries']);
    Route::get('/leader/teams/circles/{id}/sub-industries', [LeaderTeamsController::class, 'subIndustries']);
    Route::get('/teams/circles/{id}/events', [LeaderTeamsController::class, 'events']);
    Route::get('/leader/teams/circles/{id}/events', [LeaderTeamsController::class, 'events']);

    // Tab 3: Finance & Accounts
    Route::get('/finance/metrics', [LeaderFinanceController::class, 'metrics']);
    Route::get('/leader/finance/metrics', [LeaderFinanceController::class, 'metrics']);
    Route::get('/finance/transactions', [LeaderFinanceController::class, 'transactions']);
    Route::get('/leader/finance/transactions', [LeaderFinanceController::class, 'transactions']);
    Route::put('/finance/commission-rates', [LeaderFinanceController::class, 'updateCommissionRates']);
    Route::put('/leader/finance/commission-rates', [LeaderFinanceController::class, 'updateCommissionRates']);
    Route::post('/finance/transactions/record-offline', [LeaderFinanceController::class, 'recordOfflinePayment']);
    Route::post('/leader/finance/transactions/record-offline', [LeaderFinanceController::class, 'recordOfflinePayment']);

    // Tab 4: Reports & Analytics
    Route::get('/reports', [LeaderReportsController::class, 'index']);
    Route::get('/leader/reports', [LeaderReportsController::class, 'index']);
    Route::post('/reports', [LeaderReportsController::class, 'store']);
    Route::post('/leader/reports', [LeaderReportsController::class, 'store']);
    Route::get('/reports/attendance-trend', [LeaderReportsController::class, 'attendanceTrend']);
    Route::get('/leader/reports/attendance-trend', [LeaderReportsController::class, 'attendanceTrend']);
    Route::get('/reports/{id}', [LeaderReportsController::class, 'show']);
    Route::get('/leader/reports/{id}', [LeaderReportsController::class, 'show']);
    Route::get('/reports/{id}/download', [LeaderReportsController::class, 'download']);
    Route::get('/leader/reports/{id}/download', [LeaderReportsController::class, 'download']);
    Route::get('/reports/{id}/file', [LeaderReportsController::class, 'downloadPdf']);
    Route::get('/leader/reports/{id}/file', [LeaderReportsController::class, 'downloadPdf']);

    // Activities (Scoped under /leader/ to avoid colliding with Peer/Member App APIs)
    Route::get('/leader/referrals', [LeaderActivitiesController::class, 'referrals']);
    Route::get('/leader/referral', [LeaderActivitiesController::class, 'referrals']);
    Route::post('/leader/referrals', [LeaderActivitiesController::class, 'storeReferral']);

    Route::get('/leader/testimonials', [LeaderActivitiesController::class, 'testimonials']);
    Route::get('/leader/testimonial', [LeaderActivitiesController::class, 'testimonials']);
    Route::post('/leader/testimonials', [LeaderActivitiesController::class, 'storeTestimonial']);

    Route::get('/leader/peers-by-coins', [LeaderActivitiesController::class, 'peersByCoins']);
    Route::get('/leader/coins', [LeaderActivitiesController::class, 'peersByCoins']);

    Route::get('/leader/impacts', [LeaderActivitiesController::class, 'impacts']);
    Route::get('/leader/life-impacts', [LeaderActivitiesController::class, 'impacts']);
    Route::get('/leader/impact', [LeaderActivitiesController::class, 'impacts']);
    Route::post('/leader/impacts', [LeaderActivitiesController::class, 'storeImpact']);

    Route::get('/leader/p2p-meetings', [LeaderActivitiesController::class, 'p2pMeetings']);
    Route::get('/leader/peer-meetings', [LeaderActivitiesController::class, 'p2pMeetings']);
    Route::get('/leader/p2p-meeting', [LeaderActivitiesController::class, 'p2pMeetings']);
    Route::post('/leader/p2p-meetings', [LeaderActivitiesController::class, 'storeP2pMeeting']);
    Route::post('/leader/peers/p2p-meetings', [LeaderPeersController::class, 'storeP2pMeeting']);

    Route::get('/leader/business-deals', [LeaderActivitiesController::class, 'businessDeals']);
    Route::get('/leader/business-deal', [LeaderActivitiesController::class, 'businessDeals']);
    Route::post('/leader/business-deals', [LeaderActivitiesController::class, 'storeBusinessDeal']);

    Route::get('/leader/requirements', [LeaderActivitiesController::class, 'requirements']);
    Route::get('/leader/requirement', [LeaderActivitiesController::class, 'requirements']);

    // Unified Leader User-Input Activities Feed by Type (Visitor Registrations, Recommendations, Asks, Posts, Creatives, Coin Claims, Stories, Leadership Interests, Mentors, Speakers, Partners, Certifications, Events)
    Route::get('/leader/activities/{type}', [LeaderActivitiesController::class, 'activitiesByType']);
    Route::get('/activities/{type}', [LeaderActivitiesController::class, 'activitiesByType']);

    // Notifications (Leader App)
    Route::get('/leader/notifications', [LeaderNotificationsController::class, 'index']);
    Route::post('/leader/notifications/mark-read', [LeaderNotificationsController::class, 'markRead']);
    Route::post('/leader/notifications/mark-all-read', [LeaderNotificationsController::class, 'markAllRead']);
    Route::post('/leader/notifications/mark-read-all', [LeaderNotificationsController::class, 'markAllRead']);
    Route::get('/leader/notifications/unread-count', [LeaderNotificationsController::class, 'unreadCount']);
    Route::post('/leader/notifications/{id}/read', [LeaderNotificationsController::class, 'markReadSingle'])->whereUuid('id');
    Route::post('/leader/notifications/read-all', [LeaderNotificationsController::class, 'markAllRead']);

    // Tab 5: Role & Permission Management
    Route::get('/roles/matrix', [LeaderRoleManagementController::class, 'matrix']);
    Route::put('/roles/matrix', [LeaderRoleManagementController::class, 'updateMatrix']);
    Route::post('/roles', [LeaderRoleManagementController::class, 'store']);
    Route::put('/roles/{id}', [LeaderRoleManagementController::class, 'update'])->whereUuid('id');
    Route::delete('/roles/{id}', [LeaderRoleManagementController::class, 'destroy'])->whereUuid('id');
    Route::get('/leader/roles/matrix', [LeaderRoleManagementController::class, 'matrix']);
    Route::put('/leader/roles/matrix', [LeaderRoleManagementController::class, 'updateMatrix']);
    Route::post('/leader/roles', [LeaderRoleManagementController::class, 'store']);
    Route::put('/leader/roles/{id}', [LeaderRoleManagementController::class, 'update'])->whereUuid('id');
    Route::delete('/leader/roles/{id}', [LeaderRoleManagementController::class, 'destroy'])->whereUuid('id');

    // ── Member 360° APIs ─────────────────────────────────────────────────────
    Route::get('/leader/members/{member_id}', [LeaderMemberController::class, 'show'])->whereUuid('member_id');
    Route::get('/leader/members/{member_id}/activities', [LeaderMemberController::class, 'activities'])->whereUuid('member_id');
    Route::get('/leader/members/{member_id}/posts', [LeaderMemberController::class, 'posts'])->whereUuid('member_id');
    Route::delete('/leader/members/{member_id}/posts/{post_id}', [LeaderMemberController::class, 'deletePost'])->whereUuid('member_id')->whereUuid('post_id');
    Route::get('/leader/members/{member_id}/creatives', [LeaderMemberController::class, 'creatives'])->whereUuid('member_id');
    Route::delete('/leader/members/{member_id}/creatives/{creative_id}', [LeaderMemberController::class, 'deleteCreative'])->whereUuid('member_id')->whereUuid('creative_id');
    Route::get('/leader/members/{member_id}/badges', [LeaderMemberController::class, 'badges'])->whereUuid('member_id');
    Route::get('/leader/members/{member_id}/events', [LeaderMemberController::class, 'events'])->whereUuid('member_id');
    Route::get('/leader/members/{member_id}/event-registrations', [LeaderMemberController::class, 'eventRegistrations'])->whereUuid('member_id');

    // Specific Activity Type APIs
    Route::get('/leader/members/{member_id}/referrals', [LeaderMemberController::class, 'referrals'])->whereUuid('member_id');
    Route::get('/leader/members/{member_id}/testimonials', [LeaderMemberController::class, 'testimonials'])->whereUuid('member_id');
    Route::get('/leader/members/{member_id}/p2p-meetings', [LeaderMemberController::class, 'p2pMeetings'])->whereUuid('member_id');
    Route::get('/leader/members/{member_id}/business-deals', [LeaderMemberController::class, 'businessDeals'])->whereUuid('member_id');
    Route::get('/leader/members/{member_id}/requirements', [LeaderMemberController::class, 'requirements'])->whereUuid('member_id');
    Route::get('/leader/members/{member_id}/impacts', [LeaderMemberController::class, 'impacts'])->whereUuid('member_id');
    Route::get('/leader/members/{member_id}/attendance', [LeaderMemberController::class, 'attendance'])->whereUuid('member_id');
    Route::get('/leader/members/{member_id}/coins', [LeaderMemberController::class, 'coins'])->whereUuid('member_id');
    Route::get('/leader/members/{member_id}/introduced-peers', [LeaderMemberController::class, 'introducedPeers'])->whereUuid('member_id');
    Route::get('/members/{member_id}/introduced-peers', [LeaderMemberController::class, 'introducedPeers'])->whereUuid('member_id');

    // ── 3 Core Ask Flows: Discovery, Master Data & Configuration ────────────
    Route::prefix('/leader/asks')->group(function (): void {
        Route::get('summary', [LeaderAskFlowController::class, 'summary']);
        Route::get('all-data', [LeaderAskFlowController::class, 'allData']);
        Route::get('flows', [LeaderAskFlowController::class, 'flows']);
        Route::get('flows/{flow}', [LeaderAskFlowController::class, 'flowDetails'])->whereIn('flow', ['collaboration', 'referral', 'help']);
        Route::get('form-config', [LeaderAskFlowController::class, 'formConfig']);
        Route::get('my', [AskFlowHubController::class, 'myAllAsks']);
    });

    // 3 DEDICATED ASKS FLOWS: CORE ENDPOINTS (Global Feed, My History, User History, Leaderboard, Detail)
    Route::prefix('/leader/asks/{flow}')->whereIn('flow', ['collaboration', 'referral', 'help', 'all'])->group(function (): void {
        Route::get('global', [AskFlowHubController::class, 'globalFeed']);
        Route::get('my', [AskFlowHubController::class, 'myAsks']);
        Route::get('user/{userId}', [AskFlowHubController::class, 'userAsks'])->whereUuid('userId');
        Route::get('leaderboard', [AskFlowHubController::class, 'leaderboard']);
        Route::get('{id}', [AskFlowHubController::class, 'showAsk'])->whereUuid('id');
    });

    // ── Leader Pending Approvals, Leads & Circulars ─────────────────────────
    foreach (['leader', 'leaders'] as $lp) {
        Route::get("/{$lp}/pending-requests", [PendingRequestAdminController::class, 'index']);
        Route::get("/{$lp}/pending-requests/summary", [PendingRequestAdminController::class, 'summary']);
        Route::post("/{$lp}/pending-requests/{category}/{id}/approve", [PendingRequestAdminController::class, 'approve']);
        Route::post("/{$lp}/pending-requests/{id}/approve", [PendingRequestAdminController::class, 'approveDirect']);
        Route::post("/{$lp}/pending-requests/{category}/{id}/reject", [PendingRequestAdminController::class, 'reject']);
        Route::post("/{$lp}/pending-requests/{id}/reject", [PendingRequestAdminController::class, 'rejectDirect']);

        Route::get("/{$lp}/leads/counts", [LeadAdminApiController::class, 'counts']);
        Route::get("/{$lp}/leads/{type}", [LeadAdminApiController::class, 'index']);

        Route::get("/{$lp}/circulars", [CircularController::class, 'index']);
        Route::get("/{$lp}/circulars/{id}", [CircularController::class, 'show']);
    }

    // ── /leaders/... Mirror Routes for Full Compatibility ───────────────────
    Route::prefix('leaders')->group(function (): void {
        Route::get('profile', [LeaderAuthController::class, 'profile']);
        Route::put('profile', [LeaderAuthController::class, 'updateProfile']);
        Route::post('profile/avatar', [LeaderAuthController::class, 'uploadAvatar']);
        Route::post('auth/profile/avatar', [LeaderAuthController::class, 'uploadAvatar']);

        Route::get('dashboard/metrics', [LeaderDashboardController::class, 'metrics']);
        Route::get('dashboard/top-impacters', [LeaderDashboardController::class, 'topImpacters']);

        Route::get('peers', [LeaderPeersController::class, 'index']);
        Route::get('peers/celebrations', [LeaderPeersController::class, 'celebrations']);
        Route::post('peers/p2p-meetings', [LeaderPeersController::class, 'storeP2pMeeting']);
        Route::get('peers/{id}/meetings', [LeaderPeersController::class, 'meetings']);
        Route::get('peers/{id}/activities', [LeaderPeersController::class, 'activities']);
        Route::get('peers/{id}', [LeaderPeersController::class, 'show'])->whereUuid('id');
        Route::post('peers/{id}/send-wish', [LeaderPeersController::class, 'sendWish']);

        Route::get('teams/summary', [LeaderTeamsController::class, 'summary']);
        Route::get('teams/industries', [LeaderTeamsController::class, 'industries']);
        Route::get('teams/circles', [LeaderTeamsController::class, 'circles']);
        Route::get('teams/circles/{circle_id}/peers', [LeaderTeamsController::class, 'circlePeers'])->whereUuid('circle_id');
        Route::get('teams/circles/{id}', [LeaderTeamsController::class, 'showCircle'])->whereUuid('id');
        Route::get('teams/circles/{id}/sub-industries', [LeaderTeamsController::class, 'subIndustries']);
        Route::get('teams/circles/{id}/events', [LeaderTeamsController::class, 'events']);

        Route::get('finance/metrics', [LeaderFinanceController::class, 'metrics']);
        Route::get('finance/transactions', [LeaderFinanceController::class, 'transactions']);
        Route::put('finance/commission-rates', [LeaderFinanceController::class, 'updateCommissionRates']);
        Route::post('finance/transactions/record-offline', [LeaderFinanceController::class, 'recordOfflinePayment']);

        Route::get('reports', [LeaderReportsController::class, 'index']);
        Route::post('reports', [LeaderReportsController::class, 'store']);
        Route::get('reports/attendance-trend', [LeaderReportsController::class, 'attendanceTrend']);
        Route::get('reports/{id}', [LeaderReportsController::class, 'show']);
        Route::get('reports/{id}/download', [LeaderReportsController::class, 'download']);
        Route::get('reports/{id}/file', [LeaderReportsController::class, 'downloadPdf']);

        Route::get('referrals', [LeaderActivitiesController::class, 'referrals']);
        Route::post('referrals', [LeaderActivitiesController::class, 'storeReferral']);
        Route::get('testimonials', [LeaderActivitiesController::class, 'testimonials']);
        Route::post('testimonials', [LeaderActivitiesController::class, 'storeTestimonial']);
        Route::get('peers-by-coins', [LeaderActivitiesController::class, 'peersByCoins']);
        Route::get('impacts', [LeaderActivitiesController::class, 'impacts']);
        Route::post('impacts', [LeaderActivitiesController::class, 'storeImpact']);
        Route::get('p2p-meetings', [LeaderActivitiesController::class, 'p2pMeetings']);
        Route::post('p2p-meetings', [LeaderActivitiesController::class, 'storeP2pMeeting']);
        Route::get('business-deals', [LeaderActivitiesController::class, 'businessDeals']);
        Route::post('business-deals', [LeaderActivitiesController::class, 'storeBusinessDeal']);
        Route::get('requirements', [LeaderActivitiesController::class, 'requirements']);
        Route::get('activities/{type}', [LeaderActivitiesController::class, 'activitiesByType']);

        Route::get('roles/matrix', [LeaderRoleManagementController::class, 'matrix']);
        Route::put('roles/matrix', [LeaderRoleManagementController::class, 'updateMatrix']);
        Route::post('roles', [LeaderRoleManagementController::class, 'store']);
        Route::put('roles/{id}', [LeaderRoleManagementController::class, 'update'])->whereUuid('id');
        Route::delete('roles/{id}', [LeaderRoleManagementController::class, 'destroy'])->whereUuid('id');
    });
});
