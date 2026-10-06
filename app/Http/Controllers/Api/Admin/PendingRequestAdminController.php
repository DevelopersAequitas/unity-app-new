<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Events\PendingRequestChangedEvent;
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
            'coin_claims' => $this->getPendingCount('coin_claims'),
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

        return [
            'total_pending' => array_sum($breakdown),
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
        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 20);

        /** @var Collection $results */
        $results = collect();

        // 1. Circle Joining Requests
        if ($category === 'all' || $category === 'circle_joining_requests') {
            $results = $results->concat($this->fetchCircleJoinRequests($search));
        }

        // 2. Visitor Registrations
        if ($category === 'all' || $category === 'visitor_registrations') {
            $results = $results->concat($this->fetchVisitorRegistrations($search));
        }

        // 3. Coin Claims
        if ($category === 'all' || $category === 'coin_claims') {
            $results = $results->concat($this->fetchCoinClaims($search));
        }

        // 4. Certifications
        if ($category === 'all' || $category === 'certifications') {
            $results = $results->concat($this->fetchCertifications($search));
        }

        // 5. Pending Impacts
        if ($category === 'all' || $category === 'pending_impacts') {
            $results = $results->concat($this->fetchPendingImpacts($search));
        }

        // 6. Ad Booking Requests
        if ($category === 'all' || $category === 'ad_booking_requests') {
            $results = $results->concat($this->fetchAdBookings($search));
        }

        // 7. Account Deletion Requests
        if ($category === 'all' || $category === 'account_deletion_requests') {
            $results = $results->concat($this->fetchAccountDeletions($search));
        }

        // 8. Event Joining Requests
        if ($category === 'all' || $category === 'event_joining_requests') {
            $results = $results->concat($this->fetchEventJoiningRequests($search));
        }

        // 9. Circle Peer Referrals
        if ($category === 'all' || $category === 'circle_peer_referrals') {
            $results = $results->concat($this->fetchPeerReferrals($search));
        }

        // 10. Introduction Requests
        if ($category === 'all' || $category === 'introduction_requests') {
            $results = $results->concat($this->fetchIntroductionRequests($search));
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
            $updatePayload = [];
            if (Schema::hasColumn($table, 'updated_at')) {
                $updatePayload['updated_at'] = now();
            }
            if (Schema::hasColumn($table, 'status')) {
                $updatePayload['status'] = 'approved';
            }
            if (Schema::hasColumn($table, 'approved_at')) {
                $updatePayload['approved_at'] = now();
            }
            if (! empty($updatePayload)) {
                DB::table($table)->where('id', $id)->update($updatePayload);
            }

            // 3. Link user to circle safely (check schema first to prevent SQL crash)
            $userId = $record->user_id ?? null;
            $circleId = $record->circle_id ?? $record->event_circle_id ?? null;

            if ($userId && $circleId) {
                // If users table has circle_id or active_circle_id column
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

                // If pivot table circle_user exists
                if (Schema::hasTable('circle_user')) {
                    $pivotData = [];
                    if (Schema::hasColumn('circle_user', 'status')) {
                        $pivotData['status'] = 'active';
                    }
                    if (Schema::hasColumn('circle_user', 'updated_at')) {
                        $pivotData['updated_at'] = now();
                    }
                    DB::table('circle_user')->updateOrInsert(
                        ['user_id' => $userId, 'circle_id' => $circleId],
                        $pivotData
                    );
                }

                // If pivot table circle_members exists
                if (Schema::hasTable('circle_members')) {
                    $pivotData = [];
                    if (Schema::hasColumn('circle_members', 'status')) {
                        $pivotData['status'] = 'active';
                    }
                    if (Schema::hasColumn('circle_members', 'updated_at')) {
                        $pivotData['updated_at'] = now();
                    }
                    DB::table('circle_members')->updateOrInsert(
                        ['user_id' => $userId, 'circle_id' => $circleId],
                        $pivotData
                    );
                }
            }

            DB::commit();

            try {
                $summary = $this->getSummaryData();
                broadcast(new PendingRequestChangedEvent(
                    action: 'approved',
                    category: $category ?? $table,
                    requestId: (string) $id,
                    itemData: ['id' => (string) $id, 'status' => 'approved'],
                    summaryBreakdown: $summary['breakdown']
                ));
            } catch (\Throwable) {
                // Non-blocking broadcast
            }

            return response()->json([
                'success' => true,
                'message' => 'Request approved and clearance granted successfully.',
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
            if (Schema::hasColumn($table, 'status')) {
                $payload['status'] = 'rejected';
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

    private function getPendingCount(string ...$tables): int
    {
        $firstExistingCount = null;

        foreach ($tables as $tbl) {
            if (Schema::hasTable($tbl)) {
                $q = DB::table($tbl);
                $cnt = Schema::hasColumn($tbl, 'status')
                    ? $q->where('status', 'pending')->count()
                    : $q->count();

                if ($cnt > 0) {
                    return $cnt;
                }

                if ($firstExistingCount === null) {
                    $firstExistingCount = $cnt;
                }
            }
        }

        return $firstExistingCount ?? 0; // Exactly 0 if table does not exist or has 0 rows
    }

    private function resolveEventTable(): ?string
    {
        $candidates = ['event_joining_requests', 'event_join_requests', 'event_registrations', 'event_registration_requests'];

        // 1. Prefer table that has active pending records
        foreach ($candidates as $tbl) {
            if (Schema::hasTable($tbl)) {
                if (Schema::hasColumn($tbl, 'status') && DB::table($tbl)->where('status', 'pending')->exists()) {
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
            'coin_claims' => 'coin_claims',
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

    private function fetchCircleJoinRequests(?string $search): Collection
    {
        if (! Schema::hasTable('circle_join_requests')) {
            return collect();
        }

        $query = DB::table('circle_join_requests')
            ->leftJoin('users', 'circle_join_requests.user_id', '=', 'users.id')
            ->leftJoin('circles', 'circle_join_requests.circle_id', '=', 'circles.id')
            ->where('circle_join_requests.status', 'pending');

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

        if (Schema::hasColumn('circle_join_requests', 'reason_for_joining')) {
            $selectCols[] = 'circle_join_requests.reason_for_joining';
        }
        if (Schema::hasColumn('circle_join_requests', 'description')) {
            $selectCols[] = 'circle_join_requests.description';
        }

        return $query->select($selectCols)->get()->map(function ($r) {
            $descCandidate = $r->description ?? ($r->reason_for_joining ?? ($r->notes ?? null));

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
            ];
        });
    }

    private function fetchVisitorRegistrations(?string $search): Collection
    {
        if (! Schema::hasTable('visitor_registrations')) {
            return collect();
        }

        $query = DB::table('visitor_registrations')->where('status', 'pending');
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->get()->map(function ($r) {
            $candidateName = isset($r->first_name) ? trim(($r->first_name ?? '').' '.($r->last_name ?? '')) : null;
            if (empty($candidateName)) {
                $candidateName = $r->name ?? $r->visitor_name ?? 'Guest';
            }

            return [
                'id' => (string) $r->id,
                'category' => 'visitor_registrations',
                'category_label' => 'Visitor Registration',
                'applicant_name' => $candidateName,
                'applicant_email' => $r->email ?? '',
                'applicant_phone' => $r->phone ?? '',
                'company_name' => $r->company ?? 'Guest Visitor',
                'target_entity' => $r->meeting_name ?? 'Chapter Meeting',
                'details' => $this->formatDetailsText($r->notes ?? null, 'Visitor pass clearance request.'),
                'submitted_at' => $r->created_at ?? now()->toISOString(),
            ];
        });
    }

    private function fetchCoinClaims(?string $search): Collection
    {
        if (! Schema::hasTable('coin_claims')) {
            return collect();
        }

        $query = DB::table('coin_claims')
            ->leftJoin('users', 'coin_claims.user_id', '=', 'users.id')
            ->where('coin_claims.status', 'pending');

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
        ]);
    }

    private function fetchEventJoiningRequests(?string $search): Collection
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

        if (Schema::hasColumn($table, 'status')) {
            $query->where("{$table}.status", 'pending');
        }

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
            ->get()->map(function ($r) {
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
                ];
            });
    }

    private function fetchCertifications(?string $search): Collection
    {
        $table = Schema::hasTable('certification_requests') ? 'certification_requests' : (Schema::hasTable('certifications') ? 'certifications' : null);
        if (! $table) {
            return collect();
        }

        $query = DB::table($table)
            ->leftJoin('users', "{$table}.user_id", '=', 'users.id')
            ->where("{$table}.status", 'pending');

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
        ]);
    }

    private function fetchPendingImpacts(?string $search): Collection
    {
        $table = Schema::hasTable('impacts') ? 'impacts' : (Schema::hasTable('life_impacts') ? 'life_impacts' : null);
        if (! $table) {
            return collect();
        }

        $query = DB::table($table)
            ->leftJoin('users', "{$table}.user_id", '=', 'users.id')
            ->where("{$table}.status", 'pending');

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
        ]);
    }

    private function fetchAdBookings(?string $search): Collection
    {
        if (! Schema::hasTable('ad_bookings')) {
            return collect();
        }

        $query = DB::table('ad_bookings')
            ->leftJoin('users', 'ad_bookings.user_id', '=', 'users.id')
            ->where('ad_bookings.status', 'pending');

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
        ]);
    }

    private function fetchAccountDeletions(?string $search): Collection
    {
        if (! Schema::hasTable('account_deletion_requests')) {
            return collect();
        }

        $query = DB::table('account_deletion_requests')
            ->leftJoin('users', 'account_deletion_requests.user_id', '=', 'users.id')
            ->where('account_deletion_requests.status', 'pending');

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
        ]);
    }

    private function fetchPeerReferrals(?string $search): Collection
    {
        $table = Schema::hasTable('circle_peer_referrals') ? 'circle_peer_referrals' : (Schema::hasTable('peer_referrals') ? 'peer_referrals' : null);
        if (! $table) {
            return collect();
        }

        $query = DB::table($table)->where('status', 'pending');

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
        ]);
    }

    private function fetchIntroductionRequests(?string $search): Collection
    {
        if (! Schema::hasTable('introduction_requests')) {
            return collect();
        }

        $query = DB::table('introduction_requests')->where('status', 'pending');

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
        ]);
    }
}
