<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Leadership\AdminCampaignController;
use App\Http\Controllers\Api\V1\Leadership\AdminNominationController;
use App\Http\Controllers\Api\V1\Leadership\AdminScopeController;
use App\Http\Controllers\Api\V1\Leadership\AuditController;
use App\Http\Controllers\Api\V1\Leadership\CreativeController;
use App\Http\Controllers\Api\V1\Leadership\FinalDecisionController;
use App\Http\Controllers\Api\V1\Leadership\FormBuilderController;
use App\Http\Controllers\Api\V1\Leadership\JuryAssignmentController;
use App\Http\Controllers\Api\V1\Leadership\JuryEvaluationController;
use App\Http\Controllers\Api\V1\Leadership\NominationController;
use App\Http\Controllers\Api\V1\Leadership\NotificationController;
use App\Http\Controllers\Api\V1\Leadership\PublicCampaignController;
use App\Http\Controllers\Api\V1\Leadership\ReportController;
use App\Http\Controllers\Api\V1\Leadership\VerificationController;
use App\Http\Controllers\Api\V1\Leadership\VotingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Leadership Selection Management System API Routes
| Base Prefix: /api/v1/leadership
|--------------------------------------------------------------------------
*/

// ==========================================
// 1. PUBLIC ROUTES (Website & Verification)
// ==========================================

Route::prefix('public')->group(function (): void {
    // Module A: Public Campaign Discovery
    Route::get('campaigns', [PublicCampaignController::class, 'index']);
    Route::get('campaigns/{id}', [PublicCampaignController::class, 'show'])->whereUuid('id');
    Route::get('campaigns/{id}/scopes', [PublicCampaignController::class, 'scopes'])->whereUuid('id');
    Route::get('campaigns/{id}/candidates', [PublicCampaignController::class, 'candidates'])->whereUuid('id');
    Route::get('campaigns/{id}/winners', [PublicCampaignController::class, 'winners'])->whereUuid('id');

    // Module B: OTP & Verification
    Route::post('verification/nomination/request-otp', [VerificationController::class, 'requestNominationOtp']);
    Route::post('verification/nomination/verify-otp', [VerificationController::class, 'verifyNominationOtp']);
    Route::post('verification/voting/request-otp', [VerificationController::class, 'requestVotingOtp']);
    Route::post('verification/voting/verify-otp', [VerificationController::class, 'verifyVotingOtp']);
    Route::post('results/verify-otp', [VerificationController::class, 'verifyResultOtp']);

    // Module C: Public Nomination Form & Submissions
    Route::get('campaigns/{id}/nomination-form', [NominationController::class, 'form'])->whereUuid('id');
    Route::post('nomination/profile', [NominationController::class, 'profile']);
    Route::post('nominations/draft', [NominationController::class, 'saveDraft']);
    Route::get('nominations/{id}/draft', [NominationController::class, 'getDraft'])->whereUuid('id');
    Route::put('nominations/{id}/draft', [NominationController::class, 'updateDraft'])->whereUuid('id');
    Route::post('nominations/{id}/documents', [NominationController::class, 'uploadDocument'])->whereUuid('id');
    Route::delete('nominations/{id}/documents/{documentId}', [NominationController::class, 'deleteDocument'])->whereUuid('id')->whereUuid('documentId');
    Route::post('nominations/{id}/submit', [NominationController::class, 'submit'])->whereUuid('id');

    // Module G: Public Voting & Private Results
    Route::get('campaigns/{id}/voting-status', [VotingController::class, 'status'])->whereUuid('id');
    Route::get('campaigns/{id}/candidates/{candidateId}', [VotingController::class, 'candidateProfile'])->whereUuid('id')->whereUuid('candidateId');
    Route::post('votes', [VotingController::class, 'castVote']);
    Route::get('votes/{reference}', [VotingController::class, 'verifyReceipt']);
    Route::get('results', [VotingController::class, 'privateResults']);
});

