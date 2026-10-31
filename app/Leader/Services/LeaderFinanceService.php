<?php

declare(strict_types=1);

namespace App\Leader\Services;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LeaderFinanceService
{
    public function __construct(
        private readonly LeaderTeamsService $teamsService,
    ) {}

    /**
     * Get aggregate finance KPIs, trend graphs, and commission breakdowns.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(
        ?string $circleId = null,
        ?string $districtId = null,
        ?User $user = null,
    ): array {
        $resolvedDistrictId = $this->teamsService->resolveDedDistrictId($districtId, $user);
        $peersService = app(LeaderPeersService::class);
        $scopedCircleIds = $peersService->resolveScopedCircleIds($user, $districtId);

        // Sum actual payments in scope
        $query = Payment::query()->whereIn('status', ['Paid', 'paid', 'completed']);

        if ($circleId && Str::isUuid($circleId)) {
            $query->whereHas('user.circleMembers', fn ($cm) => $cm->where('circle_id', $circleId));
        } elseif ($scopedCircleIds !== null) {
            if (empty($scopedCircleIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('user.circleMembers', fn ($cm) => $cm->whereIn('circle_id', $scopedCircleIds));
            }
        } elseif ($resolvedDistrictId) {
            $query->whereHas('user.circleMembers.circle', fn (Builder $cq) => $cq->where('district_id', $resolvedDistrictId));
        }

        $collectionsAmount = (float) (clone $query)->sum('amount');
        $totalRevenueAmount = $collectionsAmount;
        $circleRevenueAmount = $collectionsAmount;

        $dateColumn = Schema::hasColumn('payments', 'paid_at') ? 'paid_at' : 'created_at';

        // Revenue trend: real collections for the last 6 months inside the role scope
        $revenueTrend = [];
        $dealsTrend = [];
        $scopeMemberIds = app(LeaderDashboardService::class)
            ->resolveLeaderScope($circleId, $districtId, $user)['scoped_member_user_ids'];

        $dealsBase = fn () => DB::table('business_deals')
            ->when(Schema::hasColumn('business_deals', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->when($scopeMemberIds !== null, function ($q) use ($scopeMemberIds) {
                if (empty($scopeMemberIds)) {
                    $q->whereRaw('1 = 0');
                } else {
                    $q->where(function ($sq) use ($scopeMemberIds): void {
                        $sq->whereIn('from_user_id', $scopeMemberIds)->orWhereIn('to_user_id', $scopeMemberIds);
                    });
                }
            });

        for ($i = 5; $i >= 0; $i--) {
            $monthStart = now()->startOfMonth()->subMonths($i);
            $monthEnd = (clone $monthStart)->endOfMonth();
            $revenueTrend[] = [
                'month' => $monthStart->format('M'),
                'value' => (float) (clone $query)->whereBetween($dateColumn, [$monthStart, $monthEnd])->sum('amount'),
            ];
            $dealsTrend[] = [
                'month' => $monthStart->format('M'),
                'value' => (float) $dealsBase()->whereBetween('deal_date', [$monthStart->toDateString(), $monthEnd->toDateString()])->count(),
            ];
        }

        $dealsClosedCount = Schema::hasTable('business_deals') ? (int) $dealsBase()->count() : 0;

        // Projection: year-to-date collections annualised by months elapsed
        $ytdCollections = (float) (clone $query)->where($dateColumn, '>=', now()->startOfYear())->sum('amount');
        $projectedAnnualRevenue = $ytdCollections > 0 ? ($ytdCollections / max(1, (int) now()->format('n'))) * 12 : 0.0;

        $coinIssuances = 0;
        if ($scopeMemberIds === null) {
            $coinIssuances = (int) User::query()->whereNull('deleted_at')->sum('coins_balance');
        } elseif (! empty($scopeMemberIds)) {
            $coinIssuances = (int) User::query()->whereIn('id', $scopeMemberIds)->sum('coins_balance');
        }
        // Fetch dynamic commission rates from leader_commission_rates table
        $dbRates = DB::table('leader_commission_rates')->get()->keyBy('role_id');

        $getRate = function (string $roleId, float $defaultReferral, float $defaultAppJoin) use ($dbRates): array {
            $row = $dbRates->get($roleId);
            $ref = $row ? (float) $row->direct_referral_cut_percentage : $defaultReferral;
            $app = $row ? (float) $row->app_join_cut_percentage : $defaultAppJoin;

            return [
                'referral' => number_format($ref, 1).'%',
                'app_join' => number_format($app, 1).'%',
            ];
        };

        $commissionStructure = [
            [
                'role' => 'Circle Founder',
                'direct_referral_cut' => $getRate('circleFounder', 7.5, 3.0)['referral'],
                'app_join_cut' => $getRate('circleFounder', 7.5, 3.0)['app_join'],
            ],
            [
                'role' => 'Circle Chair',
                'direct_referral_cut' => $getRate('circleChair', 5.0, 2.0)['referral'],
                'app_join_cut' => $getRate('circleChair', 5.0, 2.0)['app_join'],
            ],
            [
                'role' => 'Country Director',
                'direct_referral_cut' => $getRate('countryDirector', 10.0, 5.0)['referral'],
                'app_join_cut' => $getRate('countryDirector', 10.0, 5.0)['app_join'],
            ],
            [
                'role' => 'Super Admin',
                'direct_referral_cut' => $getRate('superAdmin', 12.0, 6.0)['referral'],
                'app_join_cut' => $getRate('superAdmin', 12.0, 6.0)['app_join'],
            ],
        ];

        // Resolve logged-in leader's personal commission rate
        $userRole = 'circleFounder';
        if ($user) {
            $permissionService = app(LeaderPermissionService::class);
            $roleInfo = $permissionService->resolveUserRole($user);
            $userRole = $roleInfo['role'] ?? 'circleFounder';
        }

        $userRate = match ($userRole) {
            'superAdmin' => $getRate('superAdmin', 12.0, 6.0),
            'countryDirector' => $getRate('countryDirector', 10.0, 5.0),
            'districtExecDirector' => $getRate('districtExecDirector', 10.0, 4.0),
            'industryDirector' => $getRate('industryDirector', 10.0, 4.0),
            'circleChair', 'chairBusinessGrowth', 'chairMembership', 'chairEventsPrograms' => $getRate('circleChair', 5.0, 2.0),
            'circleDirector' => $getRate('circleDirector', 7.5, 3.0),
            default => $getRate('circleFounder', 7.5, 3.0),
        };

        $commissionRates = [
            [
                'label' => 'Direct Referral Cut',
                'rate' => $userRate['referral'],
                'description' => 'Earned on every closed peer referral',
                'status' => 'Active',
            ],
            [
                'label' => 'App Join Cut',
                'rate' => $userRate['app_join'],
                'description' => 'Credited on new member onboarding',
                'status' => 'Active',
            ],
        ];

        // Commission due = collections in scope x the signed-in leader's direct referral cut
        $referralRate = (float) rtrim($userRate['referral'], '%');
        $commissionDueAmount = round($collectionsAmount * $referralRate / 100, 2);
        $totalDuesAmount = $commissionDueAmount;
        return [
            'total_revenue' => '₹'.number_format($totalRevenueAmount, 0),
            'projected_annual_revenue' => '₹'.number_format($projectedAnnualRevenue, 0),
            'circle_revenue' => '₹'.number_format($circleRevenueAmount, 0),
            'total_collections' => '₹'.number_format($collectionsAmount, 0),
            'deals_closed' => $dealsClosedCount,
            'commission_due' => '₹'.number_format($commissionDueAmount, 0),
            'total_dues' => '₹'.number_format($totalDuesAmount, 0),
            'coin_issuances_total' => $coinIssuances,
            'revenue_trend' => $revenueTrend,
            'business_deals' => $dealsTrend,
            'commission_rates' => $commissionRates,
            'commission_structure' => $commissionStructure,
        ];
    }

    /**
     * Get list of financial transactions.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTransactions(
        ?string $circleId = null,
        ?string $status = null,
        ?string $districtId = null,
        ?User $user = null,
    ): array {
        $resolvedDistrictId = $this->teamsService->resolveDedDistrictId($districtId, $user);
        $peersService = app(LeaderPeersService::class);
        $scopedCircleIds = $peersService->resolveScopedCircleIds($user, $districtId);

        $query = Payment::query()->with(['user.circleMembers.circle']);

        if ($circleId && Str::isUuid($circleId)) {
            $query->whereHas('user.circleMembers', fn ($cm) => $cm->where('circle_id', $circleId));
        } elseif ($scopedCircleIds !== null) {
            if (empty($scopedCircleIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('user.circleMembers', fn ($cm) => $cm->whereIn('circle_id', $scopedCircleIds));
            }
        } elseif ($resolvedDistrictId) {
            $query->whereHas('user.circleMembers.circle', fn (Builder $cq) => $cq->where('district_id', $resolvedDistrictId));
        }

        if ($status && strtolower($status) !== 'all') {
            $query->whereRaw('LOWER(status) = ?', [strtolower($status)]);
        }

        $payments = $query->take(10)->get();

        if ($payments->isEmpty()) {
            return [];
        }

        return $payments->map(function (Payment $p, int $idx): array {
            $u = $p->user;
            $name = $u ? trim(($u->first_name ?? '').' '.($u->last_name ?? '')) : '';
            if ($name === '' || $name === ' ') {
                $name = $u?->display_name ?? 'Peer Member';
            }

            $circleName = '';
            if ($u && $u->circleMembers->isNotEmpty()) {
                $c = $u->circleMembers->first()?->circle;
                if ($c && ! empty($c->name)) {
                    $circleName = $c->name;
                }
            }

            return [
                'id' => (string) ($p->id ?: 'txn_892'.$idx),
                'peer_name' => $name,
                'circle_name' => $circleName,
                'amount' => '₹'.number_format((float) ($p->amount ?? 0)),
                'type' => 'Annual Membership Fee',
                'status' => (string) ucfirst((string) ($p->status ?: 'Paid')),
                'date' => $p->created_at ? $p->created_at->format('Y-m-d') : '2026-08-15',
            ];
        })->values()->all();
    }

    /**
     * Update commission rates per role.
     *
     * @param  array<int, array<string, mixed>>  $rates
     * @return array<string, mixed>
     */
    public function updateCommissionRates(array $rates, ?User $user = null): array
    {
        $updatedRates = [];

        foreach ($rates as $rate) {
            $roleId = (string) ($rate['role_id'] ?? '');
            if ($roleId === '') {
                continue;
            }

            $referralCut = (float) ($rate['direct_referral_cut_percentage'] ?? 0.0);
            $appJoinCut = (float) ($rate['app_join_cut_percentage'] ?? 0.0);

            $updateData = [
                'direct_referral_cut_percentage' => $referralCut,
                'app_join_cut_percentage' => $appJoinCut,
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('leader_commission_rates', 'renewal_cut_percentage') && isset($rate['renewal_cut_percentage'])) {
                $updateData['renewal_cut_percentage'] = (float) $rate['renewal_cut_percentage'];
            }

            $existing = DB::table('leader_commission_rates')->where('role_id', $roleId)->first();
            if ($existing) {
                DB::table('leader_commission_rates')->where('role_id', $roleId)->update($updateData);
            } else {
                $updateData['id'] = (string) Str::uuid();
                $updateData['role_id'] = $roleId;
                $updateData['created_at'] = now();
                DB::table('leader_commission_rates')->insert($updateData);
            }

            $updatedRates[] = [
                'role_id' => $roleId,
                'direct_referral_cut' => number_format($referralCut, 1).'%',
                'app_join_cut' => number_format($appJoinCut, 1).'%',
            ];
        }

        $adminCode = $user ? ($user->peer_id ?: 'USR-SUPERADMIN-'.substr((string) $user->id, 0, 4)) : 'USR-SUPERADMIN-001';

        return [
            'updated_count' => count($updatedRates),
            'updated_at' => now()->toIso8601String(),
            'updated_by' => $adminCode,
            'rates' => $updatedRates,
        ];
    }

    /**
     * Record a manual / offline payment (Cheque, Cash, Bank Transfer).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function recordOfflinePayment(User $recorder, array $data): array
    {
        $id = (string) Str::uuid();
        $targetPeerId = (string) $data['peer_id'];
        $user = User::query()->where('id', $targetPeerId)->first();
        $userId = $user ? (string) $user->id : $recorder->id;

        $amount = (float) $data['amount'];
        $mode = (string) ($data['payment_mode'] ?? 'Cheque');
        $ref = (string) ($data['reference_number'] ?? 'REF-'.random_int(10000, 99999));

        DB::table('payments')->insert([
            'id' => $id,
            'user_id' => $userId,
            'amount' => $amount,
            'base_amount' => $amount,
            'total_amount' => $amount,
            'currency' => 'INR',
            'status' => 'Paid',
            'paid_at' => $data['payment_date'] ?? now(),
            'provider' => 'offline_'.$mode,
            'razorpay_payment_id' => $ref,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'transaction_id' => $id,
            'status' => 'Paid',
        ];
    }
}
