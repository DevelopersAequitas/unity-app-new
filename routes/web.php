<?php

use App\Http\Controllers\AccountDeletionController;
use App\Http\Controllers\Admin\ActivitiesBusinessDealsController;
use App\Http\Controllers\Admin\ActivitiesConnectionsController;
use App\Http\Controllers\Admin\ActivitiesController;
use App\Http\Controllers\Admin\ActivitiesLeaderInterestController;
use App\Http\Controllers\Admin\ActivitiesP2PMeetingsController;
use App\Http\Controllers\Admin\ActivitiesPeerRecommendationController;
use App\Http\Controllers\Admin\ActivitiesReferralsController;
use App\Http\Controllers\Admin\ActivitiesRequirementsController;
use App\Http\Controllers\Admin\ActivitiesTestimonialsController;
use App\Http\Controllers\Admin\ActivitiesVisitorRegistrationController;
use App\Http\Controllers\Admin\ActivityCreativeController;
use App\Http\Controllers\Admin\AdAnalyticsController;
use App\Http\Controllers\Admin\AdBookingAdminWebController;
use App\Http\Controllers\Admin\AdController;
use App\Http\Controllers\Admin\AdminCampaignController;
use App\Http\Controllers\Admin\AdminExecutionController;
use App\Http\Controllers\Admin\AdminFileUploadController;
use App\Http\Controllers\Admin\AnniversaryTemplateController;
use App\Http\Controllers\Admin\AppConfigPageController;
use App\Http\Controllers\Admin\AppNotificationAdminController;
use App\Http\Controllers\Admin\AppUpdatesController;
use App\Http\Controllers\Admin\Auth\AdminAuthController;
use App\Http\Controllers\Admin\BirthdayCreativeController;
use App\Http\Controllers\Admin\BrandPartnerAnalyticsController;
use App\Http\Controllers\Admin\BrandPartnerCategoryController;
use App\Http\Controllers\Admin\BrandPartnerController;
use App\Http\Controllers\Admin\BrandPartnerSettingsController;
use App\Http\Controllers\Admin\CampaignEmailTemplateController;
use App\Http\Controllers\Admin\CampaignPamphletController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CertificationSubmissionsController;
use App\Http\Controllers\Admin\CircleJoinRequestsController;
use App\Http\Controllers\Admin\CircleMemberDashboardController;
use App\Http\Controllers\Admin\CirclePeersController;
use App\Http\Controllers\Admin\Circles\CircleController;
use App\Http\Controllers\Admin\Circles\CircleMemberController;
use App\Http\Controllers\Admin\CircularController;
use App\Http\Controllers\Admin\CoinClaimsController;
use App\Http\Controllers\Admin\CoinsController;
use App\Http\Controllers\Admin\CollaborationPostController;
use App\Http\Controllers\Admin\ContactController;
use App\Http\Controllers\Admin\ContextSwitcherController;
use App\Http\Controllers\Admin\DailyNotificationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmailLogController;
use App\Http\Controllers\Admin\EmailTemplateController;
use App\Http\Controllers\Admin\EventCouponWebController;
use App\Http\Controllers\Admin\EventGalleryController;
use App\Http\Controllers\Admin\EventManagementController;
use App\Http\Controllers\Admin\EventScanCredentialController;
use App\Http\Controllers\Admin\ImpactsController;
use App\Http\Controllers\Admin\IndustryDirector\IndustryDirectorDashboardController;
use App\Http\Controllers\Admin\IntroductionRequestsController;
use App\Http\Controllers\Admin\LeadSubmissionsController;
use App\Http\Controllers\Admin\LifeImpactController;
use App\Http\Controllers\Admin\LifeImpactRecognitionsController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\LoginHistoryController;
use App\Http\Controllers\Admin\MemberIntroducersController;
use App\Http\Controllers\Admin\MembershipPlanController;
use App\Http\Controllers\Admin\MilestoneBadgeController;
use App\Http\Controllers\Admin\NotificationAdminController;
use App\Http\Controllers\Admin\NotificationTemplateController;
use App\Http\Controllers\Admin\PeerReferralsController;
use App\Http\Controllers\Admin\PendingRegistrationsController;
use App\Http\Controllers\Admin\PostModerationController;
use App\Http\Controllers\Admin\PostReportsController;
use App\Http\Controllers\Admin\Rbac\AdminModuleController;
use App\Http\Controllers\Admin\Rbac\AdminPageController;
use App\Http\Controllers\Admin\Rbac\PageGroupController;
use App\Http\Controllers\Admin\Rbac\RoleDataScopeController;
use App\Http\Controllers\Admin\Rbac\RoleHierarchyController;
use App\Http\Controllers\Admin\Rbac\RoleLifespanController;
use App\Http\Controllers\Admin\Rbac\RoleModuleAccessController;
use App\Http\Controllers\Admin\Rbac\RolePermissionMatrixController;
use App\Http\Controllers\Admin\Rbac\WorkflowApprovalRuleController;
use App\Http\Controllers\Admin\ReferralReportController;
use App\Http\Controllers\Admin\SponsoredMembersMilestonesWebController;
use App\Http\Controllers\Admin\StorySubmissionsController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\Admin\TutorialController;
use App\Http\Controllers\Admin\Users\UserSearchController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Admin\VisitorRegistrationsController;
use App\Http\Controllers\Api\V1\EventQrCodeController;
use App\Http\Controllers\PublicEventRegistrationFormController;
use App\Http\Controllers\ShareController;
use App\Services\Events\EventCheckinService;
use App\Support\AdminAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
});

Route::get('/congratulations', function () {
    return view('congratulations');
});

Route::get('/share', [ShareController::class, 'handle'])->name('share');

Route::get('/api/v1/event-qrcodes/{eventId}/{filename}', [EventQrCodeController::class, 'show'])->where('filename', '[^/]+');
Route::get('/event-qrcodes/{eventId}/{filename}', [EventQrCodeController::class, 'show'])->where('filename', '[^/]+');

Route::get('/api/v1/events/checkin/qr/{token}', function (Request $request, string $token) {
    $service = app(EventCheckinService::class);
    $registration = $service->registrationForToken($token);

    if (! $registration) {
        return response()->json([
            'success' => false,
            'message' => 'This QR Code is not valid or has expired.',
        ], 404);
    }

    return response()->json([
        'success' => true,
        'message' => 'QR Code is valid.',
        'data' => [
            'registration_id' => $registration->id,
            'event_id' => $registration->event_id,
            'occurrence_id' => $registration->occurrence_id,
            'user_id' => $registration->user_id,
            'checkin_status' => $registration->checkin_status,
            'status' => $registration->status,
            'payment_status' => $registration->payment_status,
        ],
    ]);
});
Route::get('/events/checkin/qr/{token}', function (Request $request, string $token) {
    return redirect('/api/v1/events/checkin/qr/'.$token);
});

Route::get('/events/{event}/occurrences/{occurrence}/visitor-register', [PublicEventRegistrationFormController::class, 'show'])
    ->whereUuid('event')
    ->whereUuid('occurrence')
    ->name('events.visitor-register');
Route::post('/events/{event}/occurrences/{occurrence}/visitor-register', [PublicEventRegistrationFormController::class, 'submit'])
    ->whereUuid('event')
    ->whereUuid('occurrence')
    ->name('events.visitor-register.submit');

