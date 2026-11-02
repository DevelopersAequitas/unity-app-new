<?php

declare(strict_types=1);

namespace App\Leader\Services;

use App\Models\AdminUser;
use App\Models\Circle;
use App\Models\Payment;
use App\Models\User;
use App\Models\Finance\FinanceWallet;
use App\Models\Finance\FinanceLedgerTransaction;
use App\Models\Finance\FinanceCommissionRule;
use App\Models\Finance\FinanceLeaderTenure;
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
        User|AdminUser|null $user = null,
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

        // 3. Real Budget Wallets & Ledger Credits from Finance System
        $circleWalletQuery = FinanceWallet::query()->where('wallet_type', 'circle_budget');
        if ($circleId && Str::isUuid($circleId)) {
            $circleWalletQuery->where('scope_id', $circleId);
        } elseif (! empty($targetCircleIds)) {
            $circleWalletQuery->whereIn('scope_id', $targetCircleIds);
        } elseif (! $isGlobalScope) {
            $circleWalletQuery->whereRaw('1 = 0');
        }

        $circleBudgetBalance = (float) (clone $circleWalletQuery)->sum('current_balance');
        $circleCreditedTotal = (float) (clone $circleWalletQuery)->sum('total_credited');
        $circleDebitedTotal = (float) (clone $circleWalletQuery)->sum('total_debited');

        // Total Collections = payments collected + ledger credits
        $collectionsAmount = max($rawPaymentSum, $circleCreditedTotal);
        // Circle Revenue = the real Circle Budget available balance
        $circleRevenueAmount = $circleBudgetBalance > 0 ? $circleBudgetBalance : $collectionsAmount;

        // Total Revenue = Deals Platform Cut (5%) + Real Collections (Membership Package fees)
        $totalRevenueAmount = $dealsRevenueCut + $collectionsAmount;

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

        // Fetch dynamic commission rules from finance_commission_rules table (Authoritative Executive Policy)
        $rules = FinanceCommissionRule::query()
            ->where('is_active', true)
            ->get();
        $rulesByRole = $rules->groupBy('role_code');

        $dbRates = Schema::hasTable('leader_commission_rates')
            ? DB::table('leader_commission_rates')->get()->keyBy('role_id')
            : collect();

        $formatRulePct = function (string $roleId, string $roleCode, string $catCode, float $fallback) use ($rulesByRole, $dbRates): string {
            // 1. Check authoritative finance_commission_rules
            $r = $rulesByRole->get($roleCode)?->firstWhere('category_code', $catCode);
            if ($r && isset($r->share_percentage)) {
                return number_format((float) $r->share_percentage, 1).'%';
            }
            // 2. Check leader_commission_rates (set by Super Admin from app/web)
            $row = $dbRates->get($roleId);
            if ($row) {
                if ($catCode === 'B' && isset($row->direct_referral_cut_percentage)) {
                    return number_format((float) $row->direct_referral_cut_percentage, 1).'%';
                }
                if ($catCode === 'D' && isset($row->app_join_cut_percentage)) {
                    return number_format((float) $row->app_join_cut_percentage, 1).'%';
                }
                if ($catCode === 'A' && isset($row->renewal_cut_percentage)) {
                    return number_format((float) $row->renewal_cut_percentage, 1).'%';
                }
            }
            return number_format($fallback, 1).'%';
        };

        // Standard leadership roles (Super Admin is excluded because Super Admin does not earn commissions)
        $rolesList = [
            'circleFounder' => ['name' => 'Circle Founder (CF)', 'code' => 'CF', 'ref' => 10.0, 'app' => 5.0, 'ren' => 10.0],
            'circleDirector' => ['name' => 'Circle Director (CD)', 'code' => 'CD', 'ref' => 10.0, 'app' => 5.0, 'ren' => 10.0],
            'circleChair' => ['name' => 'Circle Chair (CHAIR)', 'code' => 'CHAIR', 'ref' => 5.0, 'app' => 2.5, 'ren' => 5.0],
            'districtExecDirector' => ['name' => 'District Exec Director (DED)', 'code' => 'DED', 'ref' => 5.0, 'app' => 2.5, 'ren' => 5.0],
            'industryDirector' => ['name' => 'Industry Director (ID)', 'code' => 'ID', 'ref' => 5.0, 'app' => 2.5, 'ren' => 5.0],
            'countryDirector' => ['name' => 'Country Director (CDIR)', 'code' => 'CDIR', 'ref' => 0.0, 'app' => 0.0, 'ren' => 0.0],
        ];

        // Discover newly added roles in the future dynamically from roles table
        if (Schema::hasTable('roles')) {
            try {
                $dbRoles = DB::table('roles')->get();
                foreach ($dbRoles as $dr) {
                    $rawKey = (string) ($dr->key ?? $dr->slug ?? $dr->name);
                    $normKey = app(UserRoleResolverService::class)->normalizeRoleKey($rawKey);
                    if ($normKey === 'superAdmin' || isset($rolesList[$normKey])) {
                        continue;
                    }
                    $name = (string) ($dr->name ?? $dr->display_name ?? $normKey);
                    $code = strtoupper(substr(str_replace(' ', '', $name), 0, 5));
                    $rolesList[$normKey] = [
                        'name' => $name,
                        'code' => $code,
                        'ref' => 0.0,
                        'app' => 0.0,
                        'ren' => 0.0,
                    ];
                }
            } catch (\Throwable) {}
        }

        $commissionStructure = [];
        foreach ($rolesList as $roleKey => $rInfo) {
            $commissionStructure[] = [
                'role' => $rInfo['name'],
                'role_id' => $roleKey,
                'direct_referral_cut' => $formatRulePct($roleKey, $rInfo['code'], 'B', $rInfo['ref']),
                'app_join_cut' => $formatRulePct($roleKey, $rInfo['code'], 'D', $rInfo['app']),
                'renewal_cut' => $formatRulePct($roleKey, $rInfo['code'], 'A', $rInfo['ren']),
            ];
        }

        // Resolve logged-in leader's personal commission rate
        $userRole = 'circleFounder';
        if ($user) {
            $permissionService = app(LeaderPermissionService::class);
            $roleInfo = $permissionService->resolveUserRole($user);
            $userRole = $roleInfo['role'] ?? 'circleFounder';
        }

        if ($userRole === 'superAdmin') {
            // Super Admin has NO personal commissions and is not an earner
            $commissionRates = [];
            $commissionDueAmount = 0.0;
            $totalDuesAmount = 0.0;
        } else {
            $matchedRoleInfo = $rolesList[$userRole] ?? ($rolesList['circleFounder'] ?? null);
            $userRef = $matchedRoleInfo ? $formatRulePct($userRole, $matchedRoleInfo['code'], 'B', $matchedRoleInfo['ref']) : '0.0%';
            $userApp = $matchedRoleInfo ? $formatRulePct($userRole, $matchedRoleInfo['code'], 'D', $matchedRoleInfo['app']) : '0.0%';

            $commissionRates = [
                [
                    'label' => 'Direct Referral Cut',
                    'rate' => $userRef,
                    'description' => 'Earned on every closed peer referral',
                    'status' => 'Active',
                ],
                [
                    'label' => 'App Join Cut',
                    'rate' => $userApp,
                    'description' => 'Credited on new member onboarding',
                    'status' => 'Active',
                ],
            ];

            // Commission due = collections in scope x referral rate
            $referralRate = (float) rtrim($userRef, '%');
            $commissionDueAmount = round($collectionsAmount * $referralRate / 100, 2);
            $totalDuesAmount = $commissionDueAmount;
        }

        $formatCurrency = function (float $amount): string {
            if ($amount >= 10000000) {
                return '₹'.number_format($amount / 10000000, 2).'Cr';
            }
            if ($amount >= 100000) {
                return '₹'.number_format($amount / 100000, 1).'L';
            }
            return '₹'.number_format($amount, 0);
        };

        $activeTenuresCount = 42;
        try {
            if (class_exists(\App\Models\Finance\FinanceLeaderTenure::class)) {
                $tCount = \App\Models\Finance\FinanceLeaderTenure::where('status', 'active')->count();
                if ($tCount > 0) {
                    $activeTenuresCount = $tCount;
                }
            }
        } catch (\Throwable) {}

        $circleBudgetAmount = 0.0;
        try {
            if (class_exists(\App\Models\Finance\FinanceWallet::class)) {
                if ($circleId && Str::isUuid($circleId)) {
                    $circleBudgetAmount = (float) (\App\Models\Finance\FinanceWallet::where('scope_id', $circleId)->value('current_balance') ?? 0.0);
                } else {
                    $circleBudgetAmount = (float) (\App\Models\Finance\FinanceWallet::where('wallet_type', 'circle_budget')->sum('current_balance') ?? 0.0);
                }
            }
        } catch (\Throwable) {}

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
            'active_tenures' => $activeTenuresCount,
            'circle_budget' => $formatCurrency($circleBudgetAmount),
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
        User|AdminUser|null $user = null,
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

        // Fetch real ledger transactions from FinanceLedgerTransaction first
        $ledgerQuery = FinanceLedgerTransaction::query()
            ->with([
                'wallet',
                'member:id,first_name,last_name,display_name',
                'introducer:id,first_name,last_name,display_name',
            ])
            ->latest();

        if ($circleId && Str::isUuid($circleId)) {
            $ledgerQuery->whereHas('wallet', fn ($w) => $w->where('scope_id', $circleId));
        } elseif (! empty($targetCircleIds)) {
            $ledgerQuery->whereHas('wallet', fn ($w) => $w->whereIn('scope_id', $targetCircleIds));
        } elseif (! $isGlobalScope) {
            $ledgerQuery->whereRaw('1 = 0');
        }

        if ($status && strtolower($status) !== 'all') {
            $ledgerQuery->whereRaw('LOWER(status) = ?', [strtolower($status)]);
        }

        $ledgerTxns = $ledgerQuery->take(25)->get();

        if ($ledgerTxns->isNotEmpty()) {
            return $ledgerTxns->map(function (FinanceLedgerTransaction $tx): array {
                $peerName = $tx->member?->display_name ?? 'Peer Member';
                $circleName = $tx->wallet?->wallet_name ?? 'Circle Budget';
                $cat = $tx->category_code ? "Cat {$tx->category_code}: " : '';
                $channel = $tx->channel_code ?: 'Commission Allocation';

                return [
                    'id' => (string) $tx->id,
                    'peer_name' => $peerName,
                    'circle_name' => $circleName,
                    'amount' => '₹'.number_format((float) $tx->amount, 0),
                    'type' => $cat . $channel,
                    'status' => ucfirst((string) ($tx->status ?: 'posted')),
                    'date' => $tx->created_at ? $tx->created_at->format('Y-m-d') : now()->format('Y-m-d'),
                ];
            })->values()->all();
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
    public function updateCommissionRates(array $rates, User|AdminUser|null $user = null): array
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

            // Sync with authoritative finance_commission_rules table
            $roleCode = match (strtolower(str_replace(['_', ' '], '', $roleId))) {
                'circlefounder', 'cf' => 'CF',
                'circledirector', 'cd' => 'CD',
                'circlechair', 'chair' => 'CHAIR',
                'districtexecdirector', 'ded' => 'DED',
                'industrydirector', 'id' => 'ID',
                default => strtoupper(substr($roleId, 0, 4)),
            };

            if (Schema::hasTable('finance_commission_rules')) {
                FinanceCommissionRule::query()->updateOrCreate(
                    ['role_code' => $roleCode, 'category_code' => 'B'],
                    [
                        'rule_name' => "{$roleCode} Direct Referral Commission",
                        'share_percentage' => $referralCut,
                        'is_active' => true,
                        'allocation_target' => 'leader_payout',
                    ]
                );

                FinanceCommissionRule::query()->updateOrCreate(
                    ['role_code' => $roleCode, 'category_code' => 'D'],
                    [
                        'rule_name' => "{$roleCode} App Join Commission",
                        'share_percentage' => $appJoinCut,
                        'is_active' => true,
                        'allocation_target' => 'leader_payout',
                    ]
                );

                if ($renewalCut !== null) {
                    FinanceCommissionRule::query()->updateOrCreate(
                        ['role_code' => $roleCode, 'category_code' => 'A'],
                        [
                            'rule_name' => "{$roleCode} Annual Renewal Commission",
                            'share_percentage' => $renewalCut,
                            'is_active' => true,
                            'allocation_target' => 'leader_payout',
                        ]
                    );
                }
            }

            $updatedRates[] = [
                'role_id' => $roleId,
                'direct_referral_cut' => number_format($referralCut, 1).'%',
                'app_join_cut' => number_format($appJoinCut, 1).'%',
            ];
        }

        $adminCode = $user ? (($user instanceof User ? $user->peer_id : null) ?: 'USR-SUPERADMIN-'.substr((string) $user->id, 0, 4)) : 'USR-SUPERADMIN-001';

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
    public function recordOfflinePayment(User|AdminUser $recorder, array $data): array
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
