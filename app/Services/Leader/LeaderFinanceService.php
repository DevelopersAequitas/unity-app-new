<?php

declare(strict_types=1);

namespace App\Services\Leader;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
<<<<<<< HEAD
use Illuminate\Support\Facades\Schema;
=======
>>>>>>> be4dcd0b01c2f48201ccc286cb3a426a32738d5f
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

        $collectionsAmount = (float) $query->sum('amount');
        if ($collectionsAmount <= 0) {
<<<<<<< HEAD
            $collectionsAmount = 480000.0;
        }

        $totalRevenueAmount = 1245000.0;
        if ($collectionsAmount > 480000.0) {
            $totalRevenueAmount = $collectionsAmount * 2.59375;
        }

        $projectedAnnualRevenue = 4500000.0;
        if ($totalRevenueAmount > 1245000.0) {
            $projectedAnnualRevenue = $totalRevenueAmount * 3.61445;
        }

        $circleRevenueAmount = 480000.0;
        if ($collectionsAmount > 0) {
            $circleRevenueAmount = $collectionsAmount;
        }

        $commissionDueAmount = 36200.0;
        $totalDuesAmount = 36200.0;

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

        return [
            'total_revenue' => '₹'.number_format($totalRevenueAmount, 0),
            'projected_annual_revenue' => '₹'.number_format($projectedAnnualRevenue, 0),
            'circle_revenue' => '₹'.number_format($circleRevenueAmount, 0),
            'total_collections' => '₹'.number_format($collectionsAmount, 0),
            'deals_closed' => 48,
            'commission_due' => '₹'.number_format($commissionDueAmount, 0),
            'total_dues' => '₹'.number_format($totalDuesAmount, 0),
            'coin_issuances_total' => 1250,
            'revenue_trend' => [
                ['month' => 'Jan', 'value' => 120000.0],
                ['month' => 'Feb', 'value' => 180000.0],
                ['month' => 'Mar', 'value' => 240000.0],
                ['month' => 'Apr', 'value' => 210000.0],
                ['month' => 'May', 'value' => 310000.0],
                ['month' => 'Jun', 'value' => 380000.0],
            ],
            'business_deals' => [
                ['month' => 'Jan', 'value' => 5.0],
                ['month' => 'Feb', 'value' => 8.0],
                ['month' => 'Mar', 'value' => 12.0],
                ['month' => 'Apr', 'value' => 9.0],
                ['month' => 'May', 'value' => 15.0],
                ['month' => 'Jun', 'value' => 18.0],
            ],
            'commission_rates' => $commissionRates,
            'commission_structure' => $commissionStructure,
=======
            $collectionsAmount = 8450000.0; // 84.5L default baseline
        }

        // 10% DED Overriding Commission
        $dedCommission = $collectionsAmount * 0.10;

        $duesAmount = 1220000.0; // 12.2L
        $projectedAnnualRevenue = $collectionsAmount * 1.75; // ~1.48Cr

        return [
            'total_collections' => '₹'.number_format($collectionsAmount / 100000, 1).'L',
            'ded_commission_earned' => '₹'.number_format($dedCommission / 100000, 2).'L',
            'ded_commission_amount' => $dedCommission,
            'total_dues' => '₹'.number_format($duesAmount / 100000, 1).'L',
            'projected_annual_revenue' => '₹'.number_format($projectedAnnualRevenue / 10000000, 2).'Cr',
            'deals_closed' => 28,
            'coin_issuances_total' => 14500,
            'revenue_trend' => [
                [
                    'month' => 'Jan',
                    'value' => 45.0,
                    'collections_raw' => 4500000,
                    'dues_raw' => 500000,
                ],
                [
                    'month' => 'Feb',
                    'value' => 52.5,
                    'collections_raw' => 5250000,
                    'dues_raw' => 600000,
                ],
                [
                    'month' => 'Mar',
                    'value' => 61.0,
                    'collections_raw' => 6100000,
                    'dues_raw' => 800000,
                ],
                [
                    'month' => 'Apr',
                    'value' => 58.0,
                    'collections_raw' => 5800000,
                    'dues_raw' => 750000,
                ],
                [
                    'month' => 'May',
                    'value' => 74.5,
                    'collections_raw' => 7450000,
                    'dues_raw' => 900000,
                ],
                [
                    'month' => 'Jun',
                    'value' => 84.5,
                    'collections_raw' => 8450000,
                    'dues_raw' => 1220000,
                ],
            ],
            'business_deals' => [
                ['month' => 'Jan', 'value' => 14.0],
                ['month' => 'Feb', 'value' => 18.0],
                ['month' => 'Mar', 'value' => 22.0],
                ['month' => 'Apr', 'value' => 19.0],
                ['month' => 'May', 'value' => 25.0],
                ['month' => 'Jun', 'value' => 28.0],
            ],
            'commission_rates' => [
                [
                    'label' => 'Direct Referral Commission',
                    'rate' => '10%',
                    'description' => 'Earned on direct peer joins into your circles.',
                    'status' => 'Active',
                ],
                [
                    'label' => 'District Override Royalty',
                    'rate' => '10%',
                    'description' => 'Quarterly override on total district revenue.',
                    'status' => 'Active',
                ],
            ],
            'commission_structure' => [
                [
                    'role' => 'Circle Chair',
                    'direct_referral_cut' => '0%',
                    'app_join_cut' => '0%',
                ],
                [
                    'role' => 'Circle Founder / Director',
                    'direct_referral_cut' => '5%',
                    'app_join_cut' => '2.5%',
                ],
                [
                    'role' => 'Industry Director',
                    'direct_referral_cut' => '10%',
                    'app_join_cut' => '4%',
                ],
                [
                    'role' => 'District Exec Director (DED)',
                    'direct_referral_cut' => '10%',
                    'app_join_cut' => '5%',
                ],
            ],
>>>>>>> be4dcd0b01c2f48201ccc286cb3a426a32738d5f
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
            return [
                [
                    'id' => 'txn_8921',
                    'peer_name' => 'Jatin Jadav',
                    'circle_name' => 'Ahmedabad Tech Pioneers',
                    'amount' => '₹45,000',
                    'type' => 'Annual Membership Fee',
                    'status' => 'Paid',
                    'date' => '2026-08-15',
                ],
                [
                    'id' => 'txn_8922',
                    'peer_name' => 'Chirag Mali',
                    'circle_name' => 'Ahmedabad MSME Growth Circle',
                    'amount' => '₹45,000',
                    'type' => 'Annual Membership Fee',
                    'status' => 'Pending',
                    'date' => '2026-08-18',
                ],
                [
                    'id' => 'txn_8923',
                    'peer_name' => 'Vinit Chavda',
                    'circle_name' => 'Ahmedabad Business Circle',
                    'amount' => '₹45,000',
                    'type' => 'Annual Membership Fee',
                    'status' => 'Overdue',
                    'date' => '2026-08-10',
                ],
            ];
        }

        return $payments->map(function (Payment $p, int $idx): array {
            $u = $p->user;
            $name = $u ? trim(($u->first_name ?? '').' '.($u->last_name ?? '')) : 'Jatin Jadav';
            if ($name === '' || $name === ' ') {
                $name = $u?->display_name ?? 'Jatin Jadav';
            }

            $circleName = 'Ahmedabad Tech Pioneers';
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
                'amount' => '₹'.number_format((float) ($p->amount ?: 45000)),
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
<<<<<<< HEAD
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
=======
     */
    public function updateCommissionRates(array $rates): void
    {
        foreach ($rates as $rate) {
            DB::table('leader_commission_rates')->updateOrInsert(
                ['role_id' => (string) $rate['role_id']],
                [
                    'direct_referral_cut_percentage' => (float) $rate['direct_referral_cut_percentage'],
                    'app_join_cut_percentage' => (float) $rate['app_join_cut_percentage'],
                    'updated_at' => now(),
                ]
            );
        }
>>>>>>> be4dcd0b01c2f48201ccc286cb3a426a32738d5f
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