// ==========================================
// 2. JUROR ROUTES (Sanctum Authenticated)
// ==========================================

Route::middleware(['auth:sanctum'])->prefix('juror')->group(function (): void {
    // Module H: Juror Dashboard & Assignments
    Route::get('assignments', [JuryAssignmentController::class, 'jurorAssignments']);
    Route::get('assignments/{id}', [JuryAssignmentController::class, 'jurorAssignmentDetails'])->whereUuid('id');
    Route::post('assignments/{id}/declare-conflict', [JuryAssignmentController::class, 'declareConflict'])->whereUuid('id');

    // Module I: Juror Evaluation & Scoring
    Route::get('assignments/{id}/evaluation-form', [JuryEvaluationController::class, 'form'])->whereUuid('id');
    Route::post('assignments/{id}/evaluation-draft', [JuryEvaluationController::class, 'saveDraft'])->whereUuid('id');
    Route::put('assignments/{id}/evaluation-draft', [JuryEvaluationController::class, 'updateDraft'])->whereUuid('id');
    Route::post('assignments/{id}/evaluation-submit', [JuryEvaluationController::class, 'submitForm'])->whereUuid('id');
    Route::get('assignments/{id}/criteria', [JuryEvaluationController::class, 'criteria'])->whereUuid('id');
    Route::post('assignments/{id}/scores', [JuryEvaluationController::class, 'saveScores'])->whereUuid('id');
    Route::post('assignments/{id}/report', [JuryEvaluationController::class, 'submitReport'])->whereUuid('id');
});

// ==========================================
// 3. ADMIN ROUTES (Admin Auth Guard)
// ==========================================

