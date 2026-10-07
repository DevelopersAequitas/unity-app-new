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
            'certifications' => $this->getPendingCount('certification_requests', 'certifications'),
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
        if ($category === 'all' || $category === 'circle_joining_requests') {
            $results = $results->concat($this->fetchCircleJoinRequests($search, $status));
        }

        // 2. Visitor Registrations
        if ($category === 'all' || $category === 'visitor_registrations') {
            $results = $results->concat($this->fetchVisitorRegistrations($search, $status));
        }

        // 3. Coin Claims
        if ($category === 'all' || $category === 'coin_claims') {
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
        if ($category === 'all' || $category === 'account_deletion_requests') {
            $results = $results->concat($this->fetchAccountDeletions($search, $status));
        }

        // 8. Event Joining Requests
        if ($category === 'all' || $category === 'event_joining_requests') {
            $results = $results->concat($this->fetchEventJoiningRequests($search, $status));
        }

        // 9. Circle Peer Referrals
        if ($category === 'all' || $category === 'circle_peer_referrals') {
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
        return $this->processApproval($id, $category);
    }

    /**
     * Fallback approve handler when category is omitted in URL
     */
    public function approveDirect(Request $request, string $id): JsonResponse
    {
        $category = $request->input('category') ?? $request->input('type');

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

    private function processApproval(string $id, ?string $category = null): JsonResponse
    {
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
                'certification_requests',
                'certifications',
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
                'certification_requests',
                'certifications',
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

    private function applyStatusFilter($query, string $table, string $status): void
    {
        if ($status === 'all' || ! Schema::hasColumn($table, 'status')) {
            return;
        }

        if ($table === 'visitor_registrations') {
            if ($status === 'pending') {
                $query->where(function ($q) {
                    $q->whereRaw("LOWER(status) IN ('pending', 'pending_review', 'under_review', 'registered')")
                        ->orWhereNull('status');
                });
            } elseif ($status === 'approved') {
                $query->where(function ($q) {
                    $q->whereRaw("LOWER(status) IN ('approved', 'attended', 'converted_to_member')");
                });
            } elseif ($status === 'rejected') {
                $query->where(function ($q) {
                    $q->whereRaw("LOWER(status) IN ('rejected', 'no_show', 'declined')");
                });
            } else {
                $query->whereRaw('LOWER(status) = ?', [$status]);
            }

            return;
        }

        if ($table === 'circle_join_requests' || $table === 'circle_joining_requests') {
            if ($status === 'pending') {
                $query->where(function ($q) {
                    $q->whereRaw("LOWER(status) IN ('pending', 'pending_review', 'under_review', 'pending_cd_approval', 'pending_id_approval', 'pending_circle_fee')")
                        ->orWhereNull('status');
                });
            } elseif ($status === 'approved') {
                $query->where(function ($q) {
                    $q->whereRaw("LOWER(status) IN ('approved', 'circle_member', 'paid', 'cd_approved', 'ded_approved')");
                });
            } elseif ($status === 'rejected') {
                $query->where(function ($q) {
                    $q->whereRaw("LOWER(status) IN ('rejected', 'rejected_by_cd', 'rejected_by_id', 'cancelled', 'declined')");
                });
            } else {
                $query->whereRaw('LOWER(status) = ?', [$status]);
            }

            return;
        }

        if ($status === 'pending') {
            $query->where(function ($q) use ($table) {
                $q->whereRaw("LOWER({$table}.status) IN ('pending', 'pending_review', 'under_review')")
                    ->orWhereNull("{$table}.status");
            });
        } else {
            $query->whereRaw("LOWER({$table}.status) = ?", [$status]);
        }
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
            'certifications' => Schema::hasTable('certification_requests') ? 'certification_requests' : 'certifications',
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
                'status' => $lowerStatus ?: 'pending',
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
                'status' => strtolower((string) ($r->status ?? ($status !== 'all' ? $status : 'pending'))),
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
        )->get()->map(fn ($r) => [
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
            'status' => strtolower((string) ($r->status ?? ($status !== 'all' ? $status : 'pending'))),
        ]);
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
                    'status' => strtolower((string) ($r->status ?? ($status !== 'all' ? $status : 'pending'))),
                ];
            });
    }

    private function fetchCertifications(?string $search, string $status = 'pending'): Collection
    {
        $table = Schema::hasTable('certification_requests') ? 'certification_requests' : (Schema::hasTable('certifications') ? 'certifications' : null);
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
        )->get()->map(fn ($r) => [
            'id' => (string) $r->id,
            'category' => 'certifications',
            'category_label' => 'Certification Clearance',
            'applicant_name' => $r->applicant_name ?: 'Candidate',
            'applicant_email' => $r->applicant_email ?? '',
            'applicant_phone' => $r->applicant_phone ?? '',
            'company_name' => $r->company_name ?? 'Member',
            'target_entity' => $r->certificate_title ?? $r->title ?? 'Certificate Verification',
            'details' => $this->formatDetailsText($r->description ?? null, 'Credential verification review.'),
            'submitted_at' => $r->created_at ?? now()->toISOString(),
            'status' => strtolower((string) ($r->status ?? ($status !== 'all' ? $status : 'pending'))),
        ]);
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
        )->get()->map(fn ($r) => [
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
            'status' => strtolower((string) ($r->status ?? ($status !== 'all' ? $status : 'pending'))),
        ]);
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
        )->get()->map(fn ($r) => [
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
            'status' => strtolower((string) ($r->status ?? ($status !== 'all' ? $status : 'pending'))),
        ]);
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
        )->get()->map(fn ($r) => [
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
            'status' => strtolower((string) ($r->status ?? ($status !== 'all' ? $status : 'pending'))),
        ]);
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

        return $query->get()->map(fn ($r) => [
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
            'status' => strtolower((string) ($r->status ?? ($status !== 'all' ? $status : 'pending'))),
        ]);
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

        return $query->get()->map(fn ($r) => [
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
            'status' => strtolower((string) ($r->status ?? ($status !== 'all' ? $status : 'pending'))),
        ]);
    }
}
