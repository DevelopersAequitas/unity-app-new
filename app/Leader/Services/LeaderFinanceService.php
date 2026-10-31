<?php

declare(strict_types=1);

namespace App\Leader\Services;

use App\Models\Circle;
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
        $leaderScope = app(LeaderDashboardService::class)->resolveLeaderScope($circleId, $districtId, $user);
        $isGlobalScope = $leaderScope['is_global_scope'];
        $resolvedCircleId = $leaderScope['resolved_circle_id'];
        $targetCircleIds = $leaderScope['target_circle_ids'];
        $scopeMemberIds = $leaderScope['scoped_member_user_ids'];

        // 1. Business Deals in Scope & 5% Platform Revenue Cut
        $dealsBase = fn () => DB::table('business_deals')
            ->when(Schema::hasColumn('business_deals', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->when(Schema::hasColumn('business_deals', 'is_deleted'), fn ($q) => $q->where('is_deleted', false))
            ->when($scopeMemberIds !== null, function ($q) use ($scopeMemberIds) {
                if (empty($scopeMemberIds)) {
                    $q->whereRaw('1 = 0');
                } else {
                    $q->where(function ($sq) use ($scopeMemberIds): void {
                        $sq->whereIn('from_user_id', $scopeMemberIds)->orWhereIn('to_user_id', $scopeMemberIds);
                    });
                }
            });

        $dealsClosedCount = Schema::hasTable('business_deals') ? (int) $dealsBase()->count() : 0;
        $dealsAmountSum = Schema::hasTable('business_deals') ? (float) $dealsBase()->sum('deal_amount') : 0.0;
        $dealsRevenueCut = $dealsAmountSum * 0.05;

        // 2. Collections from Payments table
        $paymentsQuery = Payment::query()->where(function ($q): void {
            $q->whereIn(DB::raw('LOWER(status)'), ['paid', 'success', 'completed', 'active', 'captured']);
        });

        if ($circleId && Str::isUuid($circleId)) {
            $paymentsQuery->where(function ($q) use ($circleId): void {
                $q->where('circle_id', $circleId)
                    ->orWhereHas('user.circleMembers', fn ($cm) => $cm->where('circle_id', $circleId));
            });
        } elseif (! empty($targetCircleIds)) {
            $paymentsQuery->where(function ($q) use ($targetCircleIds): void {
                $q->whereIn('circle_id', $targetCircleIds)
                    ->orWhereHas('user.circleMembers', fn ($cm) => $cm->whereIn('circle_id', $targetCircleIds));
            });
        } elseif (! $isGlobalScope) {
            $paymentsQuery->whereRaw('1 = 0');
        }

        $rawPaymentSum = (float) (clone $paymentsQuery)->sum(DB::raw('COALESCE(NULLIF(total_amount, 0), NULLIF(amount, 0), base_amount, 0)'));

        // 3. Circle Collections from Chartered Circles & Active Members
        $circlesScopeQuery = Circle::query()->whereNull('deleted_at');
        if ($circleId && Str::isUuid($circleId)) {
            $circlesScopeQuery->where('id', $circleId);
        } elseif (! empty($targetCircleIds)) {
            $circlesScopeQuery->whereIn('id', $targetCircleIds);
        } elseif (! $isGlobalScope) {
            $circlesScopeQuery->whereRaw('1 = 0');
        }

        $circlesInScope = $circlesScopeQuery->with(['members'])->get();
        $circleCollectionsAmount = 0.0;
        foreach ($circlesInScope as $c) {
            $pCount = $c->members ? $c->members->where('status', 'approved')->count() : 0;
            $unitPrice = (float) ($c->circle_price_amount ?? 120000);
            if ($unitPrice <= 0) {
                $unitPrice = 120000;
            }
            $circleCollectionsAmount += ($unitPrice * $pCount);
        }

        $collectionsAmount = $rawPaymentSum > 0 ? $rawPaymentSum : $circleCollectionsAmount;
        $circleRevenueAmount = $collectionsAmount;

        // Total revenue = deals cut + circle collections
        $totalRevenueAmount = $dealsRevenueCut > 0
            ? ($dealsRevenueCut + $circleRevenueAmount)
            : ($collectionsAmount > 0 ? $collectionsAmount : 0.0);

        // 4. Monthly Trend (last 6 months)
        $dateColumn = Schema::hasColumn('payments', 'paid_at') ? 'paid_at' : 'created_at';
        $revenueTrend = [];
        $dealsTrend = [];

        for ($i = 5; $i >= 0; $i--) {
            $monthStart = now()->startOfMonth()->subMonths($i);
            $monthEnd = (clone $monthStart)->endOfMonth();

            $mDealsSum = (float) $dealsBase()->whereBetween('deal_date', [$monthStart->toDateString(), $monthEnd->toDateString()])->sum('deal_amount');
            $mDealsCount = (int) $dealsBase()->whereBetween('deal_date', [$monthStart->toDateString(), $monthEnd->toDateString()])->count();
            $mDealsCut = $mDealsSum * 0.05;

            $mPayments = (float) (clone $paymentsQuery)->whereBetween($dateColumn, [$monthStart, $monthEnd])->sum('amount');
            $mTotal = $mDealsCut + $mPayments;

            $revenueTrend[] = [
                'month' => $monthStart->format('M'),
                'value' => round($mTotal, 2),
            ];
            $dealsTrend[] = [
                'month' => $monthStart->format('M'),
                'value' => (float) $mDealsCount,
            ];
        }

        // Projected Annual Revenue
        $elapsedMonths = max(1, (int) now()->format('n'));
        $projectedAnnualRevenue = $totalRevenueAmount > 0 ? ($totalRevenueAmount / $elapsedMonths) * 12 : 0.0;

        // Coin issuances
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
                'direct_referral_cut' => $getRate('circleFounder', 8.5, 3.5)['referral'],
                'app_join_cut' => $getRate('circleFounder', 8.5, 3.5)['app_join'],
            ],
            [
                'role' => 'Circle Chair',
                'direct_referral_cut' => $getRate('circleChair', 0.0, 0.0)['referral'],
                'app_join_cut' => $getRate('circleChair', 0.0, 0.0)['app_join'],
            ],
            [
                'role' => 'Country Director',
                'direct_referral_cut' => $getRate('countryDirector', 11.0, 5.5)['referral'],
                'app_join_cut' => $getRate('countryDirector', 11.0, 5.5)['app_join'],
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
            'countryDirector' => $getRate('countryDirector', 11.0, 5.5),
            'districtExecDirector' => $getRate('districtExecDirector', 10.0, 4.0),
            'industryDirector' => $getRate('industryDirector', 10.0, 4.0),
            'circleChair', 'chairBusinessGrowth', 'chairMembership', 'chairEventsPrograms' => $getRate('circleChair', 0.0, 0.0),
            'circleDirector' => $getRate('circleDirector', 7.5, 3.0),
            default => $getRate('circleFounder', 8.5, 3.5),
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

        // Commission due = collections in scope x referral rate
        $referralRate = (float) rtrim($userRate['referral'], '%');
        $commissionDueAmount = round($collectionsAmount * $referralRate / 100, 2);
        $totalDuesAmount = $commissionDueAmount;

        $formatCurrency = function (float $amount): string {
            if ($amount >= 10000000) {
                return '₹'.number_format($amount / 10000000, 2).'Cr';
            }
            if ($amount >= 100000) {
                return '₹'.number_format($amount / 100000, 1).'L';
            }
            return '₹'.number_format($amount, 0);
        };

        return [
            'total_revenue' => $formatCurrency($totalRevenueAmount),
            'projected_annual_revenue' => $formatCurrency($projectedAnnualRevenue),
            'circle_revenue' => $formatCurrency($circleRevenueAmount),
            'total_collections' => $formatCurrency($collectionsAmount),
            'deals_closed' => $dealsClosedCount,
            'commission_due' => $formatCurrency($commissionDueAmount),
            'total_dues' => $formatCurrency($totalDuesAmount),
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
        $leaderScope = app(LeaderDashboardService::class)->resolveLeaderScope($circleId, $districtId, $user);
        $isGlobalScope = $leaderScope['is_global_scope'];
        $targetCircleIds = $leaderScope['target_circle_ids'];

        $query = Payment::query()->with(['user.circleMembers.circle', 'circle'])->latest();

        if ($circleId && Str::isUuid($circleId)) {
            $query->where(function ($q) use ($circleId): void {
                $q->where('circle_id', $circleId)
                    ->orWhereHas('user.circleMembers', fn ($cm) => $cm->where('circle_id', $circleId));
            });
        } elseif (! empty($targetCircleIds)) {
            $query->where(function ($q) use ($targetCircleIds): void {
                $q->whereIn('circle_id', $targetCircleIds)
                    ->orWhereHas('user.circleMembers', fn ($cm) => $cm->whereIn('circle_id', $targetCircleIds));
            });
        } elseif (! $isGlobalScope) {
            $query->whereRaw('1 = 0');
        }

        if ($status && strtolower($status) !== 'all') {
            $query->whereRaw('LOWER(status) = ?', [strtolower($status)]);
        }

        $payments = $query->take(15)->get();

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
            if ($p->circle && ! empty($p->circle->name)) {
                $circleName = $p->circle->name;
            } elseif ($u && $u->circleMembers->isNotEmpty()) {
                $c = $u->circleMembers->first()?->circle;
                if ($c && ! empty($c->name)) {
                    $circleName = $c->name;
                }
            }

            $amountVal = (float) ($p->total_amount ?: ($p->amount ?: ($p->base_amount ?: 0)));
            if ($amountVal <= 0 && $p->circle) {
                $amountVal = (float) ($p->circle->circle_price_amount ?: 120000);
            }
            if ($amountVal <= 0) {
                $amountVal = 120000;
            }

            return [
                'id' => (string) ($p->id ?: 'txn_'.Str::random(8)),
                'peer_name' => $name,
                'circle_name' => $circleName ?: 'Chartered Circle',
                'amount' => '₹'.number_format($amountVal, 0),
                'type' => ucfirst(str_replace('_', ' ', (string) ($p->payment_type ?: 'Annual Membership Fee'))),
                'status' => (string) ucfirst((string) ($p->status ?: 'Paid')),
                'date' => $p->created_at ? $p->created_at->format('Y-m-d') : now()->format('Y-m-d'),
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
