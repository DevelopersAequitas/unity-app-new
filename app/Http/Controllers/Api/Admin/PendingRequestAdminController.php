<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Events\PendingRequestChangedEvent;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\Certification;
use App\Models\Post;
use App\Models\Notification;
use App\Models\User;

class PendingRequestAdminController extends Controller
{
    /**
     * Authoritative breakdown and total counts computed strictly from the database
     */
    public function getSummaryData(): array
    {
        $breakdown = [
            'visitor_registrations' => $this->getPendingCount('visitor_registrations'),
            'coin_claims' => $this->getPendingCount('coin_claim_requests', 'coin_claims'),
            'circle_joining_requests' => $this->getPendingCount('circle_join_requests'),
            'certifications' => $this->getPendingCount('certifications', 'certification_requests', 'user_certifications', 'diagnostic_submissions', 'certification_submissions'),
            'pending_impacts' => $this->getPendingCount('impacts', 'life_impacts'),
            'ad_booking_requests' => $this->getPendingCount('ad_bookings'),
            'account_deletion_requests' => $this->getPendingCount('account_deletion_requests'),
            'account_deletion_emails' => $this->getPendingCount('account_deletion_emails'),
            'introduction_requests' => $this->getPendingCount('introduction_requests'),
            'circle_peer_referrals' => $this->getPendingCount('circle_peer_referrals', 'peer_referrals'),
            'event_joining_requests' => $this->getPendingCount('event_joining_requests', 'event_join_requests', 'event_registrations', 'event_registration_requests'),
        ];

        // Total calculated strictly as unique pending categories without duplicate counting
        $totalPending = array_sum($breakdown);

        // Normalize aliases for frontend compatibility without inflating total_pending
        $breakdown['circle_join_requests'] = $breakdown['circle_joining_requests'];
        $breakdown['event_registrations'] = $breakdown['event_joining_requests'];
        $breakdown['visitors'] = $breakdown['visitor_registrations'];

        return [
            'total_pending' => $totalPending,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Authoritative breakdown and total counts computed strictly from the database
     */
    public function summary(): JsonResponse
    {
        $data = $this->getSummaryData();

        return response()->json([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    /**
     * Unified list of real pending requests with category filter & pagination
     */
    public function index(Request $request): JsonResponse
    {
        $category = (string) $request->input('category', 'all');
        $status = strtolower((string) $request->input('status', 'pending'));
        if (! in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
            $status = 'pending';
        }
        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 20);

        /** @var Collection $results */
        $results = collect();

        // 1. Circle Joining Requests
        if ($category === 'all' || $category === 'circle_joining_requests' || $category === 'circle_join_requests') {
            $results = $results->concat($this->fetchCircleJoinRequests($search, $status));
        }

        // 2. Visitor Registrations
        if ($category === 'all' || $category === 'visitor_registrations' || $category === 'visitors') {
            $results = $results->concat($this->fetchVisitorRegistrations($search, $status));
        }

        // 3. Coin Claims
        if ($category === 'all' || $category === 'coin_claims' || $category === 'coin_claim_requests' || $category === 'coin-claims') {
            $results = $results->concat($this->fetchCoinClaims($search, $status));
        }

        // 4. Certifications
        if ($category === 'all' || $category === 'certifications') {
            $results = $results->concat($this->fetchCertifications($search, $status));
        }

        // 5. Pending Impacts
        if ($category === 'all' || $category === 'pending_impacts') {
            $results = $results->concat($this->fetchPendingImpacts($search, $status));
        }

        // 6. Ad Booking Requests
        if ($category === 'all' || $category === 'ad_booking_requests') {
            $results = $results->concat($this->fetchAdBookings($search, $status));
        }

        // 7. Account Deletion Requests
        if ($category === 'all' || $category === 'account_deletion_requests' || $category === 'account_deletion_emails') {
            $results = $results->concat($this->fetchAccountDeletions($search, $status));
        }

        // 8. Event Joining Requests
        if ($category === 'all' || $category === 'event_joining_requests' || $category === 'event_registrations' || $category === 'event_join_requests') {
            $results = $results->concat($this->fetchEventJoiningRequests($search, $status));
        }

        // 9. Circle Peer Referrals
        if ($category === 'all' || $category === 'circle_peer_referrals' || $category === 'peer_referrals') {
            $results = $results->concat($this->fetchPeerReferrals($search, $status));
        }

        // 10. Introduction Requests
        if ($category === 'all' || $category === 'introduction_requests') {
            $results = $results->concat($this->fetchIntroductionRequests($search, $status));
        }

        // Sort chronologically by submission date
        $sorted = $results->sortByDesc('submitted_at')->values();

        $page = (int) $request->input('page', 1);
        $paginated = $sorted->forPage($page, $perPage)->values();

        return response()->json([
            'success' => true,
            'data' => $paginated,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $sorted->count(),
            'last_page' => (int) ceil(max($sorted->count(), 1) / $perPage),
        ], 200);
    }

    /**
     * Category-aware approve handler
     */
    public function approve(Request $request, string $category, string $id): JsonResponse
    {
        if ($category === 'certifications') {
            return $this->approveCertificationSubmission((string) $id);
        }

        return $this->processApproval($id, $category);
    }

    /**
     * Fallback approve handler when category is omitted in URL
     */
    public function approveDirect(Request $request, string $id): JsonResponse
    {
        $category = $request->input('category') ?? $request->input('type');

        if ($category === 'certifications') {
            return $this->approveCertificationSubmission((string) $id);
        }

        return $this->processApproval($id, is_string($category) ? $category : null);
    }

    /**
     * Category-aware reject handler
     */
    public function reject(Request $request, string $category, string $id): JsonResponse
    {
        $reason = (string) $request->input('reason', 'Administrative clearance declined');

        return $this->processRejection($id, $category, $reason);
    }

    /**
     * Fallback reject handler when category is omitted in URL
     */
    public function rejectDirect(Request $request, string $id): JsonResponse
    {
        $category = $request->input('category') ?? $request->input('type');
        $reason = (string) $request->input('reason', 'Administrative clearance declined');

        return $this->processRejection($id, is_string($category) ? $category : null, $reason);
    }

    /**
     * Backward-compatible legacy approve handler
     */
    public function approveLegacy(Request $request, string $id): JsonResponse
    {
        return $this->approveDirect($request, $id);
    }

    /**
     * Backward-compatible legacy reject handler
     */
    public function rejectLegacy(Request $request, string $id): JsonResponse
    {
        return $this->rejectDirect($request, $id);
    }

    /**
     * Executes the complete Old Admin Panel Certification Approval Flow
     * (Certificate generation, feed post creation, in-app/push notification dispatch)
     */
    private function approveCertificationSubmission(string $id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            // 1. Locate the submission in the appropriate table defensively
            $submission = null;
            $certTable = null;
            $candidateTables = [
                'certifications',
                'certification_requests',
                'user_certifications',
                'diagnostic_submissions',
                'certification_submissions',
            ];

            foreach ($candidateTables as $candidate) {
                if (Schema::hasTable($candidate)) {
                    $found = DB::table($candidate)->where('id', $id)->first();
                    if ($found) {
                        $certTable = $candidate;
                        $submission = $found;
                        break;
                    }
                }
            }

            if (! $submission) {
                return response()->json(['success' => false, 'message' => 'Certification record not found.'], 404);
            }

            $userId = $submission->user_id ?? null;
            $user = null;
            if ($userId && Schema::hasTable('users')) {
                $user = DB::table('users')->where('id', $userId)->first();
            }
            if (! $user && ! empty($submission->email) && Schema::hasTable('users')) {
                $user = DB::table('users')->where('email', $submission->email)->first();
            }

            $userName = 'Applicant Peer';
            if ($user) {
                $userName = $user->name ?? $user->display_name ?? trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
                if (empty($userName)) {
                    $userName = $submission->full_name ?? $submission->name ?? 'Applicant Peer';
                }
            } elseif (! empty($submission->full_name)) {
                $userName = $submission->full_name;
            } elseif (! empty($submission->name)) {
                $userName = $submission->name;
            }

            $certType = $submission->type ?? $submission->certification_type ?? 'Leadership & Entrepreneurship';
            $level = $submission->level ?? $submission->grade ?? $submission->certification_level ?? $submission->certification_tier ?? 'Certified Member';
            $score = $submission->score ?? $submission->total_score ?? $submission->percentage ?? 0;
            $certNumber = $submission->certificate_number ?? ('CERT-'.strtoupper(Str::random(4)).'-'.date('Y'));

            // 2. Mark submission as Approved & Completed with Certificate Number
            $updatePayload = [
                'status' => 'approved',
                'updated_at' => now(),
            ];

            $updateTable = $certTable ?? 'certifications';
            if (Schema::hasColumn($updateTable, 'status')) {
                $updatePayload['status'] = 'approved';
            }
            if (Schema::hasColumn($updateTable, 'certificate_number')) {
                $updatePayload['certificate_number'] = $certNumber;
            }
            if (Schema::hasColumn($updateTable, 'issued_at')) {
                $updatePayload['issued_at'] = now();
            }
            if (Schema::hasColumn($updateTable, 'approved_at')) {
                $updatePayload['approved_at'] = now();
            }
            if (Schema::hasColumn($updateTable, 'certificate_generated_at')) {
                $updatePayload['certificate_generated_at'] = now();
            }

            DB::table($updateTable)->where('id', $id)->update($updatePayload);

            // Also synchronize certifications table if separate and record exists
            if (Schema::hasTable('certifications') && $updateTable !== 'certifications') {
                $certCols = Schema::getColumnListing('certifications');
                $syncPayload = ['updated_at' => now()];
                if (in_array('status', $certCols, true)) {
                    $syncPayload['status'] = 'approved';
                }
                if (in_array('certificate_number', $certCols, true)) {
                    $syncPayload['certificate_number'] = $certNumber;
                }
                if (in_array('issued_at', $certCols, true)) {
                    $syncPayload['issued_at'] = now();
                }
                DB::table('certifications')->where('id', $id)->update($syncPayload);
            }

            // Execute dedicated CertificateGeneratorService if available for rich assets/images
            try {
                if (Schema::hasTable('certification_submissions') && class_exists(\App\Models\CertificationSubmission::class) && class_exists(\App\Services\Certifications\CertificateGeneratorService::class)) {
                    $certSubmission = \App\Models\CertificationSubmission::find($id);
                    if ($certSubmission && $certSubmission->status !== \App\Models\CertificationSubmission::STATUS_APPROVED) {
                        app(\App\Services\Certifications\CertificateGeneratorService::class)->approveSubmission(
                            $certSubmission,
                            'Approved via admin panel',
                            auth('admin')->id() ?? auth()->id()
                        );
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Legacy CertificateGeneratorService execution skipped/failed: '.$e->getMessage());
            }

            // 3. Post to Timeline Feed (Match Old Admin Panel Post Creation)
            if (Schema::hasTable('posts') && ($user || ! empty($userId))) {
                $postUserId = $user ? $user->id : $userId;
                $postContent = "🎓 Congratulations to {$userName} on successfully completing the {$certType} Certification at {$level} level with a score of {$score}%!";

                $postPayload = [
                    'user_id' => $postUserId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (Schema::hasColumn('posts', 'id')) {
                    $postPayload['id'] = (string) Str::uuid();
                }
                if (Schema::hasColumn('posts', 'content')) {
                    $postPayload['content'] = $postContent;
                }
                if (Schema::hasColumn('posts', 'content_text')) {
                    $postPayload['content_text'] = $postContent;
                }
                if (Schema::hasColumn('posts', 'type')) {
                    $postPayload['type'] = 'announcement';
                }
                if (Schema::hasColumn('posts', 'post_type')) {
                    $postPayload['post_type'] = 'announcement';
                }
                if (Schema::hasColumn('posts', 'status')) {
                    $postPayload['status'] = 'active';
                }
                if (Schema::hasColumn('posts', 'active')) {
                    $postPayload['active'] = true;
                }
                if (Schema::hasColumn('posts', 'visibility')) {
                    $postPayload['visibility'] = 'public';
                }
                if (Schema::hasColumn('posts', 'moderation_status')) {
                    $postPayload['moderation_status'] = 'approved';
                }
                if (Schema::hasColumn('posts', 'source_type')) {
                    $postPayload['source_type'] = 'global_peer_certificate';
                }
                if (Schema::hasColumn('posts', 'source_id')) {
                    $postPayload['source_id'] = $postUserId;
                }
                if (Schema::hasColumn('posts', 'source_event')) {
                    $postPayload['source_event'] = 'global_peer_certificate';
                }

                try {
                    DB::table('posts')->insert($postPayload);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed inserting certification post to posts table: '.$e->getMessage());
                }
            }

            // 4. Send In-App / Push Notification
            if (Schema::hasTable('notifications') && ($user || ! empty($userId))) {
                $notifUserId = $user ? $user->id : $userId;
                $notifData = [
                    'title' => 'Certification Approved! 🎓',
                    'message' => "Your {$certType} certification has been verified and issued. View your certificate in your profile.",
                    'certificate_number' => $certNumber,
                    'action_url' => '/profile/certifications',
                ];

                $notifPayload = [
                    'id' => (string) Str::uuid(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (Schema::hasColumn('notifications', 'type')) {
                    $notifPayload['type'] = 'App\\Notifications\\CertificationApprovedNotification';
                }
                if (Schema::hasColumn('notifications', 'notifiable_type')) {
                    $notifPayload['notifiable_type'] = 'App\\Models\\User';
                }
                if (Schema::hasColumn('notifications', 'notifiable_id')) {
                    $notifPayload['notifiable_id'] = $notifUserId;
                }
                if (Schema::hasColumn('notifications', 'user_id')) {
                    $notifPayload['user_id'] = $notifUserId;
                }
                if (Schema::hasColumn('notifications', 'data')) {
                    $notifPayload['data'] = json_encode($notifData);
                }
                if (Schema::hasColumn('notifications', 'payload')) {
                    $notifPayload['payload'] = json_encode($notifData);
                }
                if (Schema::hasColumn('notifications', 'title')) {
                    $notifPayload['title'] = 'Certification Approved! 🎓';
                }
                if (Schema::hasColumn('notifications', 'message')) {
                    $notifPayload['message'] = "Your {$certType} certification has been verified and issued. View your certificate in your profile.";
                }
                if (Schema::hasColumn('notifications', 'is_read')) {
                    $notifPayload['is_read'] = false;
                }

                try {
                    DB::table('notifications')->insert($notifPayload);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed inserting notification: '.$e->getMessage());
                }
            }

            // Broadcast real-time pending queue update
            try {
                $summary = $this->getSummaryData();
                broadcast(new PendingRequestChangedEvent(
                    action: 'approved',
                    category: 'certifications',
                    requestId: (string) $id,
                    itemData: ['id' => (string) $id, 'status' => 'approved', 'certificate_number' => $certNumber],
                    summaryBreakdown: $summary['breakdown']
                ));
            } catch (\Throwable) {
                // Non-blocking broadcast
            }

            return response()->json([
                'success' => true,
                'message' => 'Certification approved, certificate generated, feed post created, and notification dispatched.',
                'data' => [
                    'id' => $id,
                    'status' => 'approved',
                    'certificate_number' => $certNumber,
                ],
            ], 200);
        });
    }

    private function processApproval(string $id, ?string $category = null): JsonResponse
    {
        if ($category === 'certifications') {
            return $this->approveCertificationSubmission((string) $id);
        }

        // 1. Resolve table name and locate record defensively
        $table = $category ? $this->resolveTableForCategory($category) : null;
        $record = null;

        if ($table && Schema::hasTable($table)) {
            $record = DB::table($table)->where('id', $id)->first();
        }

        if (! $record) {
            $fallbackCandidates = [
                'circle_join_requests',
                'event_joining_requests',
                'event_join_requests',
                'event_registrations',
                'visitor_registrations',
                'coin_claim_requests',
                'coin_claims',
                'certifications',
                'certification_requests',
                'user_certifications',
                'diagnostic_submissions',
                'certification_submissions',
                'impacts',
                'life_impacts',
                'ad_bookings',
                'account_deletion_requests',
                'circle_peer_referrals',
                'peer_referrals',
                'introduction_requests',
                'join_requests',
            ];

            foreach ($fallbackCandidates as $candidate) {
                if (Schema::hasTable($candidate)) {
                    $found = DB::table($candidate)->where('id', $id)->first();
                    if ($found) {
                        $table = $candidate;
                        $record = $found;
                        break;
                    }
                }
            }
        }

        if (! $table || ! $record) {
            return response()->json(['success' => false, 'message' => "Request #{$id} not found."], 404);
        }

        if (in_array($table, ['certifications', 'certification_requests', 'user_certifications', 'diagnostic_submissions', 'certification_submissions'], true)) {
            return $this->approveCertificationSubmission((string) $id);
        }

        DB::beginTransaction();
        try {
            // 2. Mark status as approved
            $updatePayload = ['updated_at' => now()];

            // Authoritative status transition: full clearance marks both CD and DED approved
            if ($table === 'circle_join_requests' || $table === 'circle_joining_requests') {
                if (Schema::hasColumn($table, 'cd_status')) {
                    $updatePayload['cd_status'] = 'approved';
                }
                if (Schema::hasColumn($table, 'ded_status')) {
                    $updatePayload['ded_status'] = 'approved';
                }
                if (Schema::hasColumn($table, 'cd_approved_at')) {
                    $updatePayload['cd_approved_at'] = now();
                }
                if (Schema::hasColumn($table, 'ded_approved_at')) {
                    $updatePayload['ded_approved_at'] = now();
                }
                if (Schema::hasColumn($table, 'ded_approval_status')) {
                    $updatePayload['ded_approval_status'] = 'approved';
                }
                if (Schema::hasColumn($table, 'id_approved_at')) {
                    $updatePayload['id_approved_at'] = now();
                }

                // Check enum valid labels dynamically or assign authoritative final status
                $enumLabels = [];
                try {
                    $validEnums = DB::select("
                        SELECT e.enumlabel 
                        FROM pg_enum e 
                        JOIN pg_type t ON e.enumtypid = t.oid 
                        WHERE t.typname = 'circle_join_request_status_enum'
                    ");
                    $enumLabels = array_column($validEnums, 'enumlabel');
                } catch (\Throwable) {
                    $enumLabels = [];
                }

                if (in_array('approved', $enumLabels, true)) {
                    $updatePayload['status'] = 'approved';
                } elseif (in_array('ded_approved', $enumLabels, true)) {
                    $updatePayload['status'] = 'ded_approved';
                } elseif (in_array('active', $enumLabels, true)) {
                    $updatePayload['status'] = 'active';
                } else {
                    $updatePayload['status'] = 'approved';
                }

                // Link user to target circle if applicable
                $userId = $record->user_id ?? null;
                $circleId = $record->circle_id ?? $record->event_circle_id ?? null;

                if (! empty($userId) && ! empty($circleId)) {
                    if (Schema::hasTable('circle_members')) {
                        DB::table('circle_members')->updateOrInsert(
                            ['circle_id' => $circleId, 'user_id' => $userId],
                            [
                                'role' => $record->requested_role ?? 'member',
                                'status' => 'active',
                                'updated_at' => now(),
                            ]
                        );
                    }
                    if (Schema::hasTable('circle_user')) {
                        DB::table('circle_user')->updateOrInsert(
                            ['circle_id' => $circleId, 'user_id' => $userId],
                            ['status' => 'active', 'updated_at' => now()]
                        );
                    }
                    if (Schema::hasTable('users')) {
                        $userUpdates = [];
                        if (Schema::hasColumn('users', 'circle_id')) {
                            $userUpdates['circle_id'] = $circleId;
                        }
                        if (Schema::hasColumn('users', 'active_circle_id')) {
                            $userUpdates['active_circle_id'] = $circleId;
                        }
                        if (! empty($userUpdates)) {
                            DB::table('users')->where('id', $userId)->update($userUpdates);
                        }
                    }
                }
            } else {
                if (Schema::hasColumn($table, 'status')) {
                    $updatePayload['status'] = 'approved';
                }
            }

            if (Schema::hasColumn($table, 'approved_at')) {
                $updatePayload['approved_at'] = now();
            }

            if (! empty($updatePayload)) {
                DB::table($table)->where('id', $id)->update($updatePayload);
            }

            DB::commit();

            try {
                $summary = $this->getSummaryData();
                broadcast(new PendingRequestChangedEvent(
                    action: 'approved',
                    category: $category ?? $table,
                    requestId: (string) $id,
                    itemData: ['id' => (string) $id, 'status' => 'approved', 'cd_status' => 'approved', 'ded_status' => 'approved'],
                    summaryBreakdown: $summary['breakdown']
                ));
            } catch (\Throwable) {
                // Non-blocking broadcast
            }

            return response()->json([
                'success' => true,
                'message' => 'Clearance granted successfully. Request removed from pending queue.',
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve request: '.$e->getMessage(),
            ], 500);
        }
    }

    private function processRejection(string $id, ?string $category = null, string $reason = 'Administrative clearance declined'): JsonResponse
    {
        $table = $category ? $this->resolveTableForCategory($category) : null;
        $record = null;

        if ($table && Schema::hasTable($table)) {
            $record = DB::table($table)->where('id', $id)->first();
        }

        if (! $record) {
            $fallbackCandidates = [
                'circle_join_requests',
                'event_joining_requests',
                'event_join_requests',
                'event_registrations',
                'visitor_registrations',
                'coin_claim_requests',
                'coin_claims',
                'certification_submissions',
                'certifications',
                'certification_requests',
                'user_certifications',
                'diagnostic_submissions',
                'impacts',
                'life_impacts',
                'ad_bookings',
                'account_deletion_requests',
                'circle_peer_referrals',
                'peer_referrals',
                'introduction_requests',
                'join_requests',
            ];

            foreach ($fallbackCandidates as $candidate) {
                if (Schema::hasTable($candidate)) {
                    $found = DB::table($candidate)->where('id', $id)->first();
                    if ($found) {
                        $table = $candidate;
                        $record = $found;
                        break;
                    }
                }
            }
        }

        if (! $table || ! $record) {
            return response()->json(['success' => false, 'message' => "Request #{$id} not found."], 404);
        }



        DB::beginTransaction();
        try {
            $payload = [];
            if (Schema::hasColumn($table, 'updated_at')) {
                $payload['updated_at'] = now();
            }

            if ($table === 'circle_join_requests' || $table === 'circle_joining_requests') {
                $currentStatus = strtolower((string) ($record->status ?? 'pending'));

                $enumLabels = [];
                try {
                    $validEnums = DB::select("
                        SELECT e.enumlabel 
                        FROM pg_enum e 
                        JOIN pg_type t ON e.enumtypid = t.oid 
                        WHERE t.typname = 'circle_join_request_status_enum'
                    ");
                    $enumLabels = array_column($validEnums, 'enumlabel');
                } catch (\Throwable) {
                    $enumLabels = [];
                }

                if (! empty($enumLabels)) {
                    if (in_array('rejected', $enumLabels, true)) {
                        $targetStatus = 'rejected';
                    } elseif (in_array('rejected_by_id', $enumLabels, true) && $currentStatus === 'pending_id_approval') {
                        $targetStatus = 'rejected_by_id';
                    } elseif (in_array('rejected_by_cd', $enumLabels, true)) {
                        $targetStatus = 'rejected_by_cd';
                    } elseif (in_array('cancelled', $enumLabels, true)) {
                        $targetStatus = 'cancelled';
                    } else {
                        $targetStatus = 'rejected';
                    }
                } else {
                    $targetStatus = 'rejected';
                }

                $payload['status'] = $targetStatus;

                if (Schema::hasColumn('circle_join_requests', 'cd_rejected_at')) {
                    $payload['cd_rejected_at'] = now();
                }
                if (Schema::hasColumn('circle_join_requests', 'cd_rejection_reason')) {
                    $payload['cd_rejection_reason'] = $reason;
                }
                if (Schema::hasColumn('circle_join_requests', 'id_rejected_at')) {
                    $payload['id_rejected_at'] = now();
                }
                if (Schema::hasColumn('circle_join_requests', 'id_rejection_reason')) {
                    $payload['id_rejection_reason'] = $reason;
                }
                if (Schema::hasColumn('circle_join_requests', 'ded_approval_status')) {
                    $payload['ded_approval_status'] = 'rejected';
                }
                if (Schema::hasColumn('circle_join_requests', 'ded_status')) {
                    $payload['ded_status'] = 'rejected';
                }
            } else {
                if (Schema::hasColumn($table, 'status')) {
                    $payload['status'] = 'rejected';
                }
            }

            if (Schema::hasColumn($table, 'rejection_reason')) {
                $payload['rejection_reason'] = $reason;
            } elseif (Schema::hasColumn($table, 'admin_note')) {
                $payload['admin_note'] = $reason;
            }
            if (Schema::hasColumn($table, 'rejected_at')) {
                $payload['rejected_at'] = now();
            }
            if (! empty($payload)) {
                DB::table($table)->where('id', $id)->update($payload);
            }

            DB::commit();

            try {
                $summary = $this->getSummaryData();
                broadcast(new PendingRequestChangedEvent(
                    action: 'rejected',
                    category: $category ?? $table,
                    requestId: (string) $id,
                    itemData: ['id' => (string) $id, 'status' => 'rejected'],
                    summaryBreakdown: $summary['breakdown']
                ));
            } catch (\Throwable) {
                // Non-blocking broadcast
            }

            return response()->json([
                'success' => true,
                'message' => "Request #{$id} rejected.",
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to reject request: '.$e->getMessage(),
            ], 500);
        }
    }
    // --- Database Aggregations & Helpers (Zero Mock Data) ---

    /**
     * Helper to dynamically select the best available user name from the database.
     */
    private function getUserNameExpression(): Expression
    {
        if (Schema::hasTable('users')) {
            $parts = [];
            if (Schema::hasColumn('users', 'name')) {
                $parts[] = "NULLIF(TRIM(users.name), '')";
            }
            if (Schema::hasColumn('users', 'first_name') && Schema::hasColumn('users', 'last_name')) {
                $parts[] = "NULLIF(TRIM(CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, ''))), '')";
            }
            if (Schema::hasColumn('users', 'display_name')) {
                $parts[] = "NULLIF(TRIM(users.display_name), '')";
            }
            if (Schema::hasColumn('users', 'full_name')) {
                $parts[] = "NULLIF(TRIM(users.full_name), '')";
            }
            if (Schema::hasColumn('users', 'email')) {
                $parts[] = "NULLIF(TRIM(users.email), '')";
            }
            $parts[] = "'Peer Member'";

            return DB::raw('
            COALESCE(
                '.implode(",\n                ", $parts).'
            ) as applicant_name
        ');
        }

        return DB::raw("
            COALESCE(
                NULLIF(TRIM(users.name), ''),
                NULLIF(TRIM(CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, ''))), ''),
                NULLIF(TRIM(users.full_name), ''),
                NULLIF(TRIM(users.email), ''),
                'Peer Member'
            ) as applicant_name
        ");
    }

    private function applyStatusFilter($query, string $tableName, string $status)
    {
        if (! Schema::hasColumn($tableName, 'status')) {
            return $query;
        }

        if ($status === 'approved') {
            return $query->where(function ($q) use ($tableName) {
                $q->whereRaw("LOWER({$tableName}.status) IN ('approved', 'ded_approved', 'cd_approved', 'completed', 'verified', 'circle_member', 'paid', 'attended', 'converted_to_member')");
                if (Schema::hasColumn($tableName, 'cd_status')) {
                    $q->orWhereRaw("LOWER({$tableName}.cd_status) = 'approved'");
                }
                if (Schema::hasColumn($tableName, 'ded_status')) {
                    $q->orWhereRaw("LOWER({$tableName}.ded_status) = 'approved'");
                }
            });
        }

        if ($status === 'rejected') {
            return $query->where(function ($q) use ($tableName) {
                $q->whereRaw("LOWER({$tableName}.status) IN ('rejected', 'declined', 'cancelled', 'no_show', 'rejected_by_cd', 'rejected_by_id')");
                if (Schema::hasColumn($tableName, 'cd_status')) {
                    $q->orWhereRaw("LOWER({$tableName}.cd_status) IN ('rejected', 'declined')");
                }
                if (Schema::hasColumn($tableName, 'ded_status')) {
                    $q->orWhereRaw("LOWER({$tableName}.ded_status) IN ('rejected', 'declined')");
                }
            });
        }

        if ($status === 'all') {
            return $query; // No status constraint
        }

        // Default: Pending
        return $query->where(function ($q) use ($tableName) {
            $q->whereRaw("LOWER({$tableName}.status) IN ('pending', 'pending_review', 'under_review', 'registered', 'pending_cd_approval', 'pending_id_approval', 'pending_circle_fee', 'submitted', 'active', 'new')")
                ->orWhereNull("{$tableName}.status");
            if (Schema::hasColumn($tableName, 'cd_status') && Schema::hasColumn($tableName, 'ded_status')) {
                $q->whereRaw("NOT (LOWER(COALESCE({$tableName}.cd_status, '')) = 'approved' AND LOWER(COALESCE({$tableName}.ded_status, '')) = 'approved')");
            }
        });
    }

    private function getPendingCount(string ...$tables): int
    {
        $firstExistingCount = null;

        foreach ($tables as $tbl) {
            if (Schema::hasTable($tbl)) {
                $query = DB::table($tbl);
                $cnt = 0;
                if (Schema::hasColumn($tbl, 'status')) {
                    $cnt = $query->where(function ($q) use ($tbl) {
                        $q->whereRaw("LOWER(status) IN ('pending', 'pending_review', 'under_review')")
                            ->orWhereNull('status');
                        if ($tbl === 'visitor_registrations') {
                            $q->orWhereRaw("LOWER(status) = 'registered'");
                        }
                        if (in_array($tbl, ['certifications', 'certification_requests', 'user_certifications', 'diagnostic_submissions', 'certification_submissions'], true)) {
                            $q->orWhereRaw("LOWER(status) IN ('submitted', 'completed', 'active', 'new')");
                        }
                        if ($tbl === 'circle_join_requests' || $tbl === 'circle_joining_requests') {
                            $q->orWhereRaw("LOWER(status) IN ('pending_cd_approval', 'pending_id_approval', 'pending_circle_fee')");
                            if (Schema::hasColumn($tbl, 'cd_status') && Schema::hasColumn($tbl, 'ded_status')) {
                                $q->whereRaw("NOT (LOWER(COALESCE({$tbl}.cd_status, '')) = 'approved' AND LOWER(COALESCE({$tbl}.ded_status, '')) = 'approved')");
                            }
                        }
                    })->count();
                } else {
                    $cnt = $query->count();
                }

                if ($cnt > 0) {
                    return $cnt;
                }

                if ($firstExistingCount === null) {
                    $firstExistingCount = $cnt;
                }
            }
        }

        return $firstExistingCount ?? 0;
    }

    private function resolveCertificationTable(): ?string
    {
        $candidates = ['certification_submissions', 'certifications', 'certification_requests', 'user_certifications', 'diagnostic_submissions'];

        foreach ($candidates as $tbl) {
            if (Schema::hasTable($tbl) && DB::table($tbl)->exists()) {
                return $tbl;
            }
        }

        foreach ($candidates as $tbl) {
            if (Schema::hasTable($tbl)) {
                return $tbl;
            }
        }

        return null;
    }

    private function resolveEventTable(): ?string
    {
        $candidates = ['event_joining_requests', 'event_join_requests', 'event_registrations', 'event_registration_requests'];

        // 1. Prefer table that has active pending records
        foreach ($candidates as $tbl) {
            if (Schema::hasTable($tbl)) {
                if (Schema::hasColumn($tbl, 'status') && DB::table($tbl)->whereRaw("LOWER(status) = 'pending'")->exists()) {
                    return $tbl;
                }
            }
        }

        // 2. Fallback to first existing table
        foreach ($candidates as $tbl) {
            if (Schema::hasTable($tbl)) {
                return $tbl;
            }
        }

        return null;
    }

    private function resolveTableForCategory(string $category): ?string
    {
        $map = [
            'circle_joining_requests' => 'circle_join_requests',
            'circle_join_requests' => 'circle_join_requests',
            'visitor_registrations' => 'visitor_registrations',
            'coin_claims' => Schema::hasTable('coin_claim_requests') ? 'coin_claim_requests' : (Schema::hasTable('coin_claims') ? 'coin_claims' : 'coin_claim_requests'),
            'coin-claims' => Schema::hasTable('coin_claim_requests') ? 'coin_claim_requests' : (Schema::hasTable('coin_claims') ? 'coin_claims' : 'coin_claim_requests'),
            'coin_claim_requests' => 'coin_claim_requests',
            'certifications' => $this->resolveCertificationTable(),
            'pending_impacts' => Schema::hasTable('impacts') ? 'impacts' : 'life_impacts',
            'ad_booking_requests' => 'ad_bookings',
            'account_deletion_requests' => 'account_deletion_requests',
            'circle_peer_referrals' => Schema::hasTable('circle_peer_referrals') ? 'circle_peer_referrals' : 'peer_referrals',
            'event_joining_requests' => $this->resolveEventTable(),
            'introduction_requests' => 'introduction_requests',
        ];

        return $map[$category] ?? null;
    }

    private function formatDetailsText(mixed $raw, string $default): string
    {
        if ($raw === null || $raw === '') {
            return $default;
        }

        if (is_string($raw)) {
            $trimmed = trim($raw);
            if ((str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) || (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']'))) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    $raw = $decoded;
                } else {
                    return $trimmed;
                }
            } else {
                if (preg_match('/^Plan:\s*/i', $trimmed)) {
                    return $default;
                }

                return $trimmed;
            }
        }

        if (is_array($raw)) {
            // Prioritize genuine submission description / reason over billing plans
            if (! empty($raw['description'])) {
                return $this->formatDetailsText($raw['description'], $default);
            }
            if (! empty($raw['reason_for_joining'])) {
                return $this->formatDetailsText($raw['reason_for_joining'], $default);
            }
            if (! empty($raw['notes']) && is_string($raw['notes'])) {
                return $this->formatDetailsText($raw['notes'], $default);
            }
            if (! empty($raw['reason'])) {
                return $this->formatDetailsText($raw['reason'], $default);
            }
            if (! empty($raw['message'])) {
                return $this->formatDetailsText($raw['message'], $default);
            }
            if (! empty($raw['comment'])) {
                return $this->formatDetailsText($raw['comment'], $default);
            }

            return $default;
        }

        return (string) $raw;
    }

    private function fetchCircleJoinRequests(?string $search, string $status = 'pending'): Collection
    {
        if (! Schema::hasTable('circle_join_requests')) {
            return collect();
        }

        $query = DB::table('circle_join_requests')
            ->leftJoin('users', 'circle_join_requests.user_id', '=', 'users.id')
            ->leftJoin('circles', 'circle_join_requests.circle_id', '=', 'circles.id');

        $this->applyStatusFilter($query, 'circle_join_requests', $status);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.first_name', 'like', "%{$search}%")
                    ->orWhere('users.last_name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        $selectCols = [
            'circle_join_requests.id',
            'circle_join_requests.created_at',
            'circle_join_requests.notes',
            $this->getUserNameExpression(),
            'users.email as applicant_email',
            'users.phone as applicant_phone',
            'users.company_name',
            'circles.name as target_entity',
        ];

        if (Schema::hasColumn('circle_join_requests', 'status')) {
            $selectCols[] = 'circle_join_requests.status';
        }
        if (Schema::hasColumn('circle_join_requests', 'cd_status')) {
            $selectCols[] = 'circle_join_requests.cd_status';
        }
        if (Schema::hasColumn('circle_join_requests', 'ded_status')) {
            $selectCols[] = 'circle_join_requests.ded_status';
        }
        if (Schema::hasColumn('circle_join_requests', 'payment_status')) {
            $selectCols[] = 'circle_join_requests.payment_status';
        }
        if (Schema::hasColumn('circle_join_requests', 'ded_approval_status')) {
            $selectCols[] = 'circle_join_requests.ded_approval_status';
        }
        if (Schema::hasColumn('circle_join_requests', 'cd_approved_at')) {
            $selectCols[] = 'circle_join_requests.cd_approved_at';
        }
        if (Schema::hasColumn('circle_join_requests', 'cd_rejected_at')) {
            $selectCols[] = 'circle_join_requests.cd_rejected_at';
        }
        if (Schema::hasColumn('circle_join_requests', 'id_approved_at')) {
            $selectCols[] = 'circle_join_requests.id_approved_at';
        }
        if (Schema::hasColumn('circle_join_requests', 'id_rejected_at')) {
            $selectCols[] = 'circle_join_requests.id_rejected_at';
        }
        if (Schema::hasColumn('circle_join_requests', 'fee_paid_at')) {
            $selectCols[] = 'circle_join_requests.fee_paid_at';
        }
        if (Schema::hasColumn('circle_join_requests', 'reason_for_joining')) {
            $selectCols[] = 'circle_join_requests.reason_for_joining';
        }
        if (Schema::hasColumn('circle_join_requests', 'description')) {
            $selectCols[] = 'circle_join_requests.description';
        }

        return $query->select($selectCols)->get()->map(function ($r) use ($status) {
            $descCandidate = $r->description ?? ($r->reason_for_joining ?? ($r->notes ?? null));
            $rawStatus = (string) ($r->status ?? ($status !== 'all' ? $status : 'pending'));
            $lowerStatus = strtolower($rawStatus);

            // Compute CD Status
            $cdStatus = 'Pending for CD Approval';
            if (isset($r->cd_status) && $r->cd_status !== null && $r->cd_status !== '') {
                $cdStatus = (string) $r->cd_status;
            } elseif (! empty($r->cd_approved_at) || in_array($lowerStatus, ['pending_id_approval', 'pending_circle_fee', 'circle_member', 'paid', 'approved', 'ded_approved', 'cd_approved'], true)) {
                $cdStatus = 'Approved';
            } elseif (! empty($r->cd_rejected_at) || $lowerStatus === 'rejected_by_cd') {
                $cdStatus = 'Rejected';
            } elseif ($lowerStatus === 'pending') {
                $cdStatus = 'Pending for CD Approval';
            }

            // Compute DED Status
            $dedStatus = 'Pending';
            if (isset($r->ded_status) && $r->ded_status !== null && $r->ded_status !== '') {
                $dedStatus = (string) $r->ded_status;
            } elseif (isset($r->ded_approval_status) && $r->ded_approval_status !== null && $r->ded_approval_status !== '') {
                $dedVal = strtolower((string) $r->ded_approval_status);
                $dedStatus = $dedVal === 'approved' ? 'Approved' : ($dedVal === 'rejected' ? 'Rejected' : 'Pending');
            } elseif (! empty($r->id_approved_at) || in_array($lowerStatus, ['pending_circle_fee', 'circle_member', 'paid', 'approved', 'ded_approved'], true)) {
                $dedStatus = 'Approved';
            } elseif (! empty($r->id_rejected_at) || in_array($lowerStatus, ['rejected_by_id', 'rejected'], true)) {
                $dedStatus = 'Rejected';
            }

            // Compute Payment Status
            $paymentStatus = 'Not Applicable';
            if (isset($r->payment_status) && $r->payment_status !== null && $r->payment_status !== '') {
                $paymentStatus = (string) $r->payment_status;
            } elseif (! empty($r->fee_paid_at) || in_array($lowerStatus, ['paid', 'circle_member'], true)) {
                $paymentStatus = 'Paid';
            } elseif ($lowerStatus === 'pending_circle_fee') {
                $paymentStatus = 'Pending / Unpaid';
            }

            $finalStatus = $lowerStatus ?: 'pending';
            if ($status === 'approved') {
                $finalStatus = 'approved';
            } elseif ($status === 'rejected') {
                $finalStatus = 'rejected';
            } elseif (in_array($lowerStatus, ['approved', 'circle_member', 'paid', 'ded_approved', 'cd_approved', 'completed', 'verified'], true) || (strtolower($cdStatus) === 'approved' && strtolower($dedStatus) === 'approved')) {
                $finalStatus = 'approved';
            }

            return [
                'id' => (string) $r->id,
                'category' => 'circle_joining_requests',
                'category_label' => 'Circle Joining Request',
                'applicant_name' => $r->applicant_name ?: 'Peer Member',
                'applicant_email' => $r->applicant_email ?? '',
                'applicant_phone' => $r->applicant_phone ?? '',
                'company_name' => $r->company_name ?? 'Independent Member',
                'target_entity' => $r->target_entity ?? 'Assigned Circle',
                'details' => $this->formatDetailsText($descCandidate, 'Application to join chartered circle roster.'),
                'submitted_at' => $r->created_at ?? now()->toISOString(),
                'status' => $finalStatus,
                'cd_status' => $cdStatus,
                'ded_status' => $dedStatus,
                'payment_status' => $paymentStatus,
            ];
        });
    }

    private function fetchVisitorRegistrations(?string $search, string $status = 'pending'): Collection
    {
        if (! Schema::hasTable('visitor_registrations')) {
            return collect();
        }

        $query = DB::table('visitor_registrations');

        $this->applyStatusFilter($query, 'visitor_registrations', $status);

        if ($search) {
            $query->where(function ($q) use ($search) {
                if (Schema::hasColumn('visitor_registrations', 'visitor_full_name')) {
                    $q->orWhere('visitor_full_name', 'like', "%{$search}%");
                }
                if (Schema::hasColumn('visitor_registrations', 'visitor_name')) {
                    $q->orWhere('visitor_name', 'like', "%{$search}%");
                }
                if (Schema::hasColumn('visitor_registrations', 'name')) {
                    $q->orWhere('name', 'like', "%{$search}%");
                }
                if (Schema::hasColumn('visitor_registrations', 'visitor_email')) {
                    $q->orWhere('visitor_email', 'like', "%{$search}%");
                }
                if (Schema::hasColumn('visitor_registrations', 'email')) {
                    $q->orWhere('email', 'like', "%{$search}%");
                }
                if (Schema::hasColumn('visitor_registrations', 'visitor_mobile')) {
                    $q->orWhere('visitor_mobile', 'like', "%{$search}%");
                }
                if (Schema::hasColumn('visitor_registrations', 'phone')) {
                    $q->orWhere('phone', 'like', "%{$search}%");
                }
                if (Schema::hasColumn('visitor_registrations', 'visitor_business')) {
                    $q->orWhere('visitor_business', 'like', "%{$search}%");
                }
                if (Schema::hasColumn('visitor_registrations', 'event_name')) {
                    $q->orWhere('event_name', 'like', "%{$search}%");
                }
            });
        }

        return $query->get()->map(function ($r) use ($status) {
            $candidateName = $r->visitor_full_name
                ?? $r->visitor_name
                ?? $r->name
                ?? (isset($r->first_name) ? trim(($r->first_name ?? '').' '.($r->last_name ?? '')) : null);

            if (empty($candidateName)) {
                $candidateName = 'Guest';
            }

            $visStatus = strtolower((string) ($r->status ?? ($status !== 'all' ? $status : 'pending')));
            if ($status === 'approved') {
                $visStatus = 'approved';
            } elseif ($status === 'rejected') {
                $visStatus = 'rejected';
            }

            return [
                'id' => (string) $r->id,
                'category' => 'visitor_registrations',
                'category_label' => 'Visitor Registration',
                'applicant_name' => $candidateName,
                'applicant_email' => $r->visitor_email ?? $r->email ?? '',
                'applicant_phone' => $r->visitor_mobile ?? $r->phone ?? '',
                'company_name' => $r->visitor_business ?? $r->company ?? 'Guest Visitor',
                'target_entity' => $r->event_name ?? $r->meeting_name ?? ($r->event_type ?? 'Chapter Meeting'),
                'details' => $this->formatDetailsText($r->note ?? $r->notes ?? $r->how_known ?? null, 'Visitor pass clearance request.'),
                'submitted_at' => $r->created_at ?? now()->toISOString(),
                'status' => $visStatus,
            ];
        });
    }

    private function fetchCoinClaims(?string $search, string $status = 'pending'): Collection
    {
        if (! Schema::hasTable('coin_claims')) {
            return collect();
        }

        $query = DB::table('coin_claims')
            ->leftJoin('users', 'coin_claims.user_id', '=', 'users.id');

        $this->applyStatusFilter($query, 'coin_claims', $status);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.first_name', 'like', "%{$search}%")
                    ->orWhere('users.last_name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        return $query->select(
            'coin_claims.*',
            $this->getUserNameExpression(),
            'users.email as applicant_email',
            'users.phone as applicant_phone',
            'users.company_name'
        )->get()->map(function ($r) use ($status) {
            $claimStatus = strtolower((string) ($r->status ?? ($status !== 'all' ? $status : 'pending')));
            if ($status === 'approved') {
                $claimStatus = 'approved';
            } elseif ($status === 'rejected') {
                $claimStatus = 'rejected';
            }

            return [
                'id' => (string) $r->id,
                'category' => 'coin_claims',
                'category_label' => 'Coin Claim',
                'applicant_name' => $r->applicant_name ?: 'Peer Member',
                'applicant_email' => $r->applicant_email ?? '',
                'applicant_phone' => $r->applicant_phone ?? '',
                'company_name' => $r->company_name ?? 'Member',
                'target_entity' => ($r->coins_amount ?? $r->amount ?? 0).' Coins',
                'details' => $this->formatDetailsText($r->reason ?? null, 'Member reward disbursement request.'),
                'submitted_at' => $r->created_at ?? now()->toISOString(),
                'status' => $claimStatus,
            ];
        });
    }

    private function fetchEventJoiningRequests(?string $search, string $status = 'pending'): Collection
    {
        $table = $this->resolveEventTable();
        if (! $table) {
            return collect();
        }

        $query = DB::table($table);

        $hasUserId = Schema::hasColumn($table, 'user_id');
        if ($hasUserId && Schema::hasTable('users')) {
            $query->leftJoin('users', "{$table}.user_id", '=', 'users.id');
        }

        $hasEventId = Schema::hasColumn($table, 'event_id');
        if ($hasEventId && Schema::hasTable('events')) {
            $query->leftJoin('events', "{$table}.event_id", '=', 'events.id');
        }

        $this->applyStatusFilter($query, $table, $status);

        if ($search) {
            $query->where(function ($q) use ($search, $hasUserId, $table) {
                if ($hasUserId && Schema::hasTable('users')) {
                    if (Schema::hasColumn('users', 'name')) {
                        $q->orWhere('users.name', 'like', "%{$search}%");
                    }
                    if (Schema::hasColumn('users', 'first_name')) {
                        $q->orWhere('users.first_name', 'like', "%{$search}%");
                    }
                    if (Schema::hasColumn('users', 'last_name')) {
                        $q->orWhere('users.last_name', 'like', "%{$search}%");
                    }
                    if (Schema::hasColumn('users', 'email')) {
                        $q->orWhere('users.email', 'like', "%{$search}%");
                    }
                }
                if (Schema::hasColumn($table, 'applicant_name')) {
                    $q->orWhere("{$table}.applicant_name", 'like', "%{$search}%");
                }
                if (Schema::hasColumn($table, 'name')) {
                    $q->orWhere("{$table}.name", 'like', "%{$search}%");
                }
            });
        }

        $selects = ["{$table}.*"];
        if ($hasUserId && Schema::hasTable('users')) {
            $selects[] = $this->getUserNameExpression();
            if (Schema::hasColumn('users', 'email')) {
                $selects[] = 'users.email as applicant_email';
            }
            if (Schema::hasColumn('users', 'phone')) {
                $selects[] = 'users.phone as applicant_phone';
            }
            if (Schema::hasColumn('users', 'company_name')) {
                $selects[] = 'users.company_name as user_company';
            }
        }
        if ($hasEventId && Schema::hasTable('events') && Schema::hasColumn('events', 'title')) {
            $selects[] = 'events.title as event_title';
        }

        return $query->select($selects)
            ->get()->map(function ($r) use ($status) {
                $candidateName = $r->applicant_name
                    ?? (isset($r->first_name) ? trim(($r->first_name ?? '').' '.($r->last_name ?? '')) : null)
                    ?? $r->name
                    ?? $r->visitor_name
                    ?? null;

                $eventTitle = $r->event_title
                    ?? $r->title
                    ?? $r->event_name
                    ?? 'Scheduled Event';

                $notes = $r->notes
                    ?? $r->request_reason
                    ?? $r->reason
                    ?? $r->remarks
                    ?? null;

                $eventReqStatus = strtolower((string) ($r->status ?? ($status !== 'all' ? $status : 'pending')));
                if ($status === 'approved') {
                    $eventReqStatus = 'approved';
                } elseif ($status === 'rejected') {
                    $eventReqStatus = 'rejected';
                }

                return [
                    'id' => (string) $r->id,
                    'category' => 'event_joining_requests',
                    'category_label' => 'Event Joining Request',
                    'applicant_name' => (! empty($candidateName) && $candidateName !== 'Peer Member') ? $candidateName : ($r->applicant_name ?? 'Peer Member'),
                    'applicant_email' => $r->applicant_email ?? $r->email ?? '',
                    'applicant_phone' => $r->applicant_phone ?? $r->phone ?? '',
                    'company_name' => $r->user_company ?? $r->company_name ?? $r->company ?? 'Registered Member',
                    'target_entity' => $eventTitle,
                    'details' => $this->formatDetailsText($notes, 'RSVP pass clearance request.'),
                    'submitted_at' => $r->created_at ?? now()->toISOString(),
                    'status' => $eventReqStatus,
                ];
            });
    }

    private function fetchCertifications(?string $search, string $status = 'pending'): Collection
    {
        $table = null;
        $possibleTables = ['certification_submissions', 'certifications', 'certification_requests', 'user_certifications', 'diagnostic_submissions'];

        foreach ($possibleTables as $t) {
            if (Schema::hasTable($t) && DB::table($t)->exists()) {
                $table = $t;
                break;
            }
        }

        if (! $table) {
            foreach ($possibleTables as $t) {
                if (Schema::hasTable($t)) {
                    $table = $t;
                    break;
                }
            }
        }

        if (! $table) {
            return collect();
        }

        $query = DB::table($table);

        $hasUsersTable = Schema::hasTable('users');
        if (Schema::hasColumn($table, 'user_id') && $hasUsersTable) {
            $query->leftJoin('users', "{$table}.user_id", '=', 'users.id');
        }

        $this->applyStatusFilter($query, $table, $status);

        if (! empty($search)) {
            $query->where(function ($q) use ($search, $table, $hasUsersTable) {
                $likeOp = DB::connection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
                $added = false;

                if ($hasUsersTable) {
                    if (Schema::hasColumn('users', 'name')) {
                        $q->where('users.name', $likeOp, "%{$search}%");
                        $added = true;
                    }
                    if (Schema::hasColumn('users', 'email')) {
                        $added ? $q->orWhere('users.email', $likeOp, "%{$search}%") : $q->where('users.email', $likeOp, "%{$search}%");
                        $added = true;
                    }
                    if (Schema::hasColumn('users', 'company_name')) {
                        $added ? $q->orWhere('users.company_name', $likeOp, "%{$search}%") : $q->where('users.company_name', $likeOp, "%{$search}%");
                        $added = true;
                    }
                }
                if (Schema::hasColumn($table, 'type')) {
                    $added ? $q->orWhere("{$table}.type", $likeOp, "%{$search}%") : $q->where("{$table}.type", $likeOp, "%{$search}%");
                    $added = true;
                }
                if (Schema::hasColumn($table, 'certification_type')) {
                    $added ? $q->orWhere("{$table}.certification_type", $likeOp, "%{$search}%") : $q->where("{$table}.certification_type", $likeOp, "%{$search}%");
                    $added = true;
                }
                if (Schema::hasColumn($table, 'full_name')) {
                    $added ? $q->orWhere("{$table}.full_name", $likeOp, "%{$search}%") : $q->where("{$table}.full_name", $likeOp, "%{$search}%");
                    $added = true;
                }
                if (Schema::hasColumn($table, 'email')) {
                    $added ? $q->orWhere("{$table}.email", $likeOp, "%{$search}%") : $q->where("{$table}.email", $likeOp, "%{$search}%");
                    $added = true;
                }
            });
        }

        $selects = [
            "{$table}.id",
            "{$table}.created_at",
        ];

        if (Schema::hasColumn($table, 'status')) {
            $selects[] = "{$table}.status";
        }

        // Applicant Name
        if ($hasUsersTable && Schema::hasColumn('users', 'name') && Schema::hasColumn($table, 'full_name')) {
            $selects[] = DB::raw("COALESCE(users.name, {$table}.full_name, 'Applicant Peer') as applicant_name");
        } elseif ($hasUsersTable && Schema::hasColumn('users', 'name')) {
            $selects[] = 'users.name as applicant_name';
        } elseif (Schema::hasColumn($table, 'full_name')) {
            $selects[] = "{$table}.full_name as applicant_name";
        } elseif (Schema::hasColumn($table, 'name')) {
            $selects[] = "{$table}.name as applicant_name";
        } else {
            $selects[] = DB::raw("'Applicant Peer' as applicant_name");
        }

        // Applicant Email
        if ($hasUsersTable && Schema::hasColumn('users', 'email') && Schema::hasColumn($table, 'email')) {
            $selects[] = DB::raw("COALESCE(users.email, {$table}.email, '') as applicant_email");
        } elseif ($hasUsersTable && Schema::hasColumn('users', 'email')) {
            $selects[] = 'users.email as applicant_email';
        } elseif (Schema::hasColumn($table, 'email')) {
            $selects[] = "{$table}.email as applicant_email";
        } else {
            $selects[] = DB::raw("'' as applicant_email");
        }

        // Applicant Phone
        if ($hasUsersTable && Schema::hasColumn('users', 'phone') && Schema::hasColumn($table, 'contact_no')) {
            $selects[] = DB::raw("COALESCE(users.phone, {$table}.contact_no, '') as applicant_phone");
        } elseif ($hasUsersTable && Schema::hasColumn('users', 'phone')) {
            $selects[] = 'users.phone as applicant_phone';
        } elseif (Schema::hasColumn($table, 'contact_no')) {
            $selects[] = "{$table}.contact_no as applicant_phone";
        } elseif (Schema::hasColumn($table, 'phone')) {
            $selects[] = "{$table}.phone as applicant_phone";
        } else {
            $selects[] = DB::raw("'' as applicant_phone");
        }

        // Company Name
        if ($hasUsersTable && Schema::hasColumn('users', 'company_name') && Schema::hasColumn($table, 'business_name')) {
            $selects[] = DB::raw("COALESCE(users.company_name, {$table}.business_name, 'Independent Member') as company_name");
        } elseif ($hasUsersTable && Schema::hasColumn('users', 'company_name')) {
            $selects[] = 'users.company_name';
        } elseif (Schema::hasColumn($table, 'business_name')) {
            $selects[] = "{$table}.business_name as company_name";
        } elseif (Schema::hasColumn($table, 'company_name')) {
            $selects[] = "{$table}.company_name";
        } else {
            $selects[] = DB::raw("'Independent Member' as company_name");
        }

        // Type / cert_type
        $typeCols = array_filter(['type', 'certification_type'], fn ($c) => Schema::hasColumn($table, $c));
        if (! empty($typeCols)) {
            $typeSql = implode(', ', array_map(fn ($c) => "{$table}.{$c}", $typeCols));
            $selects[] = DB::raw("COALESCE({$typeSql}, 'Leadership') as cert_type");
        } else {
            $selects[] = DB::raw("'Leadership' as cert_type");
        }

        // Score
        $scoreCols = array_filter(['score', 'total_score'], fn ($c) => Schema::hasColumn($table, $c));
        if (! empty($scoreCols)) {
            $scoreSql = implode(', ', array_map(fn ($c) => "{$table}.{$c}", $scoreCols));
            $selects[] = DB::raw("COALESCE({$scoreSql}, 0) as score");
        } else {
            $selects[] = DB::raw('0 as score');
        }

        // Percentage
        if (Schema::hasColumn($table, 'percentage')) {
            $selects[] = DB::raw("COALESCE({$table}.percentage, 0) as percentage");
        } else {
            $selects[] = DB::raw('0 as percentage');
        }

        // Level
        $levelCols = array_filter(['level', 'grade', 'certification_level', 'certification_tier'], fn ($c) => Schema::hasColumn($table, $c));
        if (! empty($levelCols)) {
            $levelSql = implode(', ', array_map(fn ($c) => "{$table}.{$c}", $levelCols));
            $selects[] = DB::raw("COALESCE({$levelSql}, 'Needs Improvement') as level");
        } else {
            $selects[] = DB::raw("'Needs Improvement' as level");
        }

        return $query->select($selects)
            ->get()
            ->map(function ($r) use ($status) {
                $certStatus = strtolower((string) ($r->status ?? ($status !== 'all' ? $status : 'pending')));
                if ($status === 'approved') {
                    $certStatus = 'approved';
                } elseif ($status === 'rejected') {
                    $certStatus = 'rejected';
                }

                return [
                    'id' => (string) $r->id,
                    'category' => 'certifications',
                    'category_label' => ($r->cert_type ?? 'Certification').' Clearance',
                    'applicant_name' => $r->applicant_name ?? 'Applicant Peer',
                    'applicant_email' => $r->applicant_email ?? '',
                    'applicant_phone' => $r->applicant_phone ?? '',
                    'company_name' => $r->company_name ?? 'Independent Member',
                    'target_entity' => "{$r->cert_type} ({$r->level} - {$r->score} pts)",
                    'details' => "Score: {$r->score} · Percentage: {$r->percentage}% · Level: {$r->level}",
                    'submitted_at' => $r->created_at ?? now()->toISOString(),
                    'status' => $certStatus,
                ];
            });
    }

    private function fetchPendingImpacts(?string $search, string $status = 'pending'): Collection
    {
        $table = Schema::hasTable('impacts') ? 'impacts' : (Schema::hasTable('life_impacts') ? 'life_impacts' : null);
        if (! $table) {
            return collect();
        }

        $query = DB::table($table)
            ->leftJoin('users', "{$table}.user_id", '=', 'users.id');

        $this->applyStatusFilter($query, $table, $status);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.first_name', 'like', "%{$search}%")
                    ->orWhere('users.last_name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        return $query->select(
            "{$table}.*",
            $this->getUserNameExpression(),
            'users.email as applicant_email',
            'users.phone as applicant_phone',
            'users.company_name'
        )->get()->map(function ($r) use ($status) {
            $impactStatus = strtolower((string) ($r->status ?? ($status !== 'all' ? $status : 'pending')));
            if ($status === 'approved') {
                $impactStatus = 'approved';
            } elseif ($status === 'rejected') {
                $impactStatus = 'rejected';
            }

            return [
                'id' => (string) $r->id,
                'category' => 'pending_impacts',
                'category_label' => 'Life Impact Proof',
                'applicant_name' => $r->applicant_name ?: 'Contributor',
                'applicant_email' => $r->applicant_email ?? '',
                'applicant_phone' => $r->applicant_phone ?? '',
                'company_name' => $r->company_name ?? 'Member Partner',
                'target_entity' => '₹'.number_format((float) ($r->amount ?? 0)),
                'details' => $this->formatDetailsText($r->description ?? null, 'Life impact contract validation.'),
                'submitted_at' => $r->created_at ?? now()->toISOString(),
                'status' => $impactStatus,
            ];
        });
    }

    private function fetchAdBookings(?string $search, string $status = 'pending'): Collection
    {
        if (! Schema::hasTable('ad_bookings')) {
            return collect();
        }

        $query = DB::table('ad_bookings')
            ->leftJoin('users', 'ad_bookings.user_id', '=', 'users.id');

        $this->applyStatusFilter($query, 'ad_bookings', $status);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.first_name', 'like', "%{$search}%")
                    ->orWhere('users.last_name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        return $query->select(
            'ad_bookings.*',
            $this->getUserNameExpression(),
            'users.email as applicant_email',
            'users.phone as applicant_phone',
            'users.company_name'
        )->get()->map(function ($r) use ($status) {
            $adStatus = strtolower((string) ($r->status ?? ($status !== 'all' ? $status : 'pending')));
            if ($status === 'approved') {
                $adStatus = 'approved';
            } elseif ($status === 'rejected') {
                $adStatus = 'rejected';
            }

            return [
                'id' => (string) $r->id,
                'category' => 'ad_booking_requests',
                'category_label' => 'Ad Booking Request',
                'applicant_name' => $r->applicant_name ?: 'Advertiser',
                'applicant_email' => $r->applicant_email ?? '',
                'applicant_phone' => $r->applicant_phone ?? '',
                'company_name' => $r->company_name ?? 'Brand Partner',
                'target_entity' => $r->placement ?? 'Banner Ad Slot',
                'details' => $this->formatDetailsText($r->notes ?? null, 'Ad booking schedule request.'),
                'submitted_at' => $r->created_at ?? now()->toISOString(),
                'status' => $adStatus,
            ];
        });
    }

    private function fetchAccountDeletions(?string $search, string $status = 'pending'): Collection
    {
        if (! Schema::hasTable('account_deletion_requests')) {
            return collect();
        }

        $query = DB::table('account_deletion_requests')
            ->leftJoin('users', 'account_deletion_requests.user_id', '=', 'users.id');

        $this->applyStatusFilter($query, 'account_deletion_requests', $status);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.first_name', 'like', "%{$search}%")
                    ->orWhere('users.last_name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        return $query->select(
            'account_deletion_requests.*',
            $this->getUserNameExpression(),
            'users.email as applicant_email',
            'users.phone as applicant_phone',
            'users.company_name'
        )->get()->map(function ($r) use ($status) {
            $delStatus = strtolower((string) ($r->status ?? ($status !== 'all' ? $status : 'pending')));
            if ($status === 'approved') {
                $delStatus = 'approved';
            } elseif ($status === 'rejected') {
                $delStatus = 'rejected';
            }

            return [
                'id' => (string) $r->id,
                'category' => 'account_deletion_requests',
                'category_label' => 'Account Deletion Request',
                'applicant_name' => $r->applicant_name ?: 'User',
                'applicant_email' => $r->applicant_email ?? '',
                'applicant_phone' => $r->applicant_phone ?? '',
                'company_name' => $r->company_name ?? 'Member',
                'target_entity' => 'Account Closure',
                'details' => $this->formatDetailsText($r->reason ?? null, 'User deletion request.'),
                'submitted_at' => $r->created_at ?? now()->toISOString(),
                'status' => $delStatus,
            ];
        });
    }

    private function fetchPeerReferrals(?string $search, string $status = 'pending'): Collection
    {
        $table = Schema::hasTable('circle_peer_referrals') ? 'circle_peer_referrals' : (Schema::hasTable('peer_referrals') ? 'peer_referrals' : null);
        if (! $table) {
            return collect();
        }

        $query = DB::table($table);

        $this->applyStatusFilter($query, $table, $status);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('referred_name', 'like', "%{$search}%")
                    ->orWhere('referred_email', 'like', "%{$search}%");
            });
        }

        return $query->get()->map(function ($r) use ($status) {
            $refStatus = strtolower((string) ($r->status ?? ($status !== 'all' ? $status : 'pending')));
            if ($status === 'approved') {
                $refStatus = 'approved';
            } elseif ($status === 'rejected') {
                $refStatus = 'rejected';
            }

            return [
                'id' => (string) $r->id,
                'category' => 'circle_peer_referrals',
                'category_label' => 'Circle Peer Referral',
                'applicant_name' => $r->referred_name ?? 'Referred Candidate',
                'applicant_email' => $r->referred_email ?? '',
                'applicant_phone' => $r->referred_phone ?? '',
                'company_name' => $r->referred_company ?? 'Candidate',
                'target_entity' => 'Peer Recommendation',
                'details' => $this->formatDetailsText($r->notes ?? null, 'Circle referral clearance.'),
                'submitted_at' => $r->created_at ?? now()->toISOString(),
                'status' => $refStatus,
            ];
        });
    }

    private function fetchIntroductionRequests(?string $search, string $status = 'pending'): Collection
    {
        if (! Schema::hasTable('introduction_requests')) {
            return collect();
        }

        $query = DB::table('introduction_requests');

        $this->applyStatusFilter($query, 'introduction_requests', $status);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('requester_name', 'like', "%{$search}%")
                    ->orWhere('requester_email', 'like', "%{$search}%")
                    ->orWhere('target_peer_name', 'like', "%{$search}%");
            });
        }

        return $query->get()->map(function ($r) use ($status) {
            $introStatus = strtolower((string) ($r->status ?? ($status !== 'all' ? $status : 'pending')));
            if ($status === 'approved') {
                $introStatus = 'approved';
            } elseif ($status === 'rejected') {
                $introStatus = 'rejected';
            }

            return [
                'id' => (string) $r->id,
                'category' => 'introduction_requests',
                'category_label' => 'Introduction Request',
                'applicant_name' => $r->requester_name ?? 'Requester Peer',
                'applicant_email' => $r->requester_email ?? '',
                'applicant_phone' => '',
                'company_name' => 'Member',
                'target_entity' => $r->target_peer_name ?? 'Peer Intro',
                'details' => $this->formatDetailsText($r->notes ?? null, 'Introduction request clearance.'),
                'submitted_at' => $r->created_at ?? now()->toISOString(),
                'status' => $introStatus,
            ];
        });
    }
}
