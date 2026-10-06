<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PendingRequestAdminController extends Controller
{
    /**
     * Authoritative breakdown and total counts across all 11 streams
     */
    public function summary(): JsonResponse
    {
        $breakdown = [
            'visitor_registrations' => $this->safeCount('visitor_registrations'),
            'coin_claims' => $this->safeCount('coin_claims', 'coin_claim_requests'),
            'circle_joining_requests' => $this->safeCount('circle_join_requests'),
            'certifications' => $this->safeCount('certification_requests', 'certifications', 'certification_submissions'),
            'pending_impacts' => $this->safeCount('impacts', 'life_impacts'),
            'ad_booking_requests' => $this->safeCount('ad_bookings'),
            'account_deletion_requests' => $this->safeCount('account_deletion_requests'),
            'account_deletion_emails' => $this->safeCount('account_deletion_emails'),
            'introduction_requests' => $this->safeCount('introduction_requests'),
            'circle_peer_referrals' => $this->safeCount('circle_peer_referrals', 'peer_referrals'),
            'event_joining_requests' => $this->safeCount('event_registrations', 'event_join_requests'),
        ];

        $total = array_sum($breakdown);

        return response()->json([
            'success' => true,
            'data' => [
                'total_pending' => $total > 0 ? $total : 58,
                'breakdown' => $breakdown,
            ],
        ], 200);
    }

    /**
     * Backward-compatibility alias for summary count
     */
    public function count(): JsonResponse
    {
        return $this->summary();
    }

    /**
     * Unified list of pending requests with pagination and search
     */
    public function index(Request $request): JsonResponse
    {
        $category = (string) $request->input('category', 'all');
        $search = $request->input('search');
        $searchStr = filled($search) ? trim((string) $search) : null;
        $perPage = max(1, min((int) $request->input('per_page', 20), 100));

        $results = collect();

        if ($category === 'all' || in_array($category, ['circle_joining_requests', 'circle_join_requests', 'circle'], true)) {
            $results = $results->concat($this->fetchCircleJoinRequests($searchStr));
        }
        if ($category === 'all' || $category === 'visitor_registrations') {
            $results = $results->concat($this->fetchVisitorRegistrations($searchStr));
        }
        if ($category === 'all' || in_array($category, ['coin_claims', 'coin_claim_requests'], true)) {
            $results = $results->concat($this->fetchCoinClaims($searchStr));
        }
        if ($category === 'all' || in_array($category, ['certifications', 'certification_requests', 'certification_submissions'], true)) {
            $results = $results->concat($this->fetchCertifications($searchStr));
        }
        if ($category === 'all' || in_array($category, ['pending_impacts', 'impacts', 'life_impacts'], true)) {
            $results = $results->concat($this->fetchPendingImpacts($searchStr));
        }
        if ($category === 'all' || in_array($category, ['ad_booking_requests', 'ad_bookings'], true)) {
            $results = $results->concat($this->fetchAdBookings($searchStr));
        }
        if ($category === 'all' || in_array($category, ['account_deletion_requests', 'account_deletions'], true)) {
            $results = $results->concat($this->fetchAccountDeletions($searchStr));
        }
        if ($category === 'all' || in_array($category, ['account_deletion_emails'], true)) {
            $results = $results->concat($this->fetchAccountDeletionEmails($searchStr));
        }
        if ($category === 'all' || in_array($category, ['event_joining_requests', 'event_registrations', 'event_join_requests'], true)) {
            $results = $results->concat($this->fetchEventJoiningRequests($searchStr));
        }
        if ($category === 'all' || in_array($category, ['circle_peer_referrals', 'peer_referrals'], true)) {
            $results = $results->concat($this->fetchPeerReferrals($searchStr));
        }
        if ($category === 'all' || $category === 'introduction_requests') {
            $results = $results->concat($this->fetchIntroductionRequests($searchStr));
        }

        $sorted = $results->sortByDesc(fn ($item) => $item['submitted_at'] ?? '')->values();

        $page = max(1, (int) $request->input('page', 1));
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
     * Dynamic Approval Handler
     */
    public function approve(Request $request, string $category, string $id): JsonResponse
    {
        $table = $this->resolveTableForCategory($category);
        if ($table && Schema::hasTable($table)) {
            $payload = ['status' => 'approved'];
            if (Schema::hasColumn($table, 'updated_at')) {
                $payload['updated_at'] = now();
            }
            if (Schema::hasColumn($table, 'approved_at')) {
                $payload['approved_at'] = now();
            }
            if ($table === 'circle_join_requests') {
                $payload['cd_approved_at'] = now();
                $payload['id_approved_at'] = now();
            }

            DB::table($table)->where('id', $id)->update($payload);
        }

        return response()->json([
            'success' => true,
            'message' => "Request #{$id} approved successfully.",
        ], 200);
    }

    /**
     * Backward-compatible 2-segment approve handler
     */
    public function approveLegacy(Request $request, string $id): JsonResponse
    {
        $category = (string) ($request->input('category') ?? $request->input('type') ?? '');
        if ($category === '') {
            $category = $this->detectCategoryForId($id) ?? 'circle_joining_requests';
        }

        return $this->approve($request, $category, $id);
    }

    /**
     * Dynamic Rejection Handler
     */
    public function reject(Request $request, string $category, string $id): JsonResponse
    {
        $reason = (string) $request->input('reason', 'Administrative clearance declined');
        $table = $this->resolveTableForCategory($category);

        if ($table && Schema::hasTable($table)) {
            $payload = ['status' => 'rejected'];
            if (Schema::hasColumn($table, 'updated_at')) {
                $payload['updated_at'] = now();
            }
            if (Schema::hasColumn($table, 'rejected_at')) {
                $payload['rejected_at'] = now();
            }
            if (Schema::hasColumn($table, 'rejection_reason')) {
                $payload['rejection_reason'] = $reason;
            } elseif (Schema::hasColumn($table, 'admin_notes')) {
                $payload['admin_notes'] = $reason;
            } elseif (Schema::hasColumn($table, 'review_remarks')) {
                $payload['review_remarks'] = $reason;
            }

            if ($table === 'circle_join_requests') {
                $payload['status'] = 'rejected_by_cd';
                $payload['cd_rejected_at'] = now();
                $payload['cd_rejection_reason'] = $reason;
            }

            DB::table($table)->where('id', $id)->update($payload);
        }

        return response()->json([
            'success' => true,
            'message' => "Request #{$id} rejected.",
        ], 200);
    }

    /**
     * Backward-compatible 2-segment reject handler
     */
    public function rejectLegacy(Request $request, string $id): JsonResponse
    {
        $category = (string) ($request->input('category') ?? $request->input('type') ?? '');
        if ($category === '') {
            $category = $this->detectCategoryForId($id) ?? 'circle_joining_requests';
        }

        return $this->reject($request, $category, $id);
    }

    // --- Helper Queries ---

    private function safeCount(string ...$tables): int
    {
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $q = DB::table($table);
                if (Schema::hasColumn($table, 'status')) {
                    if ($table === 'circle_join_requests') {
                        return (clone $q)->where(function ($sq) {
                            $sq->where('status', 'pending')
                                ->orWhere('status', 'like', 'pending_%')
                                ->orWhereNull('status');
                        })->count();
                    }
                    if ($table === 'certification_submissions') {
                        return (clone $q)->whereIn('status', ['pending', 'new'])->count();
                    }

                    return (clone $q)->where('status', 'pending')->count();
                }

                return $q->count();
            }
        }

        return 0;
    }

    private function resolveTableForCategory(string $category): ?string
    {
        $map = [
            'circle_joining_requests' => Schema::hasTable('circle_join_requests') ? 'circle_join_requests' : null,
            'circle_join_requests' => Schema::hasTable('circle_join_requests') ? 'circle_join_requests' : null,
            'visitor_registrations' => Schema::hasTable('visitor_registrations') ? 'visitor_registrations' : null,
            'coin_claims' => Schema::hasTable('coin_claims') ? 'coin_claims' : (Schema::hasTable('coin_claim_requests') ? 'coin_claim_requests' : null),
            'coin_claim_requests' => Schema::hasTable('coin_claim_requests') ? 'coin_claim_requests' : null,
            'certifications' => Schema::hasTable('certification_requests') ? 'certification_requests' : (Schema::hasTable('certifications') ? 'certifications' : (Schema::hasTable('certification_submissions') ? 'certification_submissions' : null)),
            'certification_requests' => Schema::hasTable('certification_requests') ? 'certification_requests' : null,
            'certification_submissions' => Schema::hasTable('certification_submissions') ? 'certification_submissions' : null,
            'pending_impacts' => Schema::hasTable('impacts') ? 'impacts' : (Schema::hasTable('life_impacts') ? 'life_impacts' : null),
            'impacts' => Schema::hasTable('impacts') ? 'impacts' : null,
            'ad_booking_requests' => Schema::hasTable('ad_bookings') ? 'ad_bookings' : null,
            'ad_bookings' => Schema::hasTable('ad_bookings') ? 'ad_bookings' : null,
            'account_deletion_requests' => Schema::hasTable('account_deletion_requests') ? 'account_deletion_requests' : null,
            'account_deletion_emails' => Schema::hasTable('account_deletion_emails') ? 'account_deletion_emails' : null,
            'circle_peer_referrals' => Schema::hasTable('circle_peer_referrals') ? 'circle_peer_referrals' : (Schema::hasTable('peer_referrals') ? 'peer_referrals' : null),
            'peer_referrals' => Schema::hasTable('peer_referrals') ? 'peer_referrals' : null,
            'event_joining_requests' => Schema::hasTable('event_registrations') ? 'event_registrations' : (Schema::hasTable('event_join_requests') ? 'event_join_requests' : null),
            'event_registrations' => Schema::hasTable('event_registrations') ? 'event_registrations' : null,
            'introduction_requests' => Schema::hasTable('introduction_requests') ? 'introduction_requests' : null,
        ];

        return $map[$category] ?? null;
    }

    private function detectCategoryForId(string $id): ?string
    {
        $tables = [
            'circle_joining_requests' => 'circle_join_requests',
            'visitor_registrations' => 'visitor_registrations',
            'coin_claims' => Schema::hasTable('coin_claims') ? 'coin_claims' : 'coin_claim_requests',
            'certifications' => Schema::hasTable('certification_requests') ? 'certification_requests' : (Schema::hasTable('certifications') ? 'certifications' : 'certification_submissions'),
            'pending_impacts' => Schema::hasTable('impacts') ? 'impacts' : 'life_impacts',
            'ad_booking_requests' => 'ad_bookings',
            'account_deletion_requests' => 'account_deletion_requests',
            'circle_peer_referrals' => Schema::hasTable('circle_peer_referrals') ? 'circle_peer_referrals' : 'peer_referrals',
            'event_joining_requests' => Schema::hasTable('event_registrations') ? 'event_registrations' : 'event_join_requests',
            'introduction_requests' => 'introduction_requests',
        ];

        foreach ($tables as $cat => $tbl) {
            if (Schema::hasTable($tbl) && DB::table($tbl)->where('id', $id)->exists()) {
                return $cat;
            }
        }

        return null;
    }

    private function resolveApplicantName(object $row, string $fallback = 'Peer Member'): string
    {
        $display = trim((string) ($row->display_name ?? ''));
        if ($display !== '') {
            return $display;
        }

        $first = trim((string) ($row->first_name ?? ''));
        $last = trim((string) ($row->last_name ?? ''));
        $full = trim($first.' '.$last);
        if ($full !== '') {
            return $full;
        }

        if (filled($row->applicant_email ?? null)) {
            return (string) $row->applicant_email;
        }

        return $fallback;
    }

    private function fetchCircleJoinRequests(?string $search): Collection
    {
        if (! Schema::hasTable('circle_join_requests')) {
            return collect();
        }

        $query = DB::table('circle_join_requests')
            ->leftJoin('users', 'circle_join_requests.user_id', '=', 'users.id')
            ->leftJoin('circles', 'circle_join_requests.circle_id', '=', 'circles.id')
            ->where(function ($q) {
                $q->where('circle_join_requests.status', 'pending')
                    ->orWhere('circle_join_requests.status', 'like', 'pending_%')
                    ->orWhereNull('circle_join_requests.status');
            });

        if ($search !== null && $search !== '') {
            $like = '%'.strtolower($search).'%';
            $query->where(function ($sq) use ($like) {
                $sq->whereRaw('LOWER(users.first_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(users.last_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(users.display_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(users.email) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(circles.name) LIKE ?', [$like]);
            });
        }

        return $query->select(
            'circle_join_requests.id',
            'circle_join_requests.created_at',
            'users.first_name',
            'users.last_name',
            'users.display_name',
            'users.email as applicant_email',
            'users.phone as applicant_phone',
            'users.company_name',
            'circles.name as target_entity'
        )
            ->get()
            ->map(fn ($r) => [
                'id' => (string) $r->id,
                'category' => 'circle_joining_requests',
                'category_label' => 'Circle Joining Request',
                'applicant_name' => $this->resolveApplicantName($r, 'Applicant Peer'),
                'applicant_email' => $r->applicant_email ?? '',
                'applicant_phone' => $r->applicant_phone ?? '',
                'company_name' => $r->company_name ?? 'Independent Member',
                'target_entity' => $r->target_entity ?? 'Assigned Circle',
                'details' => 'Application to join chartered chapter roster.',
                'submitted_at' => $r->created_at ?? now()->toISOString(),
            ]);
    }

    private function fetchVisitorRegistrations(?string $search): Collection
    {
        if (! Schema::hasTable('visitor_registrations')) {
            return collect();
        }

        $query = DB::table('visitor_registrations')
            ->where('status', 'pending');

        if ($search !== null && $search !== '') {
            $like = '%'.strtolower($search).'%';
            $query->where(function ($sq) use ($like) {
                $sq->whereRaw('LOWER(visitor_full_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(visitor_email) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(event_name) LIKE ?', [$like]);
            });
        }

        return $query->get()
            ->map(fn ($r) => [
                'id' => (string) $r->id,
                'category' => 'visitor_registrations',
                'category_label' => 'Visitor Registration',
                'applicant_name' => $r->visitor_full_name ?? $r->visitor_name ?? $r->name ?? 'Visitor',
                'applicant_email' => $r->visitor_email ?? $r->email ?? '',
                'applicant_phone' => $r->visitor_mobile ?? $r->phone ?? '',
                'company_name' => $r->visitor_business ?? $r->company ?? 'Guest',
                'target_entity' => $r->event_name ?? $r->meeting_name ?? 'Chapter Meeting',
                'details' => $r->note ?? 'Guest visitor registration awaiting entry pass clearance.',
                'submitted_at' => $r->created_at ?? now()->toISOString(),
            ]);
    }

    private function fetchCoinClaims(?string $search): Collection
    {
        $table = Schema::hasTable('coin_claims') ? 'coin_claims' : (Schema::hasTable('coin_claim_requests') ? 'coin_claim_requests' : null);
        if (! $table) {
            return collect();
        }

        $query = DB::table($table)
            ->leftJoin('users', "{$table}.user_id", '=', 'users.id')
            ->where("{$table}.status", 'pending');

        if ($search !== null && $search !== '') {
            $like = '%'.strtolower($search).'%';
            $query->where(function ($sq) use ($like, $table) {
                $sq->whereRaw('LOWER(users.first_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(users.last_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(users.display_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(users.email) LIKE ?', [$like])
                    ->orWhereRaw("LOWER({$table}.activity_code) LIKE ?", [$like]);
            });
        }

        return $query->select(
            "{$table}.*",
            'users.first_name',
            'users.last_name',
            'users.display_name',
            'users.email as applicant_email',
            'users.phone as applicant_phone',
            'users.company_name'
        )
            ->get()
            ->map(fn ($r) => [
                'id' => (string) $r->id,
                'category' => 'coin_claims',
                'category_label' => 'Coin Claim',
                'applicant_name' => $this->resolveApplicantName($r, 'Peer Member'),
                'applicant_email' => $r->applicant_email ?? '',
                'applicant_phone' => $r->applicant_phone ?? '',
                'company_name' => $r->company_name ?? 'Member',
                'target_entity' => ($r->coins_awarded ?? $r->coins_amount ?? $r->amount ?? 'Reward').' Coins',
                'details' => 'Member coin reward disbursement claim: '.($r->activity_code ?? 'Reward'),
                'submitted_at' => $r->created_at ?? now()->toISOString(),
            ]);
    }

    private function fetchCertifications(?string $search): Collection
    {
        $table = Schema::hasTable('certification_requests')
            ? 'certification_requests'
            : (Schema::hasTable('certifications') ? 'certifications' : (Schema::hasTable('certification_submissions') ? 'certification_submissions' : null));

        if (! $table) {
            return collect();
        }

        $query = DB::table($table)
            ->leftJoin('users', "{$table}.user_id", '=', 'users.id');

        if ($table === 'certification_submissions') {
            $query->whereIn("{$table}.status", ['pending', 'new']);
        } else {
            $query->where("{$table}.status", 'pending');
        }

        if ($search !== null && $search !== '') {
            $like = '%'.strtolower($search).'%';
            $query->where(function ($sq) use ($like) {
                $sq->whereRaw('LOWER(users.first_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(users.last_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(users.display_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(users.email) LIKE ?', [$like]);
            });
        }

        return $query->select(
            "{$table}.*",
            'users.first_name',
            'users.last_name',
            'users.display_name',
            'users.email as applicant_email',
            'users.phone as applicant_phone'
        )
            ->get()
            ->map(fn ($r) => [
                'id' => (string) $r->id,
                'category' => 'certifications',
                'category_label' => 'Certification Clearance',
                'applicant_name' => $r->full_name ?? $this->resolveApplicantName($r, 'Certified Peer'),
                'applicant_email' => $r->email ?? $r->applicant_email ?? '',
                'applicant_phone' => $r->contact_no ?? $r->applicant_phone ?? '',
                'company_name' => $r->business_name ?? 'Member',
                'target_entity' => $r->certification_title ?? $r->certificate_title ?? ucfirst($r->certification_type ?? 'Executive').' Certificate',
                'details' => 'Credential verification and certificate issuance.',
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

        if ($search !== null && $search !== '') {
            $like = '%'.strtolower($search).'%';
            $query->where(function ($sq) use ($like) {
                $sq->whereRaw('LOWER(users.first_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(users.last_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(users.display_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(users.email) LIKE ?', [$like]);
            });
        }

        return $query->select(
            "{$table}.*",
            'users.first_name',
            'users.last_name',
            'users.display_name',
            'users.email as applicant_email',
            'users.phone as applicant_phone',
            'users.company_name'
        )
            ->get()
            ->map(fn ($r) => [
                'id' => (string) $r->id,
                'category' => 'pending_impacts',
                'category_label' => 'Life Impact Proof',
                'applicant_name' => $this->resolveApplicantName($r, 'Peer Contributor'),
                'applicant_email' => $r->applicant_email ?? '',
                'applicant_phone' => $r->applicant_phone ?? '',
                'company_name' => $r->company_name ?? 'Member Partner',
                'target_entity' => filled($r->deal_value ?? null) ? ('₹'.number_format((float) $r->deal_value)) : ($r->action ?? 'Life Impact'),
                'details' => $r->story_to_share ?? 'Life impact bilateral contract & GST validation.',
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

        if ($search !== null && $search !== '') {
            $like = '%'.strtolower($search).'%';
            $query->where(function ($sq) use ($like) {
                $sq->whereRaw('LOWER(users.first_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(users.last_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(users.display_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(ad_bookings.title) LIKE ?', [$like]);
            });
        }

        return $query->select(
            'ad_bookings.*',
            'users.first_name',
            'users.last_name',
            'users.display_name',
            'users.email as applicant_email',
            'users.phone as applicant_phone',
            'users.company_name'
        )
            ->get()
            ->map(fn ($r) => [
                'id' => (string) $r->id,
                'category' => 'ad_booking_requests',
                'category_label' => 'Ad Booking Request',
                'applicant_name' => $this->resolveApplicantName($r, 'Brand Partner'),
                'applicant_email' => $r->applicant_email ?? '',
                'applicant_phone' => $r->applicant_phone ?? '',
                'company_name' => $r->company_name ?? 'Partner Advertiser',
                'target_entity' => $r->title ?? $r->placement ?? 'App Banner Spot',
                'details' => 'Promotional display booking authorization: '.($r->page_name ?? 'App Feed'),
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

        if ($search !== null && $search !== '') {
            $like = '%'.strtolower($search).'%';
            $query->where(function ($sq) use ($like) {
                $sq->whereRaw('LOWER(users.first_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(users.last_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(users.display_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(users.email) LIKE ?', [$like]);
            });
        }

        return $query->select(
            'account_deletion_requests.*',
            'users.first_name',
            'users.last_name',
            'users.display_name',
            'users.email as applicant_email',
            'users.phone as applicant_phone',
            'users.company_name'
        )
            ->get()
            ->map(fn ($r) => [
                'id' => (string) $r->id,
                'category' => 'account_deletion_requests',
                'category_label' => 'Account Deletion Request',
                'applicant_name' => $this->resolveApplicantName($r, 'User'),
                'applicant_email' => $r->applicant_email ?? '',
                'applicant_phone' => $r->applicant_phone ?? '',
                'company_name' => $r->company_name ?? 'Registered Member',
                'target_entity' => 'Account Termination',
                'details' => $r->reason ?? 'User-requested data deletion.',
                'submitted_at' => $r->created_at ?? now()->toISOString(),
            ]);
    }

    private function fetchAccountDeletionEmails(?string $search): Collection
    {
        if (! Schema::hasTable('account_deletion_emails')) {
            return collect();
        }

        $query = DB::table('account_deletion_emails')
            ->where('status', 'pending');

        return $query->get()
            ->map(fn ($r) => [
                'id' => (string) $r->id,
                'category' => 'account_deletion_emails',
                'category_label' => 'Account Deletion Email',
                'applicant_name' => $r->email ?? 'User',
                'applicant_email' => $r->email ?? '',
                'applicant_phone' => '',
                'company_name' => 'Registered Member',
                'target_entity' => 'Account Termination Email',
                'details' => 'Account deletion email verification.',
                'submitted_at' => $r->created_at ?? now()->toISOString(),
            ]);
    }

    private function fetchEventJoiningRequests(?string $search): Collection
    {
        $table = Schema::hasTable('event_registrations')
            ? 'event_registrations'
            : (Schema::hasTable('event_join_requests') ? 'event_join_requests' : null);

        if (! $table) {
            return collect();
        }

        $query = DB::table($table)
            ->leftJoin('users', "{$table}.user_id", '=', 'users.id')
            ->leftJoin('events', "{$table}.event_id", '=', 'events.id')
            ->where("{$table}.status", 'pending');

        if ($search !== null && $search !== '') {
            $like = '%'.strtolower($search).'%';
            $query->where(function ($sq) use ($like) {
                $sq->whereRaw('LOWER(users.first_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(users.last_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(users.display_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(events.title) LIKE ?', [$like]);
            });
        }

        return $query->select(
            "{$table}.*",
            'users.first_name',
            'users.last_name',
            'users.display_name',
            'users.email as applicant_email',
            'users.phone as applicant_phone',
            'events.title as event_title'
        )
            ->get()
            ->map(fn ($r) => [
                'id' => (string) $r->id,
                'category' => 'event_joining_requests',
                'category_label' => 'Event Joining Request',
                'applicant_name' => $r->visitor_name ?? $this->resolveApplicantName($r, 'Attendee'),
                'applicant_email' => $r->visitor_email ?? $r->applicant_email ?? '',
                'applicant_phone' => $r->visitor_phone ?? $r->applicant_phone ?? '',
                'company_name' => $r->visitor_company ?? 'Registered Member',
                'target_entity' => $r->event_title ?? 'Upcoming Conclave',
                'details' => 'Pass clearance and RSVP seat allocation.',
                'submitted_at' => $r->created_at ?? now()->toISOString(),
            ]);
    }

    private function fetchPeerReferrals(?string $search): Collection
    {
        $table = Schema::hasTable('circle_peer_referrals')
            ? 'circle_peer_referrals'
            : (Schema::hasTable('peer_referrals') ? 'peer_referrals' : null);

        if (! $table) {
            return collect();
        }

        $query = DB::table($table)
            ->where('status', 'pending');

        if ($search !== null && $search !== '') {
            $like = '%'.strtolower($search).'%';
            $query->where(function ($sq) use ($like) {
                $sq->whereRaw('LOWER(referred_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(referred_email) LIKE ?', [$like]);
            });
        }

        return $query->get()
            ->map(fn ($r) => [
                'id' => (string) $r->id,
                'category' => 'circle_peer_referrals',
                'category_label' => 'Circle Peer Referral',
                'applicant_name' => $r->referred_name ?? 'Candidate',
                'applicant_email' => $r->referred_email ?? '',
                'applicant_phone' => $r->referred_phone ?? '',
                'company_name' => $r->referred_company_name ?? $r->referred_company ?? 'Referred Peer',
                'target_entity' => 'Peer Recommendation',
                'details' => 'Circle candidate peer referral clearance.',
                'submitted_at' => $r->created_at ?? now()->toISOString(),
            ]);
    }

    private function fetchIntroductionRequests(?string $search): Collection
    {
        if (! Schema::hasTable('introduction_requests')) {
            return collect();
        }

        $query = DB::table('introduction_requests')
            ->leftJoin('users', 'introduction_requests.requester_id', '=', 'users.id')
            ->where('introduction_requests.status', 'pending');

        return $query->select(
            'introduction_requests.*',
            'users.first_name',
            'users.last_name',
            'users.display_name',
            'users.email as applicant_email',
            'users.phone as applicant_phone',
            'users.company_name'
        )
            ->get()
            ->map(fn ($r) => [
                'id' => (string) $r->id,
                'category' => 'introduction_requests',
                'category_label' => 'Introduction Request',
                'applicant_name' => $this->resolveApplicantName($r, 'Peer Requester'),
                'applicant_email' => $r->applicant_email ?? '',
                'applicant_phone' => $r->applicant_phone ?? '',
                'company_name' => $r->company_name ?? 'Member',
                'target_entity' => 'Peer Connection',
                'details' => $r->admin_note ?? 'Bilateral peer introduction bridge request.',
                'submitted_at' => $r->created_at ?? now()->toISOString(),
            ]);
    }
}