Route::middleware(['web', 'admin.auth'])->prefix('admin')->group(function (): void {
    // Module D: Campaign Management
    Route::get('campaigns', [AdminCampaignController::class, 'index']);
    Route::post('campaigns', [AdminCampaignController::class, 'store']);
    Route::get('campaigns/{id}', [AdminCampaignController::class, 'show'])->whereUuid('id');
    Route::put('campaigns/{id}', [AdminCampaignController::class, 'update'])->whereUuid('id');
    Route::post('campaigns/{id}/publish', [AdminCampaignController::class, 'publish'])->whereUuid('id');
    Route::post('campaigns/{id}/pause', [AdminCampaignController::class, 'pause'])->whereUuid('id');
    Route::post('campaigns/{id}/close', [AdminCampaignController::class, 'close'])->whereUuid('id');
    Route::post('campaigns/{id}/resume', [AdminCampaignController::class, 'resume'])->whereUuid('id');
    Route::post('campaigns/{id}/toggle-status', [AdminCampaignController::class, 'toggleStatus'])->whereUuid('id');
    Route::delete('campaigns/{id}', [AdminCampaignController::class, 'destroy'])->whereUuid('id');
    Route::get('roles', [AdminCampaignController::class, 'roles']);

    // Admin Scope CRUD
    Route::get('campaigns/{id}/scopes', [AdminScopeController::class, 'index'])->whereUuid('id');
    Route::post('campaigns/{id}/scopes', [AdminScopeController::class, 'store'])->whereUuid('id');
    Route::put('scopes/{id}', [AdminScopeController::class, 'update'])->whereUuid('id');
    Route::delete('scopes/{id}', [AdminScopeController::class, 'destroy'])->whereUuid('id');
    Route::delete('campaigns/{campaignId}/scopes/{id}', [AdminScopeController::class, 'destroy'])->whereUuid('campaignId')->whereUuid('id');

    // Module E: Form Builder
    Route::get('campaigns/{id}/forms', [FormBuilderController::class, 'index'])->whereUuid('id');
    Route::post('campaigns/{id}/forms', [FormBuilderController::class, 'store'])->whereUuid('id');
    Route::get('forms/templates', [FormBuilderController::class, 'allTemplates']);
    Route::post('forms/templates', [FormBuilderController::class, 'storeGlobal']);
    Route::get('forms/templates/{id}', [FormBuilderController::class, 'show'])->whereUuid('id');
    Route::put('forms/templates/{id}', [FormBuilderController::class, 'update'])->whereUuid('id');
    Route::post('forms/templates/{id}/publish', [FormBuilderController::class, 'publish'])->whereUuid('id');
    Route::get('forms/{id}', [FormBuilderController::class, 'show'])->whereUuid('id');
    Route::put('forms/{id}', [FormBuilderController::class, 'update'])->whereUuid('id');
    Route::post('forms/{id}/sections', [FormBuilderController::class, 'addSection'])->whereUuid('id');
    Route::post('forms/sections', [FormBuilderController::class, 'addSectionGeneric']);
    Route::put('forms/sections/{id}', [FormBuilderController::class, 'updateSectionGeneric'])->whereUuid('id');
    Route::delete('forms/sections/{id}', [FormBuilderController::class, 'deleteSectionGeneric'])->whereUuid('id');
    Route::post('sections/{id}/questions', [FormBuilderController::class, 'addQuestion'])->whereUuid('id');
    Route::post('forms/questions', [FormBuilderController::class, 'addQuestionGeneric']);
    Route::put('forms/questions/{id}', [FormBuilderController::class, 'updateQuestion'])->whereUuid('id');
    Route::delete('forms/questions/{id}', [FormBuilderController::class, 'deleteQuestionGeneric'])->whereUuid('id');
    Route::put('questions/{id}', [FormBuilderController::class, 'updateQuestion'])->whereUuid('id');
    Route::post('forms/{id}/publish', [FormBuilderController::class, 'publish'])->whereUuid('id');

    // Module F: Admin Nomination Review
    Route::get('nominations', [AdminNominationController::class, 'index']);
    Route::get('nominations/{id}', [AdminNominationController::class, 'show'])->whereUuid('id');
    Route::get('nominations/{id}/documents', [AdminNominationController::class, 'documents'])->whereUuid('id');
    Route::post('nominations/{id}/documents/{documentId}/verify', [AdminNominationController::class, 'verifyDocument'])->whereUuid('id')->whereUuid('documentId');
    Route::post('nominations/{id}/request-changes', [AdminNominationController::class, 'requestChanges'])->whereUuid('id');
    Route::post('nominations/{id}/request-correction', [AdminNominationController::class, 'requestChanges'])->whereUuid('id');
    Route::post('nominations/{id}/approve', [AdminNominationController::class, 'approve'])->whereUuid('id');
    Route::post('nominations/{id}/reject', [AdminNominationController::class, 'reject'])->whereUuid('id');
    Route::post('nominations/{id}/shortlist', [AdminNominationController::class, 'shortlist'])->whereUuid('id');
    Route::get('nominations/{id}/history', [AdminNominationController::class, 'history'])->whereUuid('id');

    // Module G: Admin Voting Management
    Route::post('campaigns/{id}/voting/open', [VotingController::class, 'openVoting'])->whereUuid('id');
    Route::post('campaigns/{id}/voting/close', [VotingController::class, 'closeVoting'])->whereUuid('id');
    Route::get('campaigns/{id}/voting/results', [VotingController::class, 'results'])->whereUuid('id');
    Route::get('voting/campaigns/{id}/tally', [VotingController::class, 'results'])->whereUuid('id');
    Route::get('voting/campaigns/{id}/summary', [VotingController::class, 'results'])->whereUuid('id');
    Route::post('nominations/{id}/results-link', [VotingController::class, 'generateResultLink'])->whereUuid('id');
    Route::post('voting/nominations/{id}/result-token', [VotingController::class, 'generateResultLink'])->whereUuid('id');

    // Module H: Admin Jury Management
    Route::get('jury/members', [JuryAssignmentController::class, 'members']);
    Route::post('nominations/{id}/jury-assignments', [JuryAssignmentController::class, 'assign'])->whereUuid('id');
    Route::get('nominations/{id}/jury-assignments', [JuryAssignmentController::class, 'candidateAssignments'])->whereUuid('id');
    Route::put('jury-assignments/{id}', [JuryAssignmentController::class, 'update'])->whereUuid('id');
    Route::post('jury-assignments/{id}/send-invitation', [JuryAssignmentController::class, 'sendInvitation'])->whereUuid('id');

    // Module I: Admin Jury Summary
    Route::get('nominations/{id}/jury-summary', [JuryEvaluationController::class, 'summary'])->whereUuid('id');

    // Module J: Final Decisions and Winners
    Route::get('campaigns/{id}/decision-candidates', [FinalDecisionController::class, 'candidates'])->whereUuid('id');
    Route::get('campaigns/{id}/decision-summary', [FinalDecisionController::class, 'summary'])->whereUuid('id');
    Route::get('decisions/campaigns/{id}', [FinalDecisionController::class, 'index'])->whereUuid('id');
    Route::post('decisions/campaigns/{id}/publish-winners', [FinalDecisionController::class, 'publishBatch'])->whereUuid('id');
    Route::post('campaigns/{id}/decisions', [FinalDecisionController::class, 'store'])->whereUuid('id');
    Route::put('decisions/{id}', [FinalDecisionController::class, 'update'])->whereUuid('id');
    Route::get('campaigns/{id}/decisions', [FinalDecisionController::class, 'index'])->whereUuid('id');
    Route::post('decisions/{id}/publish', [FinalDecisionController::class, 'publish'])->whereUuid('id');
    Route::post('decisions/{id}/unpublish', [FinalDecisionController::class, 'unpublish'])->whereUuid('id');
    Route::get('campaigns/{id}/winners', [FinalDecisionController::class, 'winners'])->whereUuid('id');

    // Module K: Winner Creative Management
    Route::get('creatives/templates', [CreativeController::class, 'templates']);
    Route::get('creatives/campaigns/{id}', [CreativeController::class, 'campaignCreatives'])->whereUuid('id');
    Route::post('decisions/{id}/creatives/generate', [CreativeController::class, 'generate'])->whereUuid('id');
    Route::get('decisions/{id}/creatives', [CreativeController::class, 'index'])->whereUuid('id');
    Route::get('creatives/{id}/download', [CreativeController::class, 'download'])->whereUuid('id');
    Route::post('creatives/{id}/publish', [CreativeController::class, 'publish'])->whereUuid('id');

    // Module L: Notifications and Audit
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/logs', [NotificationController::class, 'index']);
    Route::get('notifications/{id}', [NotificationController::class, 'show'])->whereUuid('id');
    Route::post('notifications/{id}/retry', [NotificationController::class, 'retry'])->whereUuid('id');
    Route::post('notifications/resend/{id}', [NotificationController::class, 'retry'])->whereUuid('id');
    Route::get('audit-logs', [AuditController::class, 'index']);
    Route::get('campaigns/{id}/audit-logs', [AuditController::class, 'campaignLogs'])->whereUuid('id');

    // Module M: Dashboard and Reporting
    Route::get('dashboard/overview', [ReportController::class, 'overview']);
    Route::get('reports/dashboard-overview', [ReportController::class, 'overview']);
    Route::get('reports/campaigns/{campaignId}/detailed', [ReportController::class, 'campaignDetailed'])->whereUuid('campaignId');
    Route::get('reports/nominations', [ReportController::class, 'nominations']);
    Route::get('reports/voting', [ReportController::class, 'voting']);
    Route::get('reports/jury', [ReportController::class, 'jury']);
    Route::get('reports/winners', [ReportController::class, 'winners']);
    Route::post('reports/export', [ReportController::class, 'export']);
});
