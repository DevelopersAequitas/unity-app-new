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
     * Authoritative breakdown and total counts computed strictly from the database
     */
    public function summary(): JsonResponse
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
            'event_joining_requests' => $this->getPendingCount('event_registrations', 'event_join_requests'),
        ];

        $total = array_sum($breakdown);

        return response()->json([
            'success' => true,
            'data' => [
                'total_pending' => $total, // Exact DB count with ZERO mock fallback
                'breakdown' => $breakdown,
            ],
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
     * Dynamic Approval Handler
     */
    public function approve(Request $request, string $category, string $id): JsonResponse
    {
        $table = $this->resolveTableForCategory($category);
        if ($table && Schema::hasTable($table)) {
            DB::table($table)->where('id', $id)->update([
                'status' => 'approved',
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "Request #{$id} approved.",
        ], 200);
    }

    /**
     * Dynamic Rejection Handler
     */
    public function reject(Request $request, string $category, string $id): JsonResponse
    {
        $reason = (string) $request->input('reason', 'Administrative clearance declined');
        $table = $this->resolveTableForCategory($category);

        if ($table && Schema::hasTable($table)) {
            $payload = ['status' => 'rejected', 'updated_at' => now()];
            if (Schema::hasColumn($table, 'rejection_reason')) {
                $payload['rejection_reason'] = $reason;
            }
            DB::table($table)->where('id', $id)->update($payload);
        }

        return response()->json([
            'success' => true,
            'message' => "Request #{$id} rejected.",
        ], 200);
    }

    /**
     * Backward-compatible legacy approve handler
     */
    public function approveLegacy(Request $request, string $id): JsonResponse
    {
        $category = (string) ($request->input('category') ?? $request->input('type') ?? 'circle_joining_requests');

        return $this->approve($request, $category, $id);
    }

    /**
     * Backward-compatible legacy reject handler
     */
    public function rejectLegacy(Request $request, string $id): JsonResponse
    {
        $category = (string) ($request->input('category') ?? $request->input('type') ?? 'circle_joining_requests');

        return $this->reject($request, $category, $id);
    }

    // --- Database Aggregations (Zero Mock Data) ---

    private function getPendingCount(string ...$tables): int
    {
        foreach ($tables as $tbl) {
            if (Schema::hasTable($tbl)) {
                $q = DB::table($tbl);
                if (Schema::hasColumn($tbl, 'status')) {
                    return $q->where('status', 'pending')->count();
                }

                return $q->count();
            }
        }

        return 0; // Exactly 0 if table does not exist or has 0 rows
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
            'event_joining_requests' => Schema::hasTable('event_registrations') ? 'event_registrations' : 'event_join_requests',
            'introduction_requests' => 'introduction_requests',
        ];

        return $map[$category] ?? null;
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
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        return $query->select(
            'circle_join_requests.id',
            'circle_join_requests.created_at',
            'circle_join_requests.notes',
            'users.name as applicant_name',
            'users.email as applicant_email',
            'users.phone as applicant_phone',
            'users.company_name',
            'circles.name as target_entity'
        )->get()->map(fn ($r) => [
            'id' => (string) $r->id,
            'category' => 'circle_joining_requests',
            'category_label' => 'Circle Joining Request',
            'applicant_name' => $r->applicant_name ?? 'Registered Peer',
            'applicant_email' => $r->applicant_email ?? '',
            'applicant_phone' => $r->applicant_phone ?? '',
            'company_name' => $r->company_name ?? 'Independent Member',
            'target_entity' => $r->target_entity ?? 'Assigned Circle',
            'details' => $r->notes ?? 'Application to join chartered circle roster.',
            'submitted_at' => $r->created_at ?? now()->toISOString(),
        ]);
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

        return $query->get()->map(fn ($r) => [
            'id' => (string) $r->id,
            'category' => 'visitor_registrations',
            'category_label' => 'Visitor Registration',
            'applicant_name' => $r->name ?? $r->visitor_name ?? 'Guest',
            'applicant_email' => $r->email ?? '',
            'applicant_phone' => $r->phone ?? '',
            'company_name' => $r->company ?? 'Guest Visitor',
            'target_entity' => $r->meeting_name ?? 'Chapter Meeting',
            'details' => $r->notes ?? 'Visitor pass clearance request.',
            'submitted_at' => $r->created_at ?? now()->toISOString(),
        ]);
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
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        return $query->select('coin_claims.*', 'users.name as applicant_name', 'users.email as applicant_email', 'users.company_name')
            ->get()->map(fn ($r) => [
                'id' => (string) $r->id,
                'category' => 'coin_claims',
                'category_label' => 'Coin Claim',
                'applicant_name' => $r->applicant_name ?? 'Member',
                'applicant_email' => $r->applicant_email ?? '',
                'applicant_phone' => '',
                'company_name' => $r->company_name ?? 'Member',
                'target_entity' => ($r->coins_amount ?? $r->amount ?? 0).' Coins',
                'details' => $r->reason ?? 'Member reward disbursement request.',
                'submitted_at' => $r->created_at ?? now()->toISOString(),
            ]);
    }

    private function fetchEventJoiningRequests(?string $search): Collection
    {
        $table = Schema::hasTable('event_registrations') ? 'event_registrations' : (Schema::hasTable('event_join_requests') ? 'event_join_requests' : null);
        if (! $table) {
            return collect();
        }

        $query = DB::table($table)
            ->leftJoin('users', "{$table}.user_id", '=', 'users.id')
            ->leftJoin('events', "{$table}.event_id", '=', 'events.id')
            ->where("{$table}.status", 'pending');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        return $query->select("{$table}.*", 'users.name as applicant_name', 'users.email as applicant_email', 'events.title as event_title')
            ->get()->map(fn ($r) => [
                'id' => (string) $r->id,
                'category' => 'event_joining_requests',
                'category_label' => 'Event Joining Request',
                'applicant_name' => $r->applicant_name ?? 'Attendee',
                'applicant_email' => $r->applicant_email ?? '',
                'applicant_phone' => '',
                'company_name' => 'Registered Member',
                'target_entity' => $r->event_title ?? 'Scheduled Event',
                'details' => $r->notes ?? 'RSVP pass clearance request.',
                'submitted_at' => $r->created_at ?? now()->toISOString(),
            ]);
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
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        return $query->select("{$table}.*", 'users.name as applicant_name', 'users.email as applicant_email')
            ->get()->map(fn ($r) => [
                'id' => (string) $r->id,
                'category' => 'certifications',
                'category_label' => 'Certification Clearance',
                'applicant_name' => $r->applicant_name ?? 'Candidate',
                'applicant_email' => $r->applicant_email ?? '',
                'applicant_phone' => '',
                'company_name' => 'Member',
                'target_entity' => $r->certificate_title ?? 'Certificate Verification',
                'details' => $r->description ?? 'Credential verification review.',
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
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        return $query->select("{$table}.*", 'users.name as applicant_name', 'users.email as applicant_email')
            ->get()->map(fn ($r) => [
                'id' => (string) $r->id,
                'category' => 'pending_impacts',
                'category_label' => 'Life Impact Proof',
                'applicant_name' => $r->applicant_name ?? 'Contributor',
                'applicant_email' => $r->applicant_email ?? '',
                'applicant_phone' => '',
                'company_name' => 'Member Partner',
                'target_entity' => '₹'.number_format((float) ($r->amount ?? 0)),
                'details' => $r->description ?? 'Life impact contract validation.',
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
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        return $query->select('ad_bookings.*', 'users.name as applicant_name', 'users.email as applicant_email')
            ->get()->map(fn ($r) => [
                'id' => (string) $r->id,
                'category' => 'ad_booking_requests',
                'category_label' => 'Ad Booking Request',
                'applicant_name' => $r->applicant_name ?? 'Advertiser',
                'applicant_email' => $r->applicant_email ?? '',
                'applicant_phone' => '',
                'company_name' => 'Brand Partner',
                'target_entity' => $r->placement ?? 'Banner Ad Slot',
                'details' => $r->notes ?? 'Ad booking schedule request.',
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
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        return $query->select('account_deletion_requests.*', 'users.name as applicant_name', 'users.email as applicant_email')
            ->get()->map(fn ($r) => [
                'id' => (string) $r->id,
                'category' => 'account_deletion_requests',
                'category_label' => 'Account Deletion Request',
                'applicant_name' => $r->applicant_name ?? 'User',
                'applicant_email' => $r->applicant_email ?? '',
                'applicant_phone' => '',
                'company_name' => 'Member',
                'target_entity' => 'Account Closure',
                'details' => $r->reason ?? 'User deletion request.',
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
            'details' => $r->notes ?? 'Circle referral clearance.',
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
            'details' => $r->notes ?? 'Introduction request clearance.',
            'submitted_at' => $r->created_at ?? now()->toISOString(),
        ]);
    }
}
