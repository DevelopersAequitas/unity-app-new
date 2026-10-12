<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\AdminUser;
use App\Models\CertificationSubmission;
use App\Models\CircleJoinRequest;
use App\Models\CoinClaimRequest;
use App\Models\EntrepreneurCertificationSubmission;
use App\Models\Impact;
use App\Models\LeadershipCertificationSubmission;
use App\Models\Payment;
use App\Models\User;
use App\Services\Certifications\CertificateGeneratorService;
use App\Services\Circles\CircleJoinRequestService;
use App\Services\Coins\CoinsService;
use App\Services\Membership\MembershipUpgradeService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PendingRequestModerationService
{
    public function __construct(
        private readonly CircleJoinRequestService $circleJoinService,
        private readonly MembershipUpgradeService $membershipUpgradeService,
        private readonly CertificateGeneratorService $certificateGeneratorService,
        private readonly CoinsService $coinsService,
        private readonly AdminAuditService $auditService,
        private readonly AdminScopeService $scopeService,
    ) {}

    /**
     * Retrieve consolidated pending requests with filters, search, and pagination.
     */
    public function listPendingRequests(array $filters, User|AdminUser $actor): array
    {
        $type = (string) ($filters['type'] ?? 'all');
        $status = (string) ($filters['status'] ?? 'pending');
        $search = trim((string) ($filters['search'] ?? ''));
        $circleId = $filters['circle_id'] ?? null;
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $circleIds = $this->scopeService->visibleCircleIds($actor);
        $isGlobal = $this->scopeService->isGlobal($actor);

        // Normalize single-source queries
        if (in_array($type, ['circle_join_request', 'circle_join', 'circle'], true)) {
            $paginator = $this->queryCircleJoinRequests($status, $search, $circleId, $dateFrom, $dateTo, $circleIds, $isGlobal)
                ->paginate($perPage, ['*'], 'page', $page);

            return [
                'items' => collect($paginator->items())->map(fn ($item) => $this->mapCircleJoinRequest($item))->values()->all(),
                'pagination' => $this->paginationMeta($paginator),
            ];
        }

        if (in_array($type, ['membership_upgrade', 'membership'], true)) {
            $paginator = $this->queryMembershipUpgrades($status, $search, $circleId, $dateFrom, $dateTo, $circleIds, $isGlobal)
                ->paginate($perPage, ['*'], 'page', $page);

            return [
                'items' => collect($paginator->items())->map(fn ($item) => $this->mapPaymentMembership($item))->values()->all(),
                'pagination' => $this->paginationMeta($paginator),
            ];
        }

        if ($type === 'certification') {
            $paginator = $this->queryCertifications($status, $search, $dateFrom, $dateTo)
                ->paginate($perPage, ['*'], 'page', $page);

            return [
                'items' => collect($paginator->items())->map(fn ($item) => $this->mapCertification($item))->values()->all(),
                'pagination' => $this->paginationMeta($paginator),
            ];
        }

        if ($type === 'coin_claim') {
            $paginator = $this->queryCoinClaims($status, $search, $dateFrom, $dateTo, $circleIds, $isGlobal)
                ->paginate($perPage, ['*'], 'page', $page);

            return [
                'items' => collect($paginator->items())->map(fn ($item) => $this->mapCoinClaim($item))->values()->all(),
                'pagination' => $this->paginationMeta($paginator),
            ];
        }

        if ($type === 'impact') {
            $paginator = $this->queryImpacts($status, $search, $dateFrom, $dateTo)
                ->paginate($perPage, ['*'], 'page', $page);

            return [
                'items' => collect($paginator->items())->map(fn ($item) => $this->mapImpact($item))->values()->all(),
                'pagination' => $this->paginationMeta($paginator),
            ];
        }

        // Consolidated query: 'proof' / 'proofs'
        if (in_array($type, ['proof', 'proofs'], true)) {
            return $this->aggregateSources([
                'certifications' => fn () => $this->queryCertifications($status, $search, $dateFrom, $dateTo),
                'coin_claims' => fn () => $this->queryCoinClaims($status, $search, $dateFrom, $dateTo, $circleIds, $isGlobal),
                'impacts' => fn () => $this->queryImpacts($status, $search, $dateFrom, $dateTo),
            ], $page, $perPage);
        }

        // Consolidated 'all': Circle Join Requests + Membership Upgrades + Proofs
        return $this->aggregateSources([
            'circle_join_requests' => fn () => $this->queryCircleJoinRequests($status, $search, $circleId, $dateFrom, $dateTo, $circleIds, $isGlobal),
            'membership_upgrades' => fn () => $this->queryMembershipUpgrades($status, $search, $circleId, $dateFrom, $dateTo, $circleIds, $isGlobal),
            'certifications' => fn () => $this->queryCertifications($status, $search, $dateFrom, $dateTo),
            'coin_claims' => fn () => $this->queryCoinClaims($status, $search, $dateFrom, $dateTo, $circleIds, $isGlobal),
            'impacts' => fn () => $this->queryImpacts($status, $search, $dateFrom, $dateTo),
        ], $page, $perPage);
    }

    /**
     * Compute summary counts across all pending request categories.
     */
    public function getCounts(User|AdminUser $actor): array
    {
        $circleIds = $this->scopeService->visibleCircleIds($actor);
        $isGlobal = $this->scopeService->isGlobal($actor);

        $circleJoinCount = $this->queryCircleJoinRequests('pending', '', null, null, null, $circleIds, $isGlobal)->count();
        $membershipUpgradeCount = $this->queryMembershipUpgrades('pending', '', null, null, null, $circleIds, $isGlobal)->count();
        $certificationCount = $this->queryCertifications('pending', '', null, null)->count();
        $coinClaimCount = $this->queryCoinClaims('pending', '', null, null, $circleIds, $isGlobal)->count();
        $impactCount = $this->queryImpacts('pending', '', null, null)->count();

        $proofsCount = $certificationCount + $coinClaimCount + $impactCount;
        $total = $circleJoinCount + $membershipUpgradeCount + $proofsCount;

        return [
            'total' => $total,
            'circle_join_requests' => $circleJoinCount,
            'membership_upgrades' => $membershipUpgradeCount,
            'proofs' => $proofsCount,
            'by_type' => [
                'circle_join_requests' => $circleJoinCount,
                'membership_upgrades' => $membershipUpgradeCount,
                'proofs' => $proofsCount,
                'certifications' => $certificationCount,
                'coin_claims' => $coinClaimCount,
                'impacts' => $impactCount,
            ],
        ];
    }

    /**
     * Approve a pending request by ID across supported models.
     */
    public function approve(string $id, array $data, User|AdminUser $actor, ?Request $request = null): array
    {
        $typeHint = $data['type'] ?? null;

        // 1. CircleJoinRequest
        if ((! $typeHint || in_array($typeHint, ['circle_join_request', 'circle_join', 'circle'], true)) && Schema::hasTable('circle_join_requests')) {
            $record = CircleJoinRequest::query()->find($id);
            if ($record) {
                return $this->approveCircleJoinRequest($record, $data, $actor, $request);
            }
        }

        // 2. Payment (Membership Upgrade)
        if ((! $typeHint || in_array($typeHint, ['membership_upgrade', 'membership', 'payment'], true)) && Schema::hasTable('payments')) {
            $record = Payment::query()->find($id);
            if ($record && ($record->membership_plan_id !== null || (isset($record->payment_type) && $record->payment_type === 'membership'))) {
                return $this->approvePaymentMembership($record, $data, $actor, $request);
            }
        }

        // 3. User (Direct user pending registration / upgrade)
        if ((! $typeHint || in_array($typeHint, ['membership_upgrade', 'user', 'registration'], true)) && Schema::hasTable('users')) {
            $user = User::query()->find($id);
            if ($user && in_array($user->status, ['inactive', 'pending'], true)) {
                return $this->approveUserMembership($user, $data, $actor, $request);
            }
        }

        // 4. CertificationSubmission (Proof)
        if ((! $typeHint || in_array($typeHint, ['proof', 'certification'], true)) && Schema::hasTable('certification_submissions')) {
            $submission = CertificationSubmission::query()->find($id);
            if ($submission) {
                return $this->approveCertification($submission, $data, $actor, $request);
            }
        }

        // 5. CoinClaimRequest (Proof)
        if ((! $typeHint || in_array($typeHint, ['proof', 'coin_claim'], true)) && Schema::hasTable('coin_claim_requests')) {
            $claim = CoinClaimRequest::query()->find($id);
            if ($claim) {
                return $this->approveCoinClaim($claim, $data, $actor, $request);
            }
        }

        // 6. Impact (Proof)
        if ((! $typeHint || in_array($typeHint, ['proof', 'impact'], true)) && Schema::hasTable('impacts')) {
            $impact = Impact::query()->find($id);
            if ($impact) {
                return $this->approveImpact($impact, $data, $actor, $request);
            }
        }

        throw new NotFoundHttpException("Pending moderation request with ID '{$id}' not found.");
    }

    /**
     * Reject a pending request by ID across supported models.
     */
    public function reject(string $id, array $data, User|AdminUser $actor, ?Request $request = null): array
    {
        $reason = trim((string) ($data['reason'] ?? $data['admin_note'] ?? $data['notes'] ?? 'Rejected by administrator'));
        if ($reason === '') {
            $reason = 'Rejected by administrator';
        }

        $typeHint = $data['type'] ?? null;

        // 1. CircleJoinRequest
        if ((! $typeHint || in_array($typeHint, ['circle_join_request', 'circle_join', 'circle'], true)) && Schema::hasTable('circle_join_requests')) {
            $record = CircleJoinRequest::query()->find($id);
            if ($record) {
                return $this->rejectCircleJoinRequest($record, $reason, $actor, $request);
            }
        }

        // 2. Payment (Membership Upgrade)
        if ((! $typeHint || in_array($typeHint, ['membership_upgrade', 'membership', 'payment'], true)) && Schema::hasTable('payments')) {
            $record = Payment::query()->find($id);
            if ($record && ($record->membership_plan_id !== null || (isset($record->payment_type) && $record->payment_type === 'membership'))) {
                return $this->rejectPaymentMembership($record, $reason, $actor, $request);
            }
        }

        // 3. User
        if ((! $typeHint || in_array($typeHint, ['membership_upgrade', 'user', 'registration'], true)) && Schema::hasTable('users')) {
            $user = User::query()->find($id);
            if ($user && in_array($user->status, ['inactive', 'pending'], true)) {
                return $this->rejectUserMembership($user, $reason, $actor, $request);
            }
        }

        // 4. CertificationSubmission (Proof)
        if ((! $typeHint || in_array($typeHint, ['proof', 'certification'], true)) && Schema::hasTable('certification_submissions')) {
            $submission = CertificationSubmission::query()->find($id);
            if ($submission) {
                return $this->rejectCertification($submission, $reason, $actor, $request);
            }
        }

        // 5. CoinClaimRequest (Proof)
        if ((! $typeHint || in_array($typeHint, ['proof', 'coin_claim'], true)) && Schema::hasTable('coin_claim_requests')) {
            $claim = CoinClaimRequest::query()->find($id);
            if ($claim) {
                return $this->rejectCoinClaim($claim, $reason, $actor, $request);
            }
        }

        // 6. Impact (Proof)
        if ((! $typeHint || in_array($typeHint, ['proof', 'impact'], true)) && Schema::hasTable('impacts')) {
            $impact = Impact::query()->find($id);
            if ($impact) {
                return $this->rejectImpact($impact, $reason, $actor, $request);
            }
        }

        throw new NotFoundHttpException("Pending moderation request with ID '{$id}' not found.");
    }

    // -------------------------------------------------------------------------
    // Query Builders with Eager Loading (No N+1)
    // -------------------------------------------------------------------------

    private function queryCircleJoinRequests(string $status, string $search, ?string $circleId, ?string $dateFrom, ?string $dateTo, array $circleIds, bool $isGlobal): Builder
    {
        $query = CircleJoinRequest::query()
            ->with([
                'user:id,first_name,last_name,display_name,email,phone,company_name,profile_photo_url',
                'circle:id,name',
            ]);

        if (! $isGlobal) {
            $query->whereIn('circle_id', $circleIds);
        }

        if ($circleId) {
            $query->where('circle_id', $circleId);
        }

        if ($status === 'pending') {
            $query->whereIn('status', [
                CircleJoinRequest::STATUS_PENDING_CD_APPROVAL,
                CircleJoinRequest::STATUS_PENDING_ID_APPROVAL,
                CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE,
                'pending',
            ]);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(function (Builder $q) use ($like) {
                $q->whereHas('user', function (Builder $uq) use ($like) {
                    $uq->where('display_name', 'ILIKE', $like)
                        ->orWhere('first_name', 'ILIKE', $like)
                        ->orWhere('last_name', 'ILIKE', $like)
                        ->orWhere('email', 'ILIKE', $like)
                        ->orWhere('phone', 'ILIKE', $like)
                        ->orWhere('company_name', 'ILIKE', $like);
                })->orWhereHas('circle', fn (Builder $cq) => $cq->where('name', 'ILIKE', $like));
            });
        }

        $this->applyDateFilters($query, 'created_at', $dateFrom, $dateTo);

        return $query->orderByDesc('created_at');
    }

    private function queryMembershipUpgrades(string $status, string $search, ?string $circleId, ?string $dateFrom, ?string $dateTo, array $circleIds, bool $isGlobal): Builder
    {
        $query = Payment::query()
            ->with([
                'user:id,first_name,last_name,display_name,email,phone,company_name,profile_photo_url',
                'plan:id,name,price',
                'circle:id,name',
            ]);

        if (Schema::hasColumn('payments', 'payment_type')) {
            $query->where(function (Builder $q) {
                $q->whereNotNull('membership_plan_id')
                    ->orWhere('payment_type', 'membership');
            });
        } else {
            $query->whereNotNull('membership_plan_id');
        }

        if (! $isGlobal && $circleIds !== []) {
            $query->where(function (Builder $q) use ($circleIds) {
                $q->whereIn('circle_id', $circleIds)
                    ->orWhereNull('circle_id');
            });
        }

        if ($circleId) {
            $query->where('circle_id', $circleId);
        }

        if ($status === 'pending') {
            $query->whereIn('status', ['pending', 'created']);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->whereHas('user', function (Builder $uq) use ($like) {
                $uq->where('display_name', 'ILIKE', $like)
                    ->orWhere('first_name', 'ILIKE', $like)
                    ->orWhere('last_name', 'ILIKE', $like)
                    ->orWhere('email', 'ILIKE', $like)
                    ->orWhere('phone', 'ILIKE', $like)
                    ->orWhere('company_name', 'ILIKE', $like);
            });
        }

        $this->applyDateFilters($query, 'created_at', $dateFrom, $dateTo);

        return $query->orderByDesc('created_at');
    }

    private function queryCertifications(string $status, string $search, ?string $dateFrom, ?string $dateTo): Builder
    {
        $query = CertificationSubmission::query();

        if ($status === 'pending') {
            $query->whereIn('status', [CertificationSubmission::STATUS_NEW, 'pending']);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('full_name', 'ILIKE', $like)
                    ->orWhere('business_name', 'ILIKE', $like)
                    ->orWhere('email', 'ILIKE', $like)
                    ->orWhere('contact_no', 'ILIKE', $like);
            });
        }

        $this->applyDateFilters($query, 'created_at', $dateFrom, $dateTo);

        return $query->orderByDesc('created_at');
    }

    private function queryCoinClaims(string $status, string $search, ?string $dateFrom, ?string $dateTo, array $circleIds, bool $isGlobal): Builder
    {
        $query = CoinClaimRequest::query()
            ->with([
                'user:id,first_name,last_name,display_name,email,phone,company_name,profile_photo_url',
            ]);

        if (! $isGlobal && $circleIds !== []) {
            $query->whereHas('user.circleMemberships', function (Builder $cm) use ($circleIds) {
                $cm->whereIn('circle_id', $circleIds)->where('status', 'approved');
            });
        }

        if ($status === 'pending') {
            $query->where('status', 'pending');
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('activity_code', 'ILIKE', $like)
                    ->orWhere('admin_notes', 'ILIKE', $like)
                    ->orWhereHas('user', function (Builder $uq) use ($like) {
                        $uq->where('display_name', 'ILIKE', $like)
                            ->orWhere('first_name', 'ILIKE', $like)
                            ->orWhere('last_name', 'ILIKE', $like)
                            ->orWhere('email', 'ILIKE', $like);
                    });
            });
        }

        $this->applyDateFilters($query, 'created_at', $dateFrom, $dateTo);

        return $query->orderByDesc('created_at');
    }

    private function queryImpacts(string $status, string $search, ?string $dateFrom, ?string $dateTo): Builder
    {
        $query = Impact::query()
            ->with([
                'user:id,first_name,last_name,display_name,email,phone,company_name,profile_photo_url',
                'impactedPeer:id,first_name,last_name,display_name,email',
            ]);

        if ($status === 'pending') {
            $query->where('status', 'pending');
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('action', 'ILIKE', $like)
                    ->orWhere('story_to_share', 'ILIKE', $like)
                    ->orWhereHas('user', function (Builder $uq) use ($like) {
                        $uq->where('display_name', 'ILIKE', $like)
                            ->orWhere('first_name', 'ILIKE', $like)
                            ->orWhere('last_name', 'ILIKE', $like)
                            ->orWhere('email', 'ILIKE', $like);
                    });
            });
        }

        $this->applyDateFilters($query, 'created_at', $dateFrom, $dateTo);

        return $query->orderByDesc('created_at');
    }

    private function applyDateFilters(Builder $query, string $column, ?string $dateFrom, ?string $dateTo): void
    {
        if ($dateFrom) {
            $query->whereDate($column, '>=', Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo) {
            $query->whereDate($column, '<=', Carbon::parse($dateTo)->endOfDay());
        }
    }

    /**
     * Unified aggregation across multiple builder sources with sorting and pagination.
     */
    private function aggregateSources(array $sources, int $page, int $perPage): array
    {
        $allItems = collect();
        $totalCount = 0;

        foreach ($sources as $sourceKey => $queryFactory) {
            /** @var Builder $query */
            $query = $queryFactory();
            $count = $query->count();
            $totalCount += $count;

            if ($count > 0) {
                // Fetch limited recent records for page composition
                $records = $query->limit($page * $perPage)->get();
                $mapped = match ($sourceKey) {
                    'circle_join_requests' => $records->map(fn ($r) => $this->mapCircleJoinRequest($r)),
                    'membership_upgrades' => $records->map(fn ($r) => $this->mapPaymentMembership($r)),
                    'certifications' => $records->map(fn ($r) => $this->mapCertification($r)),
                    'coin_claims' => $records->map(fn ($r) => $this->mapCoinClaim($r)),
                    'impacts' => $records->map(fn ($r) => $this->mapImpact($r)),
                    default => collect(),
                };
                $allItems = $allItems->concat($mapped);
            }
        }

        // Sort descending by created_at
        $sorted = $allItems->sortByDesc(fn ($item) => $item['created_at'] ?? '')->values();

        // Slice for current page
        $offset = ($page - 1) * $perPage;
        $pageItems = $sorted->slice($offset, $perPage)->values()->all();

        $lastPage = max(1, (int) ceil($totalCount / $perPage));

        return [
            'items' => $pageItems,
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $totalCount,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Approve Implementations
    // -------------------------------------------------------------------------

    private function approveCircleJoinRequest(CircleJoinRequest $record, array $data, User|AdminUser $actor, ?Request $request): array
    {
        $oldValues = $record->only(['status', 'circle_id', 'fee_paid_at']);
        $circleId = $data['circle_id'] ?? $record->circle_id;

        $updated = $this->circleJoinService->approveAndJoinCircle($record, $actor, $circleId);

        $this->auditService->log(
            $actor,
            'pending_request.circle_join.approve',
            'circle_join_requests',
            $updated->id,
            $oldValues,
            $updated->only(['status', 'circle_id', 'fee_paid_at']),
            $request
        );

        return $this->mapCircleJoinRequest($updated);
    }

    private function approvePaymentMembership(Payment $payment, array $data, User|AdminUser $actor, ?Request $request): array
    {
        $oldValues = $payment->only(['status', 'paid_at']);

        $updatedPayment = DB::transaction(function () use ($payment, $data, $actor): Payment {
            $payment->status = Payment::STATUS_SUCCESS;
            $payment->paid_at = now();
            $payment->save();

            if ($payment->user) {
                $startDate = filled($data['membership_starts_at'] ?? null)
                    ? Carbon::parse($data['membership_starts_at'])->startOfDay()
                    : now()->startOfDay();

                $endDate = filled($data['membership_ends_at'] ?? null)
                    ? Carbon::parse($data['membership_ends_at'])->endOfDay()
                    : $startDate->copy()->addYear()->endOfDay();

                $this->membershipUpgradeService->markAsOnlyGreenPeerAfterPayment($payment->user, [
                    'membership_starts_at' => $startDate,
                    'membership_ends_at' => $endDate,
                    'starts_at' => $startDate,
                    'ends_at' => $endDate,
                    'payment_id' => $payment->id,
                    'plan_id' => $payment->membership_plan_id,
                    'membership_approved_by' => $actor->id,
                    'membership_approved_at' => now(),
                    'force_dates' => true,
                ]);
            }

            return $payment->fresh(['user', 'plan', 'circle']);
        });

        $this->auditService->log(
            $actor,
            'pending_request.membership_upgrade.approve',
            'payments',
            $updatedPayment->id,
            $oldValues,
            $updatedPayment->only(['status', 'paid_at']),
            $request
        );

        return $this->mapPaymentMembership($updatedPayment);
    }

    private function approveUserMembership(User $user, array $data, User|AdminUser $actor, ?Request $request): array
    {
        $oldValues = $user->only(['status', 'approval_status', 'membership_status']);

        DB::transaction(function () use ($user, $data, $actor): void {
            $startDate = filled($data['membership_starts_at'] ?? null)
                ? Carbon::parse($data['membership_starts_at'])->startOfDay()
                : now()->startOfDay();

            $endDate = filled($data['membership_ends_at'] ?? null)
                ? Carbon::parse($data['membership_ends_at'])->endOfDay()
                : $startDate->copy()->addYear()->endOfDay();

            $user->status = 'active';
            $user->approval_status = 'approved';
            $user->membership_status = User::STATUS_GREEN_PEER;
            if (Schema::hasColumn('users', 'membership_starts_at')) {
                $user->membership_starts_at = $startDate;
            }
            if (Schema::hasColumn('users', 'membership_ends_at')) {
                $user->membership_ends_at = $endDate;
            }
            $user->save();

            $this->membershipUpgradeService->markAsOnlyGreenPeerAfterPayment($user, [
                'membership_starts_at' => $startDate,
                'membership_ends_at' => $endDate,
                'starts_at' => $startDate,
                'ends_at' => $endDate,
                'membership_approved_by' => $actor->id,
                'membership_approved_at' => now(),
                'force_dates' => true,
            ]);
        });

        $freshUser = $user->fresh();

        $this->auditService->log(
            $actor,
            'pending_request.user_membership.approve',
            'users',
            $freshUser->id,
            $oldValues,
            $freshUser->only(['status', 'approval_status', 'membership_status']),
            $request
        );

        return [
            'id' => (string) $freshUser->id,
            'type' => 'membership_upgrade',
            'type_label' => 'Membership Upgrade',
            'subtype' => 'user_registration',
            'status' => 'approved',
            'status_label' => 'Approved',
            'is_pending' => false,
            'title' => 'Peer Membership: '.($freshUser->display_name ?: $freshUser->first_name),
            'description' => 'User activated and upgraded to Global Peer',
            'amount' => null,
            'currency' => 'INR',
            'user' => $this->mapUserSummary($freshUser),
            'circle' => null,
            'details' => [
                'membership_status' => $freshUser->membership_status,
                'membership_ends_at' => $freshUser->membership_ends_at?->toISOString(),
            ],
            'requested_at' => $freshUser->created_at?->toISOString(),
            'created_at' => $freshUser->created_at?->toISOString(),
            'updated_at' => $freshUser->updated_at?->toISOString(),
        ];
    }

    private function approveCertification(CertificationSubmission $submission, array $data, User|AdminUser $actor, ?Request $request): array
    {
        $oldValues = $submission->only(['status', 'approved_at', 'admin_note']);
        $adminNote = $data['admin_note'] ?? $data['notes'] ?? $submission->admin_note;

        $approved = $this->certificateGeneratorService->approveSubmission($submission, $adminNote, (string) $actor->id);

        $this->auditService->log(
            $actor,
            'pending_request.certification.approve',
            'certification_submissions',
            $approved->id,
            $oldValues,
            $approved->only(['status', 'approved_at', 'admin_note']),
            $request
        );

        return $this->mapCertification($approved);
    }

    private function approveCoinClaim(CoinClaimRequest $claim, array $data, User|AdminUser $actor, ?Request $request): array
    {
        $oldValues = $claim->only(['status', 'approved_at', 'coins_awarded']);

        $coinsToAward = isset($data['coins_awarded'])
            ? (int) $data['coins_awarded']
            : (int) ($claim->coins_awarded ?: 10);

        DB::transaction(function () use ($claim, $coinsToAward, $data, $actor): void {
            $claim->status = 'approved';
            $claim->approved_at = now();
            $claim->coins_awarded = $coinsToAward;
            $claim->admin_notes = $data['admin_note'] ?? $data['notes'] ?? $claim->admin_notes;
            $claim->save();

            if ($claim->user && $coinsToAward > 0) {
                $this->coinsService->reward(
                    $claim->user,
                    $coinsToAward,
                    'Approved coin claim: '.$claim->activity_code.' #'.$claim->id,
                    [
                        'source' => 'coin_claim_approved',
                        'claim_id' => (string) $claim->id,
                        'activity_code' => (string) $claim->activity_code,
                        'approved_by_admin_id' => (string) $actor->id,
                    ]
                );
            }
        });

        $freshClaim = $claim->fresh(['user']);

        $this->auditService->log(
            $actor,
            'pending_request.coin_claim.approve',
            'coin_claim_requests',
            $freshClaim->id,
            $oldValues,
            $freshClaim->only(['status', 'approved_at', 'coins_awarded']),
            $request
        );

        return $this->mapCoinClaim($freshClaim);
    }

    private function approveImpact(Impact $impact, array $data, User|AdminUser $actor, ?Request $request): array
    {
        $oldValues = $impact->only(['status', 'approved_at', 'approved_by']);
        $remarks = $data['admin_note'] ?? ($data['notes'] ?? ($data['review_remarks'] ?? null));

        $freshImpact = app(\App\Services\Impacts\ImpactService::class)->approveImpact($impact, $actor, $remarks);

        $this->auditService->log(
            $actor,
            'pending_request.impact.approve',
            'impacts',
            $freshImpact->id,
            $oldValues,
            $freshImpact->only(['status', 'approved_at', 'approved_by']),
            $request
        );

        return $this->mapImpact($freshImpact);
    }

    // -------------------------------------------------------------------------
    // Reject Implementations
    // -------------------------------------------------------------------------

    private function rejectCircleJoinRequest(CircleJoinRequest $record, string $reason, User|AdminUser $actor, ?Request $request): array
    {
        $oldValues = $record->only(['status', 'cd_rejected_at', 'id_rejected_at']);

        $updated = $this->circleJoinService->rejectGeneric($record, $actor, $reason);

        $this->auditService->log(
            $actor,
            'pending_request.circle_join.reject',
            'circle_join_requests',
            $updated->id,
            $oldValues,
            $updated->only(['status', 'cd_rejected_at', 'id_rejected_at']),
            $request
        );

        return $this->mapCircleJoinRequest($updated);
    }

    private function rejectPaymentMembership(Payment $payment, string $reason, User|AdminUser $actor, ?Request $request): array
    {
        $oldValues = $payment->only(['status']);

        $payment->status = Payment::STATUS_FAILED;
        $payment->save();

        $this->auditService->log(
            $actor,
            'pending_request.membership_upgrade.reject',
            'payments',
            $payment->id,
            $oldValues,
            ['status' => Payment::STATUS_FAILED, 'reason' => $reason],
            $request
        );

        return $this->mapPaymentMembership($payment->fresh(['user', 'plan', 'circle']));
    }

    private function rejectUserMembership(User $user, string $reason, User|AdminUser $actor, ?Request $request): array
    {
        $oldValues = $user->only(['status', 'approval_status']);

        $user->status = 'rejected';
        $user->approval_status = 'rejected';
        $user->save();

        $this->auditService->log(
            $actor,
            'pending_request.user_membership.reject',
            'users',
            $user->id,
            $oldValues,
            ['status' => 'rejected', 'reason' => $reason],
            $request
        );

        return [
            'id' => (string) $user->id,
            'type' => 'membership_upgrade',
            'type_label' => 'Membership Upgrade',
            'subtype' => 'user_registration',
            'status' => 'rejected',
            'status_label' => 'Rejected',
            'is_pending' => false,
            'title' => 'Peer Membership: '.($user->display_name ?: $user->first_name),
            'description' => 'User registration rejected: '.$reason,
            'amount' => null,
            'currency' => 'INR',
            'user' => $this->mapUserSummary($user),
            'circle' => null,
            'details' => ['rejection_reason' => $reason],
            'requested_at' => $user->created_at?->toISOString(),
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }

    private function rejectCertification(CertificationSubmission $submission, string $reason, User|AdminUser $actor, ?Request $request): array
    {
        $oldValues = $submission->only(['status', 'rejected_at', 'admin_note']);

        $submission->forceFill([
            'status' => CertificationSubmission::STATUS_REJECTED,
            'admin_note' => $reason,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => (string) $actor->id,
            'rejected_at' => now(),
        ])->save();

        if ($submission->certification_type === CertificationSubmission::TYPE_LEADERSHIP && class_exists(LeadershipCertificationSubmission::class) && Schema::hasTable('leadership_certification_submissions')) {
            LeadershipCertificationSubmission::query()->where('id', $submission->id)->update(['status' => 'rejected']);
        } elseif ($submission->certification_type === CertificationSubmission::TYPE_ENTREPRENEUR && class_exists(EntrepreneurCertificationSubmission::class) && Schema::hasTable('entrepreneur_certification_submissions')) {
            EntrepreneurCertificationSubmission::query()->where('id', $submission->id)->update(['status' => 'rejected']);
        }

        $freshSubmission = $submission->fresh();

        $this->auditService->log(
            $actor,
            'pending_request.certification.reject',
            'certification_submissions',
            $freshSubmission->id,
            $oldValues,
            $freshSubmission->only(['status', 'rejected_at', 'admin_note']),
            $request
        );

        return $this->mapCertification($freshSubmission);
    }

    private function rejectCoinClaim(CoinClaimRequest $claim, string $reason, User|AdminUser $actor, ?Request $request): array
    {
        $oldValues = $claim->only(['status', 'rejected_at']);

        $claim->status = 'rejected';
        $claim->rejected_at = now();
        $claim->admin_notes = $reason;
        $claim->save();

        $freshClaim = $claim->fresh(['user']);

        $this->auditService->log(
            $actor,
            'pending_request.coin_claim.reject',
            'coin_claim_requests',
            $freshClaim->id,
            $oldValues,
            $freshClaim->only(['status', 'rejected_at', 'admin_notes']),
            $request
        );

        return $this->mapCoinClaim($freshClaim);
    }

    private function rejectImpact(Impact $impact, string $reason, User|AdminUser $actor, ?Request $request): array
    {
        $oldValues = $impact->only(['status', 'rejected_at', 'rejected_by']);

        $freshImpact = app(\App\Services\Impacts\ImpactService::class)->rejectImpact($impact, $actor, $reason);

        $this->auditService->log(
            $actor,
            'pending_request.impact.reject',
            'impacts',
            $freshImpact->id,
            $oldValues,
            $freshImpact->only(['status', 'rejected_at', 'rejected_by']),
            $request
        );

        return $this->mapImpact($freshImpact);
    }

    private function resolveUserActorId(User|AdminUser $actor): ?string
    {
        if ($actor instanceof User) {
            return (string) $actor->id;
        }

        return User::query()->whereKey($actor->id)->exists() ? (string) $actor->id : null;
    }

    // -------------------------------------------------------------------------
    // Mappers for Standardized TypeScript-Compatible Envelope
    // -------------------------------------------------------------------------

    private function mapCircleJoinRequest(CircleJoinRequest $item): array
    {
        $isPending = in_array((string) $item->status, [
            CircleJoinRequest::STATUS_PENDING_CD_APPROVAL,
            CircleJoinRequest::STATUS_PENDING_ID_APPROVAL,
            CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE,
            'pending',
        ], true);

        return [
            'id' => (string) $item->id,
            'type' => 'circle_join_request',
            'type_label' => 'Circle Join Request',
            'subtype' => 'circle_join',
            'status' => (string) $item->status,
            'status_label' => $this->formatStatusLabel((string) $item->status),
            'is_pending' => $isPending,
            'title' => 'Circle Join: '.($item->circle?->name ?? 'Circle'),
            'description' => (string) ($item->reason_for_joining ?? $item->reason ?? 'Requested membership in circle'),
            'amount' => null,
            'currency' => 'INR',
            'user' => $this->mapUserSummary($item->user),
            'circle' => $item->circle ? [
                'id' => (string) $item->circle->id,
                'name' => (string) $item->circle->name,
            ] : null,
            'details' => [
                'fee_paid_at' => $item->fee_paid_at?->toISOString(),
                'cd_approved_at' => $item->cd_approved_at?->toISOString(),
                'id_approved_at' => $item->id_approved_at?->toISOString(),
                'cd_rejection_reason' => $item->cd_rejection_reason,
                'id_rejection_reason' => $item->id_rejection_reason,
            ],
            'requested_at' => $item->requested_at?->toISOString() ?? $item->created_at?->toISOString(),
            'created_at' => $item->created_at?->toISOString(),
            'updated_at' => $item->updated_at?->toISOString(),
        ];
    }

    private function mapPaymentMembership(Payment $payment): array
    {
        $isPending = in_array((string) $payment->status, [Payment::STATUS_PENDING, Payment::STATUS_CREATED], true);

        return [
            'id' => (string) $payment->id,
            'type' => 'membership_upgrade',
            'type_label' => 'Membership Upgrade',
            'subtype' => 'membership_plan',
            'status' => (string) $payment->status,
            'status_label' => $this->formatStatusLabel((string) $payment->status),
            'is_pending' => $isPending,
            'title' => 'Membership Upgrade'.($payment->plan ? ': '.$payment->plan->name : ''),
            'description' => 'Payment for platform tier membership',
            'amount' => (float) ($payment->total_amount ?? $payment->amount ?? 0),
            'currency' => (string) ($payment->currency ?? 'INR'),
            'user' => $this->mapUserSummary($payment->user),
            'circle' => $payment->circle ? [
                'id' => (string) $payment->circle->id,
                'name' => (string) $payment->circle->name,
            ] : null,
            'details' => [
                'plan_id' => $payment->membership_plan_id,
                'plan_name' => $payment->plan?->name,
                'provider' => $payment->provider,
                'razorpay_order_id' => $payment->razorpay_order_id,
            ],
            'requested_at' => $payment->created_at?->toISOString(),
            'created_at' => $payment->created_at?->toISOString(),
            'updated_at' => $payment->updated_at?->toISOString(),
        ];
    }

    private function mapCertification(CertificationSubmission $item): array
    {
        $isPending = in_array((string) $item->status, [CertificationSubmission::STATUS_NEW, 'pending'], true);

        return [
            'id' => (string) $item->id,
            'type' => 'proof',
            'type_label' => 'Proof of Certification',
            'subtype' => 'certification',
            'status' => (string) $item->status,
            'status_label' => $this->formatStatusLabel((string) $item->status),
            'is_pending' => $isPending,
            'title' => 'Certification: '.ucfirst((string) $item->certification_type).' ('.($item->certification_level ?? 'Standard').')',
            'description' => 'Business: '.($item->business_name ?? 'N/A').' | Score: '.$item->total_score.' ('.$item->percentage.'%)',
            'amount' => null,
            'currency' => 'INR',
            'user' => [
                'id' => (string) $item->user_id,
                'name' => (string) $item->full_name,
                'first_name' => (string) $item->full_name,
                'last_name' => null,
                'display_name' => (string) $item->full_name,
                'email' => (string) $item->email,
                'phone' => (string) ($item->contact_no ?? ''),
                'company_name' => $item->business_name,
                'profile_photo_url' => null,
            ],
            'circle' => null,
            'details' => [
                'certification_type' => $item->certification_type,
                'certification_level' => $item->certification_level,
                'total_score' => $item->total_score,
                'percentage' => $item->percentage,
                'certificate_download_url' => $item->certificate_download_url,
                'admin_note' => $item->admin_note,
            ],
            'requested_at' => $item->created_at?->toISOString(),
            'created_at' => $item->created_at?->toISOString(),
            'updated_at' => $item->updated_at?->toISOString(),
        ];
    }

    private function mapCoinClaim(CoinClaimRequest $item): array
    {
        $isPending = (string) $item->status === 'pending';

        return [
            'id' => (string) $item->id,
            'type' => 'proof',
            'type_label' => 'Coin Reward Claim',
            'subtype' => 'coin_claim',
            'status' => (string) $item->status,
            'status_label' => $this->formatStatusLabel((string) $item->status),
            'is_pending' => $isPending,
            'title' => 'Coin Claim: '.ucwords(str_replace('_', ' ', (string) $item->activity_code)),
            'description' => (string) ($item->admin_notes ?? 'Reward claim for completed peer activity'),
            'amount' => (float) ($item->coins_awarded ?? 0),
            'currency' => 'COINS',
            'user' => $this->mapUserSummary($item->user),
            'circle' => null,
            'details' => [
                'activity_code' => $item->activity_code,
                'coins_awarded' => $item->coins_awarded,
                'payload' => $item->payload,
                'admin_notes' => $item->admin_notes,
            ],
            'requested_at' => $item->created_at?->toISOString(),
            'created_at' => $item->created_at?->toISOString(),
            'updated_at' => $item->updated_at?->toISOString(),
        ];
    }

    private function mapImpact(Impact $item): array
    {
        $isPending = (string) $item->status === 'pending';

        return [
            'id' => (string) $item->id,
            'type' => 'proof',
            'type_label' => 'Life Impact Verification',
            'subtype' => 'impact',
            'status' => (string) $item->status,
            'status_label' => $this->formatStatusLabel((string) $item->status),
            'is_pending' => $isPending,
            'title' => 'Life Impact: '.ucwords(str_replace('_', ' ', (string) $item->action)),
            'description' => (string) ($item->story_to_share ?? $item->additional_remarks ?? 'Peer reported positive life impact'),
            'amount' => (float) ($item->life_impacted ?? 1),
            'currency' => 'IMPACTS',
            'user' => $this->mapUserSummary($item->user),
            'circle' => null,
            'details' => [
                'action' => $item->action,
                'impact_date' => $item->impact_date?->toDateString(),
                'life_impacted' => $item->life_impacted,
                'impacted_peer' => $item->impactedPeer ? [
                    'id' => (string) $item->impactedPeer->id,
                    'name' => $item->impactedPeer->display_name ?: trim(($item->impactedPeer->first_name ?? '').' '.($item->impactedPeer->last_name ?? '')),
                ] : null,
                'review_remarks' => $item->review_remarks,
            ],
            'requested_at' => $item->created_at?->toISOString(),
            'created_at' => $item->created_at?->toISOString(),
            'updated_at' => $item->updated_at?->toISOString(),
        ];
    }

    private function mapUserSummary(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $name = $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''));

        return [
            'id' => (string) $user->id,
            'name' => $name !== '' ? $name : 'Peer Member',
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'display_name' => $user->display_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'company_name' => $user->company_name,
            'profile_photo_url' => $user->profile_photo_url,
        ];
    }

    private function formatStatusLabel(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }

    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