Route::get('/account-deletion-request', [AccountDeletionController::class, 'show'])->name('account-deletion.show');
Route::post('/account-deletion-request', [AccountDeletionController::class, 'submit'])->name('account-deletion.submit');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login/send-otp', [AdminAuthController::class, 'requestOtp'])->name('login.send-otp');
    Route::post('/login/verify', [AdminAuthController::class, 'verifyOtp'])->name('login.verify');

    Route::middleware(['admin.auth', 'admin.role', 'admin.circle', 'admin.permission'])->group(function () {
        // RBAC Hierarchy & Profile management
        Route::get('/rbac/hierarchy', [RoleHierarchyController::class, 'index'])->name('rbac.hierarchy');
        Route::get('/rbac/hierarchy/map', [RoleHierarchyController::class, 'fullMap'])->name('rbac.hierarchy.fullmap');
        Route::post('/rbac/roles', [RoleHierarchyController::class, 'storeRole'])->name('rbac.roles.store');
        Route::post('/rbac/roles/update-parent', [RoleHierarchyController::class, 'updateParent'])->name('rbac.roles.update-parent');
        Route::post('/rbac/roles/clone', [RoleHierarchyController::class, 'cloneProfile'])->name('rbac.roles.clone');
        Route::put('/rbac/roles/{id}', [RoleHierarchyController::class, 'updateRole'])->name('rbac.roles.update')->whereUuid('id');
        Route::delete('/rbac/roles/{id}', [RoleHierarchyController::class, 'deleteRole'])->name('rbac.roles.delete')->whereUuid('id');
        Route::post('/rbac/roles/assign', [RoleHierarchyController::class, 'assignRole'])->name('rbac.roles.assign');
        Route::get('/rbac/roles/{id}/assignments', [RoleHierarchyController::class, 'getAssignments'])->name('rbac.roles.assignments')->whereUuid('id');
        Route::post('/rbac/roles/{id}/assignments', [RoleHierarchyController::class, 'assignPeer'])->name('rbac.roles.assign-peer')->whereUuid('id');
        Route::delete('/rbac/roles/{id}/assignments/{userId}', [RoleHierarchyController::class, 'removeAssignment'])->name('rbac.roles.remove-assignment')->whereUuid('id')->whereUuid('userId');
        Route::post('/switch-context', [ContextSwitcherController::class, 'switchContext'])->name('switch-context');
        Route::post('/profile/remove-current-role', [RoleHierarchyController::class, 'removeCurrentRole'])->name('profile.remove-current-role');

        // ── Dynamic RBAC Management ─────────────────────────────
        Route::prefix('rbac')->name('rbac.')->group(function () {
            // Module Management
            Route::get('/modules', [AdminModuleController::class, 'index'])->name('modules.index');
            Route::get('/modules/create', [AdminModuleController::class, 'create'])->name('modules.create');
            Route::post('/modules', [AdminModuleController::class, 'store'])->name('modules.store');
            Route::get('/modules/{id}/edit', [AdminModuleController::class, 'edit'])->name('modules.edit')->whereUuid('id');
            Route::put('/modules/{id}', [AdminModuleController::class, 'update'])->name('modules.update')->whereUuid('id');
            Route::delete('/modules/{id}', [AdminModuleController::class, 'destroy'])->name('modules.destroy')->whereUuid('id');
            Route::post('/modules/order', [AdminModuleController::class, 'updateOrder'])->name('modules.order');

            // Page Management
            Route::get('/pages', [AdminPageController::class, 'index'])->name('pages.index');
            Route::get('/pages/create', [AdminPageController::class, 'create'])->name('pages.create');
            Route::post('/pages', [AdminPageController::class, 'store'])->name('pages.store');
            Route::get('/pages/{id}/edit', [AdminPageController::class, 'edit'])->name('pages.edit')->whereUuid('id');
            Route::put('/pages/{id}', [AdminPageController::class, 'update'])->name('pages.update')->whereUuid('id');
            Route::delete('/pages/{id}', [AdminPageController::class, 'destroy'])->name('pages.destroy')->whereUuid('id');

            // Permission Matrix
            Route::get('/permission-matrix', [RolePermissionMatrixController::class, 'index'])->name('permission-matrix.index');
            Route::post('/permission-matrix', [RolePermissionMatrixController::class, 'update'])->name('permission-matrix.update');

            // Module Access
            Route::get('/module-access', [RoleModuleAccessController::class, 'index'])->name('module-access.index');
            Route::post('/module-access', [RoleModuleAccessController::class, 'update'])->name('module-access.update');

            // Page Groups
            Route::get('/page-groups', [PageGroupController::class, 'index'])->name('page-groups.index');
            Route::get('/page-groups/create', [PageGroupController::class, 'create'])->name('page-groups.create');
            Route::post('/page-groups', [PageGroupController::class, 'store'])->name('page-groups.store');
            Route::get('/page-groups/{id}/edit', [PageGroupController::class, 'edit'])->name('page-groups.edit')->whereUuid('id');
            Route::put('/page-groups/{id}', [PageGroupController::class, 'update'])->name('page-groups.update')->whereUuid('id');
            Route::delete('/page-groups/{id}', [PageGroupController::class, 'destroy'])->name('page-groups.destroy')->whereUuid('id');

            // Data Scope
            Route::get('/data-scope', [RoleDataScopeController::class, 'index'])->name('data-scope.index');
            Route::post('/data-scope', [RoleDataScopeController::class, 'store'])->name('data-scope.store');
            Route::delete('/data-scope/{id}', [RoleDataScopeController::class, 'destroy'])->name('data-scope.destroy')->whereUuid('id');

            // Workflow Approval Rules
            Route::get('/workflow-rules', [WorkflowApprovalRuleController::class, 'index'])->name('workflow-rules.index');
            Route::post('/workflow-rules', [WorkflowApprovalRuleController::class, 'store'])->name('workflow-rules.store');
            Route::put('/workflow-rules/{id}', [WorkflowApprovalRuleController::class, 'update'])->name('workflow-rules.update')->whereUuid('id');
            Route::delete('/workflow-rules/{id}', [WorkflowApprovalRuleController::class, 'destroy'])->name('workflow-rules.destroy')->whereUuid('id');

            // Role Lifespan & History
            Route::get('/lifespan', [RoleLifespanController::class, 'index'])->name('lifespan.index');
        });

        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::get('/', function () {
            $admin = auth('admin')->user();

            if (AdminAccess::isIndustryScoped($admin)) {
                return redirect()->route('admin.industry-director.dashboard');
            }

            if (AdminAccess::isDed($admin)) {
                return redirect()->route('admin.ded.dashboard');
            }

            if (AdminAccess::isCircleScoped($admin)) {
                return redirect()->route('admin.circle-member.dashboard');
            }

            return redirect()->route('admin.dashboard');
        })->name('home');

        // Tutorials routes
        Route::get('/tutorials', [TutorialController::class, 'index'])->name('tutorials.index');
        Route::post('/tutorials', [TutorialController::class, 'store'])->name('tutorials.store');
        Route::delete('/tutorials/{id}', [TutorialController::class, 'destroy'])->whereUuid('id')->name('tutorials.destroy');

        Route::get('/app-config', [AppConfigPageController::class, 'index'])->name('app-config.index');
        Route::get('/app-updates', [AppUpdatesController::class, 'index'])->name('app-updates.index');
        Route::post('/app-updates/save/{platform}', [AppUpdatesController::class, 'saveSettings'])->name('app-updates.save');
        Route::post('/app-updates/maintenance', [AppUpdatesController::class, 'saveMaintenance'])->name('app-updates.maintenance.save');
        Route::post('/app-updates/notify-selected', [AppUpdatesController::class, 'notifySelected'])->name('app-updates.notify-selected');
        Route::post('/app-updates/releases', [AppUpdatesController::class, 'storeRelease'])->name('app-updates.releases.store');
        Route::get('/birthday-creative', [BirthdayCreativeController::class, 'index'])->name('birthday-creative.index');
        Route::post('/birthday-creative', [BirthdayCreativeController::class, 'update'])->name('birthday-creative.update');
        Route::get('/birthday-creative/preview/{userId}', [BirthdayCreativeController::class, 'preview'])->name('birthday-creative.preview');
        Route::put('/app-config/branding', [AppConfigPageController::class, 'updateBranding'])->name('app-config.branding');
        Route::post('/app-config/upload-brand-asset', [AppConfigPageController::class, 'uploadBrandAsset'])->name('app-config.upload-brand-asset');
        Route::put('/app-config/labels', [AppConfigPageController::class, 'bulkLabels'])->name('app-config.labels');
        Route::put('/app-config/features', [AppConfigPageController::class, 'bulkFeatures'])->name('app-config.features');
        Route::put('/app-config/icons', [AppConfigPageController::class, 'bulkIcons'])->name('app-config.icons');
        Route::post('/app-config/icons/upload', [AppConfigPageController::class, 'uploadIconAsset'])->name('app-config.icons.upload');
        Route::post('/app-config/navigation', [AppConfigPageController::class, 'saveNavigation'])->name('app-config.navigation.store');
        Route::put('/app-config/navigation/{id}', [AppConfigPageController::class, 'saveNavigation'])->whereUuid('id')->name('app-config.navigation.update');
        Route::put('/app-config/navigation/group/{menu_type}', [AppConfigPageController::class, 'bulkUpdateNavigationGroup'])->name('app-config.navigation.group-update');
        Route::delete('/app-config/navigation/{id}', [AppConfigPageController::class, 'deleteNavigation'])->whereUuid('id')->name('app-config.navigation.destroy');
        Route::put('/app-config/dashboard-widgets', [AppConfigPageController::class, 'bulkWidgets'])->name('app-config.widgets');
        Route::put('/app-config/social-links', [AppConfigPageController::class, 'bulkSocial'])->name('app-config.social');
        Route::put('/app-config/membership-labels', [AppConfigPageController::class, 'membershipLabels'])->name('app-config.membership-labels');
        Route::post('/app-config/clear-cache', [AppConfigPageController::class, 'clearCache'])->name('app-config.clear-cache');
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/circle-member/dashboard', [CircleMemberDashboardController::class, 'index'])->name('circle-member.dashboard');
        Route::get('/ded-dashboard', [DashboardController::class, 'ded'])->name('ded.dashboard');
        Route::get('/ded-dashboard/leadership/{role}', [DashboardController::class, 'dedLeadershipDetail'])->name('ded.dashboard.leadership');
        Route::get('/ded-dashboard/health/active-members', [DashboardController::class, 'dedActiveMembersDetail'])->name('ded.dashboard.health.active-members');
        Route::get('/ded-dashboard/health/leadership-spots', [DashboardController::class, 'dedLeadershipSpotsDetail'])->name('ded.dashboard.health.leadership-spots');
        Route::get('/ded-dashboard/health/membership-conversion', [DashboardController::class, 'dedMembershipConversionDetail'])->name('ded.dashboard.health.membership-conversion');
        Route::get('/ded-dashboard/health/referral-activity', [DashboardController::class, 'dedReferralActivityDetail'])->name('ded.dashboard.health.referral-activity');
        Route::get('/ded-dashboard/industries', [DashboardController::class, 'dedIndustriesOverview'])->name('ded.dashboard.industries');
        Route::get('/ded-dashboard/industries/{id}', [DashboardController::class, 'dedIndustryDetail'])->name('ded.dashboard.industries.detail');
        Route::get('/ded/dashboard', fn () => redirect()->route('admin.ded.dashboard'))->name('ded.dashboard.legacy');
        Route::get('/location/states/{state}/districts', [LocationController::class, 'districts'])->whereUuid('state')->name('location.states.districts');
        Route::get('/industry-director/dashboard', [IndustryDirectorDashboardController::class, 'index'])
            ->middleware('admin.industry-director')
            ->name('industry-director.dashboard');
        Route::post('/industry-director/switch-industry', [IndustryDirectorDashboardController::class, 'switchIndustry'])
            ->middleware('admin.industry-director')
            ->name('industry-director.switch-industry');
        Route::get('/member-introducers', [MemberIntroducersController::class, 'index'])->name('member-introducers.index');
        Route::get('/member-introducers/{id}/introduced-peers', [MemberIntroducersController::class, 'introducedPeers'])->whereUuid('id')->name('member-introducers.introduced-peers');
        Route::get('/member-introducers/{id}/creative-preview', [MemberIntroducersController::class, 'creativePreview'])->whereUuid('id')->name('member-introducers.creative-preview');
        Route::post('/member-introducers/{id}/post-creative', [MemberIntroducersController::class, 'postCreativeToTimeline'])->whereUuid('id')->name('member-introducers.post-creative');
        Route::get('/life-impact-recognitions', [LifeImpactRecognitionsController::class, 'index'])->name('life-impact-recognitions.index');
        Route::get('/life-impact-recognitions/{id}/creative-preview', [LifeImpactRecognitionsController::class, 'creativePreview'])->whereUuid('id')->name('life-impact-recognitions.creative-preview');
        Route::post('/life-impact-recognitions/{id}/post-creative', [LifeImpactRecognitionsController::class, 'postCreativeToTimeline'])->whereUuid('id')->name('life-impact-recognitions.post-creative');
        Route::get('/milestone-badges', [MilestoneBadgeController::class, 'index'])->name('milestone-badges.index');
        Route::get('/milestone-badges/create', [MilestoneBadgeController::class, 'create'])->name('milestone-badges.create');
        Route::post('/milestone-badges', [MilestoneBadgeController::class, 'store'])->name('milestone-badges.store');
        Route::get('/milestone-badges/{badge}/edit', [MilestoneBadgeController::class, 'edit'])->whereUuid('badge')->name('milestone-badges.edit');
        Route::put('/milestone-badges/{badge}', [MilestoneBadgeController::class, 'update'])->whereUuid('badge')->name('milestone-badges.update');
        Route::delete('/milestone-badges/{badge}', [MilestoneBadgeController::class, 'destroy'])->whereUuid('badge')->name('milestone-badges.destroy');
        Route::post('/milestone-badges/{badge}/toggle-status', [MilestoneBadgeController::class, 'toggleStatus'])->whereUuid('badge')->name('milestone-badges.toggle-status');
        Route::get('/sponsored-milestones', [SponsoredMembersMilestonesWebController::class, 'index'])->name('sponsored-milestones.index');
        Route::get('/sponsored-milestones/{user}', [SponsoredMembersMilestonesWebController::class, 'show'])
            ->whereUuid('user')
            ->name('sponsored-milestones.show');
        Route::get('/users', [UsersController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UsersController::class, 'create'])->name('users.create');
        Route::post('/users', [UsersController::class, 'store'])->name('users.store');
        Route::get('/users/search', UserSearchController::class)->name('users.search');
        Route::get('/users/import', [UsersController::class, 'importForm'])->name('users.import');
        Route::post('/users/import', [UsersController::class, 'import'])->name('users.import.submit');
        Route::post('/users/export/csv', [UsersController::class, 'exportCsv'])->name('users.export.csv');
        Route::get('/users/upcoming-events', [UsersController::class, 'upcomingEvents'])->name('users.upcoming-events');
        Route::get('/users/{user}', [UsersController::class, 'show'])->withTrashed()->whereUuid('user')->name('users.show');
        Route::post('/users/bulk-approve-membership', [UsersController::class, 'bulkApproveMembership'])->name('users.bulk-approve-membership');
        Route::post('/users/{user}/approve-membership', [UsersController::class, 'approveMembership'])->withTrashed()->whereUuid('user')->name('users.approve-membership');
        Route::get('/users/{user}/edit', [UsersController::class, 'edit'])->withTrashed()->whereUuid('user')->name('users.edit');
        Route::match(['put', 'post'], '/users/{user}', [UsersController::class, 'update'])->withTrashed()->whereUuid('user')->name('users.update');
        Route::delete('/users/{user}', [UsersController::class, 'destroy'])
            ->withTrashed()
            ->whereUuid('user')
            ->name('users.destroy');
        Route::delete('/users/{user}/circle-members/{circleMember}', [UsersController::class, 'removeCircleMembership'])->withTrashed()->name('users.circle-members.destroy');
        Route::match(['put', 'post'], '/users/{user}/circle-members/{circleMember}', [UsersController::class, 'updateCircleMembership'])->withTrashed()->name('users.circle-members.update');
        Route::post('/users/{user}/roles/remove', [UsersController::class, 'removeRole'])->withTrashed()->name('users.roles.remove');
        Route::post('/users/{user}/membership-welcome-email/send', [UsersController::class, 'sendWelcomeMembershipEmail'])->withTrashed()->name('users.membership-welcome-email.send');
        Route::post('/users/{user}/trigger-membership-notification', [UsersController::class, 'triggerMembershipNotification'])->withTrashed()->name('users.trigger-membership-notification');
        Route::post('/users/{user}/introduced-members', [UsersController::class, 'addIntroducedMember'])
            ->withTrashed()
            ->whereUuid('user')
            ->name('users.introduced-members.store');
        Route::delete('/users/{user}/introduced-members/{introducedMember}', [UsersController::class, 'removeIntroducedMember'])
            ->withTrashed()
            ->whereUuid('user')
            ->whereUuid('introducedMember')
            ->name('users.introduced-members.destroy');

        // Story Submissions Admin
        Route::get('/stories', [StorySubmissionsController::class, 'index'])->name('stories.index');
        Route::post('/stories/{id}/approve', [StorySubmissionsController::class, 'approve'])->name('stories.approve')->whereUuid('id');
        Route::post('/stories/{id}/reject', [StorySubmissionsController::class, 'reject'])->name('stories.reject')->whereUuid('id');

        Route::get('/activities', [ActivitiesController::class, 'index'])->name('activities.index');
        Route::get('/activities/peer-summary/{user}', [ActivitiesController::class, 'peerSummary'])->name('activities.peer-summary');
        Route::get('/activities/peer-details/{user}/{type}', [ActivitiesController::class, 'peerActivityDetails'])->name('activities.peer-details');
        Route::post('/activities/export', [ActivitiesController::class, 'export'])->name('activities.export');
        Route::get('/activities/testimonials', [ActivitiesTestimonialsController::class, 'index'])->name('activities.testimonials.index');
        Route::get('/activities/testimonials/export', [ActivitiesTestimonialsController::class, 'export'])->name('activities.testimonials.export');
        Route::get('/activities/requirements', [ActivitiesRequirementsController::class, 'index'])->name('activities.requirements.index');
        Route::get('/activities/requirements/export', [ActivitiesRequirementsController::class, 'export'])->name('activities.requirements.export');
        Route::get('/activities/referrals', [ActivitiesReferralsController::class, 'index'])->name('activities.referrals.index');
        Route::get('/activities/referrals/export', [ActivitiesReferralsController::class, 'export'])->name('activities.referrals.export');
        Route::get('/referral-report', [ReferralReportController::class, 'index'])->name('referral-report.index');
        Route::get('/referral-report/export', [ReferralReportController::class, 'export'])->name('referral-report.export');
        Route::get('/referral-report/{referrer_user_id}', [ReferralReportController::class, 'show'])->name('referral-report.show');
        Route::get('/activities/p2p-meetings', [ActivitiesP2PMeetingsController::class, 'index'])->name('activities.p2p-meetings.index');
        Route::get('/activities/p2p-meetings/export', [ActivitiesP2PMeetingsController::class, 'export'])->name('activities.p2p-meetings.export');
        Route::get('/activities/business-deals', [ActivitiesBusinessDealsController::class, 'index'])->name('activities.business-deals.index');
        Route::get('/activities/business-deals/export', [ActivitiesBusinessDealsController::class, 'export'])->name('activities.business-deals.export');
        Route::get('/activities/connections', [ActivitiesConnectionsController::class, 'index'])->name('activities.connections.index');
        Route::get('/activities/connections/export', [ActivitiesConnectionsController::class, 'export'])->name('activities.connections.export');
        Route::get('/activity-creatives', [ActivityCreativeController::class, 'index'])->name('activity-creatives.index');
        Route::get('/activities/become-a-leader', [ActivitiesLeaderInterestController::class, 'index'])->name('activities.become-a-leader.index');
        Route::get('/activities/recommend-peer', [ActivitiesPeerRecommendationController::class, 'index'])->name('activities.recommend-peer.index');
        Route::get('/activities/register-visitor', [ActivitiesVisitorRegistrationController::class, 'index'])->name('activities.register-visitor.index');
        Route::get('/collaborations', [CollaborationPostController::class, 'index'])->name('collaborations.index');
        Route::get('/collaborations/export', [CollaborationPostController::class, 'export'])->name('collaborations.export');
        Route::get('/collaborations/{id}', [CollaborationPostController::class, 'show'])->name('collaborations.show');
        Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
        Route::get('/contacts/import', [ContactController::class, 'import'])->name('contacts.import');
        Route::post('/contacts/import', [ContactController::class, 'importStore'])->name('contacts.import.store');
        Route::get('/contacts/export', [ContactController::class, 'export'])->name('contacts.export');
        Route::get('/contacts/user/{user_id}/export', [ContactController::class, 'exportUserDetails'])->name('contacts.user-details.export');
        Route::post('/contacts/user/{user_id}/export-selected', [ContactController::class, 'exportSelected'])->name('contacts.user-details.export-selected');
        Route::get('/contacts/user/{user_id}', [ContactController::class, 'userDetails'])->name('contacts.user-details');
        Route::get('/contacts/{id}', [ContactController::class, 'show'])->name('contacts.show');
        Route::get('/activities/{peer}/become-a-leader', [ActivitiesLeaderInterestController::class, 'show'])
            ->whereUuid('peer')
            ->name('activities.become-a-leader.show');
        Route::get('/activities/{peer}/recommend-peer', [ActivitiesPeerRecommendationController::class, 'show'])
            ->whereUuid('peer')
            ->name('activities.recommend-peer.show');
        Route::get('/activities/{peer}/register-visitor', [ActivitiesVisitorRegistrationController::class, 'show'])
            ->whereUuid('peer')
            ->name('activities.register-visitor.show');
        Route::get('/activities/{member}/testimonials', [ActivitiesController::class, 'testimonials'])->name('activities.testimonials');
        Route::get('/activities/{member}/referrals', [ActivitiesController::class, 'referrals'])->name('activities.referrals');
        Route::get('/activities/{member}/business-deals', [ActivitiesController::class, 'businessDeals'])->name('activities.business-deals');
        Route::get('/activities/{member}/p2p-meetings', [ActivitiesController::class, 'p2pMeetings'])->name('activities.p2p-meetings');
        Route::get('/activities/{member}/requirements', [ActivitiesController::class, 'requirements'])->name('activities.requirements');
        Route::get('/coins', [CoinsController::class, 'index'])->name('coins.index');
        Route::get('/coins/export', [CoinsController::class, 'exportIndex'])->name('coins.export');
        Route::get('/life-impact', [LifeImpactController::class, 'index'])
            ->name('life-impact.index');
        Route::get('/life-impact/export', [LifeImpactController::class, 'export'])
            ->name('life-impact.export');
        Route::get('/life-impact/{member}/history', [LifeImpactController::class, 'history'])
            ->name('life-impact.history');
        Route::get('/life-impact/{member}/history/{category}', [LifeImpactController::class, 'history'])
            ->name('life-impact.history.category');
        Route::get('/coins/add', [CoinsController::class, 'create'])->name('coins.create');
        Route::post('/coins/add', [CoinsController::class, 'store'])->name('coins.store');
        Route::get('/coins/{member}/ledger', [CoinsController::class, 'ledger'])->name('coins.ledger');
        Route::get('/coins/{member}/ledger/{type}', [CoinsController::class, 'ledgerByType'])->name('coins.ledger.type');
        Route::get('/coins/{member}/ledger-export', [CoinsController::class, 'exportLedger'])->name('coins.ledger.export');
        Route::get('/unity-peers-plans', [MembershipPlanController::class, 'index'])->name('unity-peers-plans.index');
        Route::get('/unity-peers-plans/create', [MembershipPlanController::class, 'create'])->name('unity-peers-plans.create');
        Route::post('/unity-peers-plans', [MembershipPlanController::class, 'store'])->name('unity-peers-plans.store');
        Route::get('/unity-peers-plans/{plan}/edit', [MembershipPlanController::class, 'edit'])->name('unity-peers-plans.edit');
        Route::get('/login-history', [LoginHistoryController::class, 'index'])->name('login-history.index');
        Route::put('/unity-peers-plans/{plan}', [MembershipPlanController::class, 'update'])->name('unity-peers-plans.update');
        Route::post('/files/upload', [AdminFileUploadController::class, 'upload'])->name('files.upload');

        Route::get('/circulars', [CircularController::class, 'index'])->name('circulars.index');

        Route::get('/anniversary-creatives', [AnniversaryTemplateController::class, 'index'])->name('anniversary-creatives.index');
        Route::post('/anniversary-creatives', [AnniversaryTemplateController::class, 'store'])->name('anniversary-creatives.store');
        Route::post('/anniversary-creatives/{template}/toggle', [AnniversaryTemplateController::class, 'toggleActive'])->name('anniversary-creatives.toggle');
        Route::delete('/anniversary-creatives/{template}', [AnniversaryTemplateController::class, 'destroy'])->name('anniversary-creatives.destroy');
        Route::get('/anniversary-creatives/preview/{userId}', [AnniversaryTemplateController::class, 'preview'])->name('anniversary-creatives.preview');
        Route::get('/circulars/create', [CircularController::class, 'create'])->name('circulars.create');
        Route::post('/circulars', [CircularController::class, 'store'])->name('circulars.store');
        Route::get('/circulars/{circular}', [CircularController::class, 'show'])->name('circulars.show');
        Route::get('/circulars/{circular}/edit', [CircularController::class, 'edit'])->name('circulars.edit');
        Route::put('/circulars/{circular}', [CircularController::class, 'update'])->name('circulars.update');
        Route::delete('/circulars/{circular}', [CircularController::class, 'destroy'])->name('circulars.destroy');

        Route::get('/circles', [CircleController::class, 'index'])->name('circles.index');
        Route::get('/circles/create', [CircleController::class, 'create'])->name('circles.create');
        Route::post('/circles', [CircleController::class, 'store'])->name('circles.store');
        Route::get('/circles/{circle}', [CircleController::class, 'show'])->name('circles.show');
        Route::get('/circles/{circle}/edit', [CircleController::class, 'edit'])->name('circles.edit');
        Route::put('/circles/{circle}', [CircleController::class, 'update'])->name('circles.update');
        Route::delete('/circles/{circle}', [CircleController::class, 'destroy'])->name('circles.destroy');
        Route::post('/circles/{circle}/members', [CircleMemberController::class, 'store'])->name('circles.members.store');
        Route::get('/circles/{circle}/delete-stats', [CircleController::class, 'deleteStats'])->name('circles.delete-stats');
        Route::get('/circles/{circle}/peer-options', [CirclePeersController::class, 'peerOptions'])->name('circles.peer-options');
        Route::put('/circles/{circle}/members/{circleMember}', [CircleMemberController::class, 'update'])->name('circles.members.update');
        Route::delete('/circles/{circle}/members/{circleMember}', [CircleMemberController::class, 'destroy'])->name('circles.members.destroy');
        Route::get('/event-scan-credentials', [EventScanCredentialController::class, 'index'])->name('event-scan-credentials.index');
        Route::get('/event-scan-credentials/create', [EventScanCredentialController::class, 'create'])->name('event-scan-credentials.create');
        Route::post('/event-scan-credentials', [EventScanCredentialController::class, 'store'])->name('event-scan-credentials.store');
        Route::get('/event-scan-credentials/{eventScanCredential}/edit', [EventScanCredentialController::class, 'edit'])->name('event-scan-credentials.edit');
        Route::put('/event-scan-credentials/{eventScanCredential}', [EventScanCredentialController::class, 'update'])->name('event-scan-credentials.update');
        Route::post('/event-scan-credentials/{eventScanCredential}/toggle', [EventScanCredentialController::class, 'toggle'])->name('event-scan-credentials.toggle');
        Route::post('/event-scan-credentials/{eventScanCredential}/reset-password', [EventScanCredentialController::class, 'resetPassword'])->name('event-scan-credentials.reset-password');
        Route::get('/events', [EventManagementController::class, 'index'])->name('events.index');
        Route::get('/events/total-attendance', [EventManagementController::class, 'totalAttendance'])->name('events.total-attendance');
        Route::get('/events/total-registered', [EventManagementController::class, 'totalRegistered'])->name('events.total-registered');
        Route::get('/events/attendance', [EventManagementController::class, 'attendance'])->name('events.attendance');
        Route::get('/event-joining-requests', [EventManagementController::class, 'joiningRequests'])->name('event-joining-requests.index');
        Route::post('/event-joining-requests/{id}/approve', [EventManagementController::class, 'approveJoiningRequest'])->whereUuid('id')->name('event-joining-requests.approve');
        Route::post('/event-joining-requests/{id}/reject', [EventManagementController::class, 'rejectJoiningRequest'])->whereUuid('id')->name('event-joining-requests.reject');
        Route::get('/events/create', [EventManagementController::class, 'create'])->name('events.create');
        Route::post('/events', [EventManagementController::class, 'store'])->name('events.store');
        Route::get('/events/{id}/edit', [EventManagementController::class, 'edit'])->whereUuid('id')->name('events.edit');
        Route::put('/events/{id}', [EventManagementController::class, 'update'])->whereUuid('id')->name('events.update');
        Route::get('/events/{id}', [EventManagementController::class, 'show'])->whereUuid('id')->name('events.show');
        Route::post('/events/{id}/occurrences/{occurrence_id}/add-visitor', [EventManagementController::class, 'addVisitorDirectly'])->whereUuid('id')->whereUuid('occurrence_id')->name('events.occurrences.add-visitor');
        Route::post('/events/registrations/{registration_id}/sync-zoho-invoice', [EventManagementController::class, 'syncZohoInvoice'])->name('events.registrations.sync-zoho-invoice');
        Route::post('/events/registrations/{id}/send-whatsapp-qr', [EventManagementController::class, 'sendWhatsappQr'])->whereUuid('id')->name('events.registrations.send-whatsapp-qr');

        Route::resource('/event-coupons', EventCouponWebController::class)->except(['create', 'edit']);

        Route::get('/event-gallery', [EventGalleryController::class, 'index'])->name('event-gallery.index');
        Route::post('/event-gallery/events', [EventGalleryController::class, 'storeEvent'])->name('event-gallery.events.store');
        Route::post('/event-gallery/media', [EventGalleryController::class, 'storeMedia'])->name('event-gallery.media.store');
        Route::delete('/event-gallery/media/{id}', [EventGalleryController::class, 'destroyMedia'])->name('event-gallery.media.destroy');
        Route::get('/categories/export', [CategoryController::class, 'export'])->name('categories.export');
        Route::post('/categories/import', [CategoryController::class, 'import'])->name('categories.import');
        Route::get('/categories/{category}/view', [CategoryController::class, 'show'])->name('categories.view');
        Route::post('/categories/{category}/level2', [CategoryController::class, 'storeLevel2'])->name('categories.level2.store');
        Route::post('/categories/{category}/level3', [CategoryController::class, 'storeLevel3'])->name('categories.level3.store');
        Route::post('/categories/{category}/level4', [CategoryController::class, 'storeLevel4'])->name('categories.level4.store');
        Route::delete('/categories/level2/{level2}', [CategoryController::class, 'destroyLevel2'])->name('categories.level2.destroy');
        Route::delete('/categories/level3/{level3}', [CategoryController::class, 'destroyLevel3'])->name('categories.level3.destroy');
        Route::delete('/categories/level4/{level4}', [CategoryController::class, 'destroyLevel4'])->name('categories.level4.destroy');
        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::get('/ads/dashboard', [AdAnalyticsController::class, 'index'])->name('ads.dashboard');
        Route::get('/ads/analytics', [AdAnalyticsController::class, 'detailedReport'])->name('ads.analytics');
        Route::get('/ads', [AdController::class, 'index'])->name('ads.index');
        Route::get('/ads/create', [AdController::class, 'create'])->name('ads.create');
        Route::post('/ads', [AdController::class, 'store'])->name('ads.store');
        Route::get('/ads/{ad}/edit', [AdController::class, 'edit'])->name('ads.edit');
        Route::put('/ads/{ad}', [AdController::class, 'update'])->name('ads.update');
        Route::patch('/ads/{ad}/toggle-status', [AdController::class, 'toggleStatus'])->name('ads.toggle-status');
        Route::delete('/ads/{ad}', [AdController::class, 'destroy'])->name('ads.destroy');
        Route::get('/ads/{ad}', [AdController::class, 'show'])->name('ads.show');
        Route::get('/ad-bookings', [AdBookingAdminWebController::class, 'index'])->name('ad-bookings.index');
        Route::get('/ad-bookings/{adBooking}', [AdBookingAdminWebController::class, 'show'])->whereUuid('adBooking')->name('ad-bookings.show');
        Route::post('/ad-bookings/{adBooking}/review', [AdBookingAdminWebController::class, 'review'])->whereUuid('adBooking')->name('ad-bookings.review');
        Route::get('/posts', [PostModerationController::class, 'index'])->name('posts.index');
        Route::get('/posts/{post}', [PostModerationController::class, 'show'])->name('posts.show');
        Route::post('/posts/impacts/{impact}/deactivate', [PostModerationController::class, 'deactivateImpact'])->whereUuid('impact')->name('posts.impacts.deactivate');
        Route::post('/posts/impacts/{impact}/activate', [PostModerationController::class, 'activateImpact'])->whereUuid('impact')->name('posts.impacts.activate');
        Route::get('/post-reports', [PostReportsController::class, 'index'])->name('post-reports.index');
        Route::get('/post-reports/{report}', [PostReportsController::class, 'show'])->name('post-reports.show');
        Route::post('/post-reports/{report}/mark-reviewed', [PostReportsController::class, 'markReviewed'])->name('post-reports.mark-reviewed');
        Route::post('/post-reports/{report}/dismiss', [PostReportsController::class, 'dismiss'])->name('post-reports.dismiss');
        Route::post('/post-reports/{report}/resolve', [PostReportsController::class, 'resolve'])->name('post-reports.resolve');
        Route::delete('/posts/{post}', [PostModerationController::class, 'destroy'])->name('posts.destroy');
        Route::post('/posts/{post}/deactivate', [PostModerationController::class, 'deactivate'])->name('posts.deactivate');
        Route::post('/posts/{post}/restore', [PostModerationController::class, 'restore'])->name('posts.restore');
        Route::get('/pending-requests/pending-registrations', [PendingRegistrationsController::class, 'index'])->name('pending-registrations.index');
        Route::post('/pending-requests/pending-registrations/{user}/approve', [PendingRegistrationsController::class, 'approve'])->name('pending-registrations.approve');
        Route::post('/pending-requests/pending-registrations/{user}/reject', [PendingRegistrationsController::class, 'reject'])->name('pending-registrations.reject');

        Route::get('/pending-requests/introduction-requests', [IntroductionRequestsController::class, 'index'])->name('introduction-requests.index');
        Route::post('/pending-requests/introduction-requests/{id}/approve', [IntroductionRequestsController::class, 'approve'])->whereUuid('id')->name('introduction-requests.approve');
        Route::post('/pending-requests/introduction-requests/{id}/reject', [IntroductionRequestsController::class, 'reject'])->whereUuid('id')->name('introduction-requests.reject');

        Route::get('/peer-referrals', [PeerReferralsController::class, 'index'])->name('peer-referrals.index');
        Route::get('/peer-referrals/{id}', [PeerReferralsController::class, 'show'])->whereUuid('id')->name('peer-referrals.show');
        Route::post('/peer-referrals/{id}/status', [PeerReferralsController::class, 'updateStatus'])->whereUuid('id')->name('peer-referrals.status-update');

        Route::get('/visitor-registrations', [VisitorRegistrationsController::class, 'index'])->name('visitor-registrations.index');
        Route::post('/visitor-registrations', [VisitorRegistrationsController::class, 'store'])->name('visitor-registrations.store');
        Route::post('/visitor-registrations/import', [VisitorRegistrationsController::class, 'importStore'])->name('visitor-registrations.import');
        Route::get('/visitor-registrations/sample-csv', [VisitorRegistrationsController::class, 'sampleCsv'])->name('visitor-registrations.sample-csv');
        Route::get('/visitor-registrations/export', [VisitorRegistrationsController::class, 'export'])->name('visitor-registrations.export');
        Route::get('/visitor-registrations/{id}/export-single', [VisitorRegistrationsController::class, 'exportSingle'])
            ->whereUuid('id')
            ->name('visitor-registrations.export-single');
        Route::post('/visitor-registrations/{id}/approve', [VisitorRegistrationsController::class, 'approve'])
            ->whereUuid('id')
            ->name('visitor-registrations.approve');
        Route::post('/visitor-registrations/{id}/reject', [VisitorRegistrationsController::class, 'reject'])
            ->whereUuid('id')
            ->name('visitor-registrations.reject');
        Route::delete('/visitor-registrations/{id}', [VisitorRegistrationsController::class, 'destroy'])
            ->whereUuid('id')
            ->name('visitor-registrations.destroy');
        Route::post('/visitor-registrations/bulk-destroy', [VisitorRegistrationsController::class, 'bulkDestroy'])
            ->name('visitor-registrations.bulk-destroy');
        Route::get('/coin-claims', [CoinClaimsController::class, 'index'])->name('coin-claims.index');
        Route::get('/coin-claims/{id}', [CoinClaimsController::class, 'show'])->whereUuid('id')->name('coin-claims.show');
        Route::post('/coin-claims/{id}/approve', [CoinClaimsController::class, 'approve'])->whereUuid('id')->name('coin-claims.approve');
        Route::post('/coin-claims/{id}/reject', [CoinClaimsController::class, 'reject'])->whereUuid('id')->name('coin-claims.reject');
        Route::get('/pending-requests/circle-joining-requests', [CircleJoinRequestsController::class, 'index'])->name('circle-joining-requests.index');
        Route::get('/pending-requests/circle-joining-requests/{id}', [CircleJoinRequestsController::class, 'show'])->whereUuid('id')->name('circle-joining-requests.show');
        Route::post('/pending-requests/circle-joining-requests/{id}/approve-cd', [CircleJoinRequestsController::class, 'approveCd'])->whereUuid('id')->name('circle-joining-requests.approve-cd');
        Route::post('/pending-requests/circle-joining-requests/{id}/reject-cd', [CircleJoinRequestsController::class, 'rejectCd'])->whereUuid('id')->name('circle-joining-requests.reject-cd');
        Route::post('/pending-requests/circle-joining-requests/{id}/approve-id', [CircleJoinRequestsController::class, 'approveId'])->whereUuid('id')->name('circle-joining-requests.approve-id');
        Route::post('/pending-requests/circle-joining-requests/{id}/approve-ded', [CircleJoinRequestsController::class, 'approveDed'])->whereUuid('id')->name('circle-joining-requests.approve-ded');
        Route::post('/pending-requests/circle-joining-requests/{id}/reject-ded', [CircleJoinRequestsController::class, 'rejectDed'])->whereUuid('id')->name('circle-joining-requests.reject-ded');
        Route::post('/pending-requests/circle-joining-requests/{id}/reject-id', [CircleJoinRequestsController::class, 'rejectId'])->whereUuid('id')->name('circle-joining-requests.reject-id');
        Route::get('/pending-requests/certifications', [CertificationSubmissionsController::class, 'index'])->name('certifications.index');
        Route::post('/pending-requests/certifications/{id}/approve', [CertificationSubmissionsController::class, 'approve'])->whereUuid('id')->name('certifications.approve');
        Route::post('/pending-requests/certifications/{id}/reject', [CertificationSubmissionsController::class, 'reject'])->whereUuid('id')->name('certifications.reject');
        Route::get('/certificates/{id}/view', [CertificationSubmissionsController::class, 'certificate'])->whereUuid('id')->name('certifications.certificate');

        Route::get('/pending-requests/leads/entrepreneur-certification', [LeadSubmissionsController::class, 'entrepreneurCertification'])->name('leads.entrepreneur-certification.index');
        Route::get('/pending-requests/leads/entrepreneur-certification/{id}', [LeadSubmissionsController::class, 'entrepreneurCertificationShow'])->name('leads.entrepreneur-certification.show');
        Route::get('/pending-requests/leads/leadership-certification', [LeadSubmissionsController::class, 'leadershipCertification'])->name('leads.leadership-certification.index');
        Route::get('/pending-requests/leads/leadership-certification/{id}', [LeadSubmissionsController::class, 'leadershipCertificationShow'])->name('leads.leadership-certification.show');
        Route::get('/pending-requests/leads/partner-with-us', [LeadSubmissionsController::class, 'partnerWithUs'])->name('leads.partner-with-us.index');
        Route::get('/pending-requests/leads/partner-with-us/{id}', [LeadSubmissionsController::class, 'partnerWithUsShow'])->name('leads.partner-with-us.show');
        Route::get('/pending-requests/leads/become-speaker', [LeadSubmissionsController::class, 'becomeSpeaker'])->name('leads.become-speaker.index');
        Route::get('/pending-requests/leads/become-speaker/{id}', [LeadSubmissionsController::class, 'becomeSpeakerShow'])->name('leads.become-speaker.show');
        Route::get('/pending-requests/leads/become-mentor', [LeadSubmissionsController::class, 'becomeMentor'])->name('leads.become-mentor.index');
        Route::get('/pending-requests/leads/become-mentor/{id}', [LeadSubmissionsController::class, 'becomeMentorShow'])->name('leads.become-mentor.show');
        Route::get('/campaign-email-templates', [CampaignEmailTemplateController::class, 'index'])->name('campaign-email-templates.index');
        Route::get('/campaign-email-templates/list', [CampaignEmailTemplateController::class, 'list'])->name('campaign-email-templates.list');
        Route::get('/notifications/dashboard', [NotificationAdminController::class, 'dashboard'])->name('notifications.dashboard');
        Route::get('/notifications/campaigns', [NotificationAdminController::class, 'campaigns'])->name('notifications.campaigns');
        Route::get('/notifications/campaigns/create', [NotificationAdminController::class, 'createCampaign'])->name('notifications.campaigns.create');
        Route::post('/notifications/campaigns', [NotificationAdminController::class, 'storeCampaign'])->name('notifications.campaigns.store');
        Route::post('/notifications/campaigns/seed-defaults', [NotificationAdminController::class, 'seedDefaults'])->name('notifications.campaigns.seed-defaults');
        Route::get('/notifications/campaigns/{id}/edit', [NotificationAdminController::class, 'editCampaign'])->whereUuid('id')->name('notifications.campaigns.edit');
        Route::put('/notifications/campaigns/{id}', [NotificationAdminController::class, 'updateCampaign'])->whereUuid('id')->name('notifications.campaigns.update');
        Route::patch('/notifications/campaigns/{id}/toggle', [NotificationAdminController::class, 'toggleCampaign'])->whereUuid('id')->name('notifications.campaigns.toggle');
        Route::post('/notifications/campaigns/{id}/preview', [NotificationAdminController::class, 'previewCampaign'])->whereUuid('id')->name('notifications.campaigns.preview');
        Route::post('/notifications/campaigns/{id}/run', [NotificationAdminController::class, 'runCampaign'])->whereUuid('id')->name('notifications.campaigns.run');
        Route::get('/notifications/users/search', [NotificationAdminController::class, 'searchUsers'])->name('notifications.users.search');
        Route::get('/notifications/users/{user}/push-status', [NotificationAdminController::class, 'pushStatus'])->whereUuid('user')->name('notifications.users.push-status');
        Route::get('/notifications/send-test', [NotificationAdminController::class, 'sendTestForm'])->name('notifications.send-test');
        Route::post('/notifications/send-test', [NotificationAdminController::class, 'sendTest'])->name('notifications.send-test.store');
        Route::get('/notifications/logs', [NotificationAdminController::class, 'logs'])->name('notifications.logs');
        Route::get('/notifications/push-tokens', [NotificationAdminController::class, 'pushTokens'])->name('notifications.push-tokens');
        Route::patch('/notifications/push-tokens/{id}/deactivate', [NotificationAdminController::class, 'deactivatePushToken'])->whereUuid('id')->name('notifications.push-tokens.deactivate');
        Route::get('/notifications/user-notifications', [NotificationAdminController::class, 'userNotifications'])->name('notifications.user-notifications');
        Route::post('/notifications/{id}/mark-read', [NotificationAdminController::class, 'markNotificationRead'])->whereUuid('id')->name('notifications.mark-read');
        Route::delete('/notifications/{id}', [NotificationAdminController::class, 'deleteNotification'])->whereUuid('id')->name('notifications.destroy');
        Route::delete('/notifications/clear-user/{userId}', [NotificationAdminController::class, 'clearUserNotifications'])->whereUuid('userId')->name('notifications.clear-user');

        Route::get('/campaigns', [AdminCampaignController::class, 'index'])->name('campaigns.index');
        Route::get('/campaigns/create', [AdminCampaignController::class, 'create'])->name('campaigns.create');
        Route::post('/campaigns', [AdminCampaignController::class, 'store'])->name('campaigns.store');
        Route::post('/campaigns/preview-recipients', [AdminCampaignController::class, 'previewRecipients'])->name('campaigns.preview-recipients');
        Route::post('/campaigns/import-audience', [AdminCampaignController::class, 'importAudience'])->name('campaigns.import-audience');
        Route::get('/campaigns/audience-samples/{audienceType}', [AdminCampaignController::class, 'downloadAudienceSample'])->name('campaigns.audience-samples');
        Route::get('/campaigns/filter-options', [AdminCampaignController::class, 'filterOptions'])->name('campaigns.filter-options');
        Route::get('/campaigns/member-search', [AdminCampaignController::class, 'memberSearch'])->name('campaigns.member-search');
        Route::get('/campaigns/{campaign}', [AdminCampaignController::class, 'show'])->name('campaigns.show');
        Route::get('/campaigns/{campaign}/edit', [AdminCampaignController::class, 'edit'])->name('campaigns.edit');
        Route::put('/campaigns/{campaign}', [AdminCampaignController::class, 'update'])->name('campaigns.update');
        Route::post('/campaigns/{campaign}/send', [AdminCampaignController::class, 'send'])->name('campaigns.send');
        Route::delete('/campaigns/{campaign}', [AdminCampaignController::class, 'destroy'])->name('campaigns.destroy');
        Route::post('/campaigns/{campaign}/pause', [AdminCampaignController::class, 'pause'])->name('campaigns.pause');
        Route::post('/campaigns/{campaign}/resume', [AdminCampaignController::class, 'resume'])->name('campaigns.resume');
        Route::post('/campaigns/{campaign}/stop', [AdminCampaignController::class, 'stop'])->name('campaigns.stop');
        Route::post('/campaigns/{campaign}/duplicate', [AdminCampaignController::class, 'duplicate'])->name('campaigns.duplicate');
        Route::post('/campaigns/{campaign}/retry', [AdminCampaignController::class, 'retry'])->name('campaigns.retry');
        Route::get('/campaign-pamphlets/select-list', [CampaignPamphletController::class, 'selectList'])->name('campaign-pamphlets.select-list');
        Route::get('/campaign-pamphlets', [CampaignPamphletController::class, 'index'])->name('campaign-pamphlets.index');
        Route::get('/campaign-pamphlets/create', [CampaignPamphletController::class, 'create'])->name('campaign-pamphlets.create');
        Route::post('/campaign-pamphlets', [CampaignPamphletController::class, 'store'])->name('campaign-pamphlets.store');
        Route::get('/campaign-pamphlets/{pamphlet}/edit', [CampaignPamphletController::class, 'edit'])->name('campaign-pamphlets.edit');
        Route::put('/campaign-pamphlets/{pamphlet}', [CampaignPamphletController::class, 'update'])->name('campaign-pamphlets.update');
        Route::delete('/campaign-pamphlets/{pamphlet}', [CampaignPamphletController::class, 'destroy'])->name('campaign-pamphlets.destroy');

        Route::get('/email-logs', [EmailLogController::class, 'index'])->name('email-logs.index');
        Route::get('/daily-notifications', [DailyNotificationController::class, 'index'])->name('daily-notifications.index');
        Route::put('/daily-notifications/{id}', [DailyNotificationController::class, 'update'])->name('daily-notifications.update');
        Route::get('/daily-notifications/{id}/eligible-users', [DailyNotificationController::class, 'eligibleUsers'])->name('daily-notifications.eligible-users');
        Route::post('/daily-notifications/{id}/send', [DailyNotificationController::class, 'sendReminder'])->name('daily-notifications.send');
        Route::get('/test-notifications', [DailyNotificationController::class, 'testNotifications'])->name('daily-notifications.test');

        Route::get('/impacts', [ImpactsController::class, 'index'])->name('impacts.index');
        Route::get('/impacts/export/csv', [ImpactsController::class, 'exportCsv'])->name('impacts.export.csv');
        Route::post('/impacts', [ImpactsController::class, 'store'])->name('impacts.store');
        Route::post('/impacts/actions', [ImpactsController::class, 'storeAction'])->name('impacts.actions.store');
        Route::put('/impacts/actions/{id}', [ImpactsController::class, 'updateAction'])->whereUuid('id')->name('impacts.actions.update');
        Route::delete('/impacts/actions/{id}', [ImpactsController::class, 'destroyAction'])->whereUuid('id')->name('impacts.actions.destroy');
        Route::get('/impacts/pending', [ImpactsController::class, 'pending'])->name('impacts.pending');
        Route::get('/impacts/posts', [ImpactsController::class, 'posts'])->name('impacts.posts');
        Route::get('/impacts/{id}', [ImpactsController::class, 'show'])->whereUuid('id')->name('impacts.show');
        Route::post('/impacts/{id}/approve', [ImpactsController::class, 'approve'])->whereUuid('id')->name('impacts.approve');
        Route::post('/impacts/{id}/reject', [ImpactsController::class, 'reject'])->whereUuid('id')->name('impacts.reject');
        Route::get('/email-logs/{emailLog}', [EmailLogController::class, 'show'])->name('email-logs.show');

        // Account Deletion Requests
        Route::get('/account-deletion-request', [App\Http\Controllers\Admin\AccountDeletionController::class, 'index']);
        Route::get('/account-deletion-requests', [App\Http\Controllers\Admin\AccountDeletionController::class, 'index'])->name('account-deletion.index');
        Route::get('/account-deletion-requests/emails', [App\Http\Controllers\Admin\AccountDeletionController::class, 'emails'])->name('account-deletion.emails');
        Route::get('/account-deletion-requests/emails/{template}/preview', [App\Http\Controllers\Admin\AccountDeletionController::class, 'preview'])->name('account-deletion.emails.preview');
        Route::post('/account-deletion-requests/emails/{template}/send', [App\Http\Controllers\Admin\AccountDeletionController::class, 'send'])->name('account-deletion.emails.send');
        Route::post('/account-deletion-requests/emails/clear-logs', [App\Http\Controllers\Admin\AccountDeletionController::class, 'clearLogs'])->name('account-deletion.emails.clear-logs');
        Route::post('/account-deletion-requests/{id}/approve', [App\Http\Controllers\Admin\AccountDeletionController::class, 'approve'])->name('account-deletion.approve');
        Route::post('/account-deletion-requests/{id}/reject', [App\Http\Controllers\Admin\AccountDeletionController::class, 'reject'])->name('account-deletion.reject');
        Route::patch('/account-deletion-requests/{id}/status', [App\Http\Controllers\Admin\AccountDeletionController::class, 'updateStatus'])->name('account-deletion.update-status');
        Route::post('/account-deletion-requests/{id}/activate-account', [App\Http\Controllers\Admin\AccountDeletionController::class, 'activateAccount'])->name('account-deletion.activate-account');
        Route::post('/account-deletion-requests/{id}/deactivate-account', [App\Http\Controllers\Admin\AccountDeletionController::class, 'deactivateAccount'])->name('account-deletion.deactivate-account');

        // Support Tickets Module
        Route::get('/support-tickets', [SupportTicketController::class, 'index'])->name('support-tickets.index');
        Route::get('/support-tickets/{id}', [SupportTicketController::class, 'show'])->name('support-tickets.show');
        Route::put('/support-tickets/{id}', [SupportTicketController::class, 'update'])->name('support-tickets.update');
        Route::post('/support-tickets/{id}/send-email', [SupportTicketController::class, 'sendEmail'])->whereUuid('id')->name('support-tickets.send-email');

        // Email Templates Module
        Route::get('/email-templates', [EmailTemplateController::class, 'index'])->name('email-templates.index');
        Route::get('/email-templates/{key}/edit', [EmailTemplateController::class, 'edit'])->name('email-templates.edit');
        Route::put('/email-templates/{key}', [EmailTemplateController::class, 'update'])->name('email-templates.update');
        Route::get('/email-templates/{key}/preview', [EmailTemplateController::class, 'preview'])->name('email-templates.preview');

        // Notification Templates Module
        Route::get('/notification-templates', [NotificationTemplateController::class, 'index'])->name('notification-templates.index');
        Route::get('/notification-templates/{key}/edit', [NotificationTemplateController::class, 'edit'])->name('notification-templates.edit');
        Route::put('/notification-templates/{key}', [NotificationTemplateController::class, 'update'])->name('notification-templates.update');
        Route::get('/notification-templates/{key}/preview', [NotificationTemplateController::class, 'preview'])->name('notification-templates.preview');

        // App Notifications & Mobile Navigation Showcase Module
        Route::get('/app-notifications', [AppNotificationAdminController::class, 'index'])->name('app-notifications.index');
        Route::post('/app-notifications', [AppNotificationAdminController::class, 'store'])->name('app-notifications.store');
        Route::get('/app-notifications/peers-search', [AppNotificationAdminController::class, 'searchPeers'])->name('app-notifications.peers-search');
        Route::get('/app-notifications/peer-details/{id}', [AppNotificationAdminController::class, 'peerDetails'])->whereUuid('id')->name('app-notifications.peer-details');
        Route::get('/app-notifications/{key}/preview', [AppNotificationAdminController::class, 'preview'])->name('app-notifications.preview');
        Route::post('/app-notifications/send', [AppNotificationAdminController::class, 'sendToPeers'])->name('app-notifications.send');
        Route::post('/app-notifications/send-all-to-peer', [AppNotificationAdminController::class, 'sendAllToPeer'])->name('app-notifications.send-all-to-peer');
        Route::get('/app-notifications/delivery-logs', [AppNotificationAdminController::class, 'deliveryLogs'])->name('app-notifications.delivery-logs');

        Route::get('/execution/leadership', [AdminExecutionController::class, 'leadership'])->name('execution.leadership');
        Route::get('/execution/industries', [AdminExecutionController::class, 'industries'])->name('execution.industries');
        Route::get('/execution/events', [AdminExecutionController::class, 'events'])->name('execution.events');
        Route::get('/execution/finance', [AdminExecutionController::class, 'finance'])->name('execution.finance');
        Route::get('/execution/communications', [AdminExecutionController::class, 'communications'])->name('execution.communications');
        Route::post('/execution/communications/broadcast', [AdminExecutionController::class, 'sendBroadcast'])->name('execution.broadcast.send');
        Route::get('/execution/meetings', [AdminExecutionController::class, 'meetings'])->name('execution.meetings');
        Route::get('/execution/reports', [AdminExecutionController::class, 'reports'])->name('execution.reports');

        // Brand Partners Module
        Route::middleware('admin.role:global_admin,marketing_team,analytics_team,content_team,read_only')->group(function () {
            Route::get('/brand-partners/dashboard', [BrandPartnerAnalyticsController::class, 'index'])->name('brand-partners.dashboard');
            Route::get('/brand-partners/analytics', [BrandPartnerAnalyticsController::class, 'detailedReport'])->name('brand-partners.analytics');
            Route::get('/brand-partners/offers', [BrandPartnerController::class, 'offers'])->name('brand-partners.offers');
            Route::get('/brand-partners/settings', [BrandPartnerSettingsController::class, 'index'])->name('brand-partners.settings');
            Route::get('/brand-partners', [BrandPartnerController::class, 'index'])->name('brand-partners.index');
        });

        Route::get('/pending-requests/leads/entrepreneur-certification', [LeadSubmissionsController::class, 'entrepreneurCertification'])->name('leads.entrepreneur-certification.index');
        Route::get('/pending-requests/leads/entrepreneur-certification/{id}', [LeadSubmissionsController::class, 'entrepreneurCertificationShow'])->name('leads.entrepreneur-certification.show');
        Route::get('/pending-requests/leads/leadership-certification', [LeadSubmissionsController::class, 'leadershipCertification'])->name('leads.leadership-certification.index');
        Route::get('/pending-requests/leads/leadership-certification/{id}', [LeadSubmissionsController::class, 'leadershipCertificationShow'])->name('leads.leadership-certification.show');
        Route::get('/pending-requests/leads/partner-with-us', [LeadSubmissionsController::class, 'partnerWithUs'])->name('leads.partner-with-us.index');
        Route::get('/pending-requests/leads/partner-with-us/{id}', [LeadSubmissionsController::class, 'partnerWithUsShow'])->name('leads.partner-with-us.show');
        Route::get('/pending-requests/leads/become-speaker', [LeadSubmissionsController::class, 'becomeSpeaker'])->name('leads.become-speaker.index');
        Route::get('/pending-requests/leads/become-speaker/{id}', [LeadSubmissionsController::class, 'becomeSpeakerShow'])->name('leads.become-speaker.show');
        Route::get('/pending-requests/leads/become-mentor', [LeadSubmissionsController::class, 'becomeMentor'])->name('leads.become-mentor.index');
        Route::get('/pending-requests/leads/become-mentor/{id}', [LeadSubmissionsController::class, 'becomeMentorShow'])->name('leads.become-mentor.show');
        Route::get('/campaign-email-templates', [CampaignEmailTemplateController::class, 'index'])->name('campaign-email-templates.index');
        Route::get('/campaign-email-templates/list', [CampaignEmailTemplateController::class, 'list'])->name('campaign-email-templates.list');

        Route::middleware('admin.role:global_admin,marketing_team,content_team')->group(function () {
            Route::get('/brand-partners/create', [BrandPartnerController::class, 'create'])->name('brand-partners.create');
            Route::post('/brand-partners', [BrandPartnerController::class, 'store'])->name('brand-partners.store');
            Route::get('/brand-partners/{brand_partner}/edit', [BrandPartnerController::class, 'edit'])->name('brand-partners.edit');
            Route::put('/brand-partners/{brand_partner}', [BrandPartnerController::class, 'update'])->name('brand-partners.update');
            Route::post('/brand-partners/{brand_partner}/duplicate', [BrandPartnerController::class, 'duplicate'])->name('brand-partners.duplicate');
            Route::patch('/brand-partners/{brand_partner}/status', [BrandPartnerController::class, 'toggleStatus'])->name('brand-partners.toggle-status');
            Route::patch('/brand-partners/{brand_partner}/featured', [BrandPartnerController::class, 'toggleFeatured'])->name('brand-partners.toggle-featured');
            Route::patch('/brand-partners/{brand_partner}/sponsored', [BrandPartnerController::class, 'toggleSponsored'])->name('brand-partners.toggle-sponsored');
            Route::post('/brand-partners/reorder', [BrandPartnerController::class, 'reorderPriority'])->name('brand-partners.reorder');
            Route::post('/brand-partners/{brand_partner}/priority-up', [BrandPartnerController::class, 'movePriorityUp'])->name('brand-partners.priority-up');
            Route::post('/brand-partners/{brand_partner}/priority-down', [BrandPartnerController::class, 'movePriorityDown'])->name('brand-partners.priority-down');
            Route::post('/brand-partners/{brand_partner}/send-notification', [BrandPartnerController::class, 'sendManualNotification'])->name('brand-partners.send-notification');
        });

        Route::middleware('admin.role:global_admin')->group(function () {
            Route::delete('/brand-partners/{brand_partner}', [BrandPartnerController::class, 'destroy'])->name('brand-partners.destroy');
            Route::post('/brand-partners/settings', [BrandPartnerSettingsController::class, 'update'])->name('brand-partners.settings.update');
        });

        Route::middleware('admin.role:global_admin,content_team')->group(function () {
            Route::get('/brand-partners/categories', [BrandPartnerCategoryController::class, 'index'])->name('brand-partners.categories.index');
            Route::post('/brand-partners/categories', [BrandPartnerCategoryController::class, 'store'])->name('brand-partners.categories.store');
            Route::put('/brand-partners/categories/{brand_partner_category}', [BrandPartnerCategoryController::class, 'update'])->name('brand-partners.categories.update');
            Route::delete('/brand-partners/categories/{brand_partner_category}', [BrandPartnerCategoryController::class, 'destroy'])->name('brand-partners.categories.destroy');
            Route::post('/brand-partners/categories/reorder', [BrandPartnerCategoryController::class, 'reorder'])->name('brand-partners.categories.reorder');
        });

        Route::middleware('admin.role:global_admin,analytics_team')->group(function () {
            Route::get('/brand-partners/export', [BrandPartnerController::class, 'export'])->name('brand-partners.export');
        });

        Route::get('/execution/leadership', [AdminExecutionController::class, 'leadership'])->name('execution.leadership');
        Route::get('/execution/industries', [AdminExecutionController::class, 'industries'])->name('execution.industries');

        // Wildcard route defined at the bottom to avoid intercepting concrete paths
        Route::middleware('admin.role:global_admin,marketing_team,analytics_team,content_team,read_only')->group(function () {
            Route::get('/brand-partners/{brand_partner}', [BrandPartnerController::class, 'show'])->name('brand-partners.show');
        });
    });
});
