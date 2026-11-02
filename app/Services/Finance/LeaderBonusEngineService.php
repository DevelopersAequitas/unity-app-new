<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\Finance\FinanceBonusRoleConfig;
use App\Models\Finance\FinanceBonusRule;
use App\Models\Finance\FinanceLeaderBonusAward;
use App\Models\Finance\FinanceLeaderTenure;
use App\Models\Finance\FinanceLedgerTransaction;
use App\Models\Finance\FinancePolicyAuditLog;
use App\Models\Finance\FinanceWallet;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class LeaderBonusEngineService
{
    public function __construct(
        private readonly LeaderTenureService $tenureService,
    ) {}

    /**
     * List all dynamic bonus rules with their per-role configurations.
     */
    public function listRules(?string $roleCode = null, ?string $category = null): Collection
    {
        $query = FinanceBonusRule::query()
            ->with(['roleConfigs'])
            ->orderBy('sort_order')
            ->orderBy('created_at');

        if ($category !== null && trim($category) !== '') {
            $query->where('bonus_category', strtolower(trim($category)));
        }

        if ($roleCode !== null && trim($roleCode) !== '') {
            $normalizedRole = $this->tenureService->normalizeRoleCode($roleCode);
            $query->whereHas('roleConfigs', function ($q) use ($normalizedRole): void {
                $q->where('role_code', $normalizedRole);
            });
        }

        return $query->get();
    }

    /**
     * Get the complete active bonus catalog for a specific role (CD, CF, ID, DED).
     *
     * @return array<string, mixed>
     */
    public function getRoleBonusCatalog(string $roleCode): array
    {
        $normalizedRole = $this->tenureService->normalizeRoleCode($roleCode);

        $configs = FinanceBonusRoleConfig::query()
            ->with('rule')
            ->where('role_code', $normalizedRole)
            ->get()
            ->filter(fn (FinanceBonusRoleConfig $cfg): bool => $cfg->rule !== null)
            ->sortBy(fn (FinanceBonusRoleConfig $cfg): int => (int) ($cfg->rule?->sort_order ?? 999))
            ->values();

        $formatted = $configs->map(function (FinanceBonusRoleConfig $cfg): array {
            /** @var FinanceBonusRule $rule */
            $rule = $cfg->rule;
            $effectiveAmount = $cfg->override_bonus_amount_inr !== null
                ? (float) $cfg->override_bonus_amount_inr
                : (float) $rule->bonus_amount_inr;
            $effectiveTargetMembers = $cfg->override_target_members !== null
                ? (int) $cfg->override_target_members
                : $rule->target_members_count;
            $effectiveTimeframeDays = $cfg->override_timeframe_days !== null
                ? (int) $cfg->override_timeframe_days
                : $rule->timeframe_days;

            return [
                'role_config_id' => $cfg->id,
                'bonus_rule_id' => $rule->id,
                'rule_code' => $rule->rule_code,
                'role_code' => $cfg->role_code,
                'scope_type' => $cfg->scope_type,
                'is_active_for_role' => (bool) ($cfg->is_active && $rule->is_active),
                'activated_at' => $cfg->activated_at?->toIso8601String(),
                'deactivated_at' => $cfg->deactivated_at?->toIso8601String(),
                'bonus_category' => $rule->bonus_category,
                'metric_type' => $rule->metric_type,
                'title' => $rule->title,
                'description' => $rule->description,
                'target_members_count' => $effectiveTargetMembers,
                'target_circles_count' => $rule->target_circles_count,
                'min_avg_members_per_circle' => $rule->min_avg_members_per_circle,
                'target_percentage' => $rule->target_percentage !== null ? (float) $rule->target_percentage : null,
                'target_meetings_count' => $rule->target_meetings_count,
                'meeting_window_mode' => $rule->meeting_window_mode,
                'target_collaborations_count' => $rule->target_collaborations_count,
                'max_disputes_allowed' => $rule->max_disputes_allowed,
                'timeframe_days' => $effectiveTimeframeDays,
                'bonus_amount_inr' => $effectiveAmount,
                'reward_perks' => $rule->reward_perks,
                'requires_approval' => (bool) $rule->requires_approval,
                'payout_destination' => 'leader_wallet (Personal Bonus Account)',
            ];
        })->all();

        // If no role configs exist in DB yet, fallback to active standard rules for this role
        if (count($formatted) === 0) {
            $formatted = $this->getDefaultRoleBonusRules($normalizedRole);
        }

        return [
            'role_code' => $normalizedRole,
            'payout_wallet_type' => 'leader_wallet',
            'total_configured_rules' => count($formatted),
            'active_rules_count' => collect($formatted)->where('is_active_for_role', true)->count(),
            'bonus_rules' => $formatted,
        ];

    /**
     * Default standard bonus rules fallback when database table has not yet seeded configs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDefaultRoleBonusRules(string $roleCode): array
    {
        $normalized = strtoupper(trim($roleCode));

        if ($normalized === 'CD') {
            return [
                [
                    'role_config_id' => 'cd_def_1',
                    'bonus_rule_id' => 'rule_speed_10m_20d',
                    'rule_code' => 'SPEED_10M_20D',
                    'role_code' => 'CD',
                    'scope_type' => 'circle',
                    'is_active_for_role' => true,
                    'bonus_category' => 'speed_bonus',
                    'metric_type' => 'NEW_MEMBERS_IN_DAYS',
                    'title' => '10 new Peers in 20 days',
                    'description' => 'Leader brings or onboards 10 new paid Peers within 20 days of tenure start or activation.',
                    'target_members_count' => 10,
                    'timeframe_days' => 20,
                    'bonus_amount_inr' => 10000.00,
                    'reward_perks' => null,
                    'requires_approval' => false,
                    'payout_destination' => 'leader_wallet (Personal Bonus Account)',
                ],
                [
                    'role_config_id' => 'cd_def_2',
                    'bonus_rule_id' => 'rule_cd_speed_10m_10d',
                    'rule_code' => 'CD_SPEED_10M_10D',
                    'role_code' => 'CD',
                    'scope_type' => 'circle',
                    'is_active_for_role' => true,
                    'bonus_category' => 'speed_bonus',
                    'metric_type' => 'NEW_MEMBERS_IN_DAYS',
                    'title' => '10 new Peers in 10 days',
                    'description' => 'Fast-track speed bonus: 10 new paid Peers within 10 days.',
                    'target_members_count' => 10,
                    'timeframe_days' => 10,
                    'bonus_amount_inr' => 25000.00,
                    'reward_perks' => null,
                    'requires_approval' => false,
                    'payout_destination' => 'leader_wallet (Personal Bonus Account)',
                ],
                [
                    'role_config_id' => 'cd_def_3',
                    'bonus_rule_id' => 'rule_cd_scale_50m_90d',
                    'rule_code' => 'CD_SCALE_50M_90D',
                    'role_code' => 'CD',
                    'scope_type' => 'circle',
                    'is_active_for_role' => true,
                    'bonus_category' => 'growth_milestone',
                    'metric_type' => 'TOTAL_CIRCLE_MEMBERS',
                    'title' => '50 Active Circle Members',
                    'description' => 'Reach 50 active paid peers in circle within 90 days.',
                    'target_members_count' => 50,
                    'timeframe_days' => 90,
                    'bonus_amount_inr' => 25000.00,
                    'reward_perks' => null,
                    'requires_approval' => false,
                    'payout_destination' => 'leader_wallet (Personal Bonus Account)',
                ],
                [
                    'role_config_id' => 'cd_def_4',
                    'bonus_rule_id' => 'rule_cd_scale_100m_180d',
                    'rule_code' => 'CD_SCALE_100M_180D',
                    'role_code' => 'CD',
                    'scope_type' => 'circle',
                    'is_active_for_role' => true,
                    'bonus_category' => 'growth_milestone',
                    'metric_type' => 'TOTAL_CIRCLE_MEMBERS',
                    'title' => '100 Active Circle Members',
                    'description' => 'Reach 100 active paid peers in circle within 180-day tenure window.',
                    'target_members_count' => 100,
                    'timeframe_days' => 180,
                    'bonus_amount_inr' => 50000.00,
                    'reward_perks' => null,
                    'requires_approval' => false,
                    'payout_destination' => 'leader_wallet (Personal Bonus Account)',
                ],
            ];
        }

        if ($normalized === 'CF') {
            return [
                [
                    'role_config_id' => 'cf_def_1',
                    'bonus_rule_id' => 'rule_speed_10m_20d',
                    'rule_code' => 'SPEED_10M_20D',
                    'role_code' => 'CF',
                    'scope_type' => 'circle',
                    'is_active_for_role' => true,
                    'bonus_category' => 'speed_bonus',
                    'metric_type' => 'NEW_MEMBERS_IN_DAYS',
                    'title' => '10 new Peers in 20 days',
                    'description' => 'Bring 10 new paid Peers within 20 days of tenure activation.',
                    'target_members_count' => 10,
                    'timeframe_days' => 20,
                    'bonus_amount_inr' => 10000.00,
                    'reward_perks' => null,
                    'requires_approval' => false,
                    'payout_destination' => 'leader_wallet (Personal Bonus Account)',
                ],
                [
                    'role_config_id' => 'cf_def_2',
                    'bonus_rule_id' => 'rule_cf_speed_30m_40d',
                    'rule_code' => 'CF_SPEED_30M_40D',
                    'role_code' => 'CF',
                    'scope_type' => 'circle',
                    'is_active_for_role' => true,
                    'bonus_category' => 'speed_bonus',
                    'metric_type' => 'NEW_MEMBERS_IN_DAYS',
                    'title' => '30 members in 40 days',
                    'description' => 'Momentum bonus: 30 paid members within 40 days.',
                    'target_members_count' => 30,
                    'timeframe_days' => 40,
                    'bonus_amount_inr' => 25000.00,
                    'reward_perks' => null,
                    'requires_approval' => false,
                    'payout_destination' => 'leader_wallet (Personal Bonus Account)',
                ],
                [
                    'role_config_id' => 'cf_def_3',
                    'bonus_rule_id' => 'rule_cf_speed_50m_75d',
                    'rule_code' => 'CF_SPEED_50M_75D',
                    'role_code' => 'CF',
                    'scope_type' => 'circle',
                    'is_active_for_role' => true,
                    'bonus_category' => 'growth_milestone',
                    'metric_type' => 'TOTAL_CIRCLE_MEMBERS',
                    'title' => '50 members in 75 days',
                    'description' => 'Founder milestone: 50 paid members within 75 days.',
                    'target_members_count' => 50,
                    'timeframe_days' => 75,
                    'bonus_amount_inr' => 50000.00,
                    'reward_perks' => 'Founder Dinner / Retreat',
                    'requires_approval' => false,
                    'payout_destination' => 'leader_wallet (Personal Bonus Account)',
                ],
            ];
        }

        return [];
    }


    }

    /**
     * Create a new dynamic bonus rule and initialize role configurations.
     *
     * @param  array<string, mixed>  $data
     */
    public function createRule(array $data, ?string $adminId = null, ?string $ipAddress = null): FinanceBonusRule
    {
        return DB::transaction(function () use ($data, $adminId, $ipAddress): FinanceBonusRule {
            $activeRoles = collect($data['active_roles'] ?? ['CD'])
                ->map(fn ($r) => $this->tenureService->normalizeRoleCode((string) $r))
                ->unique()
                ->values()
                ->all();

            unset($data['active_roles']);
            $data['rule_code'] = strtoupper(trim((string) $data['rule_code']));
            $data['bonus_category'] = strtolower(trim((string) $data['bonus_category']));
            $data['is_system'] = false;
            $data['updated_by_admin_id'] = $adminId;

            $rule = FinanceBonusRule::query()->create($data);

            $allStandardRoles = array_unique(array_merge(['CD', 'CF', 'ID', 'DED'], $activeRoles));
            foreach ($allStandardRoles as $rCode) {
                $isActive = in_array($rCode, $activeRoles, true);
                $scopeType = match ($rCode) {
                    'ID' => 'industry',
                    'DED' => 'district',
                    default => 'circle',
                };

                FinanceBonusRoleConfig::query()->create([
                    'bonus_rule_id' => $rule->id,
                    'role_code' => $rCode,
                    'scope_type' => $scopeType,
                    'is_active' => $isActive,
                    'activated_at' => $isActive ? now() : null,
                    'updated_by_admin_id' => $adminId,
                ]);
            }

            FinancePolicyAuditLog::query()->create([
                'admin_user_id' => $adminId,
                'action' => 'finance.bonus_rule.created',
                'target_table' => 'finance_bonus_rules',
                'target_id' => $rule->id,
                'before_snapshot' => null,
                'after_snapshot' => $rule->load('roleConfigs')->toArray(),
                'ip_address' => $ipAddress,
            ]);

            return $rule->load('roleConfigs');
        });
    }

    /**
     * Update an existing dynamic bonus rule (amount, days, members, %, perks, etc.).
     *
     * @param  array<string, mixed>  $data
     */
    public function updateRule(string $ruleId, array $data, ?string $adminId = null, ?string $ipAddress = null): FinanceBonusRule
    {
        return DB::transaction(function () use ($ruleId, $data, $adminId, $ipAddress): FinanceBonusRule {
            $rule = FinanceBonusRule::query()->findOrFail($ruleId);
            $before = $rule->toArray();

            $data['updated_by_admin_id'] = $adminId;
            $rule->fill($data)->save();

            FinancePolicyAuditLog::query()->create([
                'admin_user_id' => $adminId,
                'action' => 'finance.bonus_rule.updated',
                'target_table' => 'finance_bonus_rules',
                'target_id' => $rule->id,
                'before_snapshot' => $before,
                'after_snapshot' => $rule->fresh()?->toArray(),
                'ip_address' => $ipAddress,
            ]);

            return $rule->load('roleConfigs');
        });
    }

    /**
     * Delete a bonus rule.
     */
    public function deleteRule(string $ruleId, ?string $adminId = null, ?string $ipAddress = null): void
    {
        DB::transaction(function () use ($ruleId, $adminId, $ipAddress): void {
            $rule = FinanceBonusRule::query()->findOrFail($ruleId);
            $before = $rule->toArray();

            $rule->delete();

            FinancePolicyAuditLog::query()->create([
                'admin_user_id' => $adminId,
                'action' => 'finance.bonus_rule.deleted',
                'target_table' => 'finance_bonus_rules',
                'target_id' => $ruleId,
                'before_snapshot' => $before,
                'after_snapshot' => null,
                'ip_address' => $ipAddress,
            ]);
        });
    }

    /**
     * Activate or deactivate a bonus rule for a specific Role (CD, CF, ID, DED)
     * and record the exact `activated_at` date from which counting begins.
     *
     * @param  array<string, mixed>  $data
     */
    public function toggleRoleConfig(array $data, ?string $adminId = null, ?string $ipAddress = null): FinanceBonusRoleConfig
    {
        return DB::transaction(function () use ($data, $adminId, $ipAddress): FinanceBonusRoleConfig {
            $ruleId = (string) $data['bonus_rule_id'];
            $roleCode = $this->tenureService->normalizeRoleCode((string) $data['role_code']);
            $isActive = (bool) $data['is_active'];

            $existing = FinanceBonusRoleConfig::query()
                ->where('bonus_rule_id', $ruleId)
                ->where('role_code', $roleCode)
                ->first();

            $before = $existing?->toArray();

            $scopeType = match ($roleCode) {
                'ID' => 'industry',
                'DED' => 'district',
                default => 'circle',
            };

            $activatedAt = $existing?->activated_at;
            $deactivatedAt = $existing?->deactivated_at;

            if ($isActive) {
                if (! empty($data['activated_at'])) {
                    $activatedAt = Carbon::parse((string) $data['activated_at']);
                } elseif (! $existing || ! $existing->is_active || $activatedAt === null) {
                    $activatedAt = now();
                }
                $deactivatedAt = null;
            } else {
                $deactivatedAt = now();
            }

            $payload = [
                'scope_type' => $scopeType,
                'is_active' => $isActive,
                'activated_at' => $activatedAt,
                'deactivated_at' => $deactivatedAt,
                'updated_by_admin_id' => $adminId,
            ];

            if (array_key_exists('override_bonus_amount_inr', $data)) {
                $payload['override_bonus_amount_inr'] = $data['override_bonus_amount_inr'];
            }
            if (array_key_exists('override_target_members', $data)) {
                $payload['override_target_members'] = $data['override_target_members'];
            }
            if (array_key_exists('override_timeframe_days', $data)) {
                $payload['override_timeframe_days'] = $data['override_timeframe_days'];
            }

            if ($existing) {
                $existing->fill($payload)->save();
                $config = $existing->fresh(['rule']) ?? $existing;
            } else {
                $config = FinanceBonusRoleConfig::query()->create(array_merge([
                    'bonus_rule_id' => $ruleId,
                    'role_code' => $roleCode,
                ], $payload))->load('rule');
            }

            FinancePolicyAuditLog::query()->create([
                'admin_user_id' => $adminId,
                'action' => 'finance.bonus_role_config.toggled',
                'target_table' => 'finance_bonus_role_configs',
                'target_id' => $config->id,
                'before_snapshot' => $before,
                'after_snapshot' => $config->toArray(),
                'ip_address' => $ipAddress,
            ]);

            return $config;
        });
    }

    /**
     * Evaluate all active bonus rules for a Leader Tenure and automatically credit achieved personal bonuses
     * to the leader's Personal Bonus Wallet (`wallet_type = 'leader_wallet'`).
     *
     * @param  array<string, mixed>  $manualMetrics
     * @return array<string, mixed>
     */
    public function evaluateTenureBonuses(
        string $leaderTenureId,
        array $manualMetrics = [],
        ?string $actorAdminId = null,
    ): array {
        /** @var FinanceLeaderTenure $tenure */
        $tenure = FinanceLeaderTenure::query()->with('user')->findOrFail($leaderTenureId);
        $roleCode = $this->tenureService->normalizeRoleCode($tenure->role_code);

        $activeRoleConfigs = FinanceBonusRoleConfig::query()
            ->with('rule')
            ->where('role_code', $roleCode)
            ->where('is_active', true)
            ->get()
            ->filter(fn (FinanceBonusRoleConfig $cfg): bool => $cfg->rule !== null && $cfg->rule->is_active)
            ->sortBy(fn (FinanceBonusRoleConfig $cfg): int => (int) ($cfg->rule?->sort_order ?? 999))
            ->values();

        $evaluatedAwards = [];
        $newlyCreditedAmount = 0.0;

        DB::transaction(function () use (
            $tenure,
            $roleCode,
            $activeRoleConfigs,
            $manualMetrics,
            $actorAdminId,
            &$evaluatedAwards,
            &$newlyCreditedAmount
        ): void {
            foreach ($activeRoleConfigs as $cfg) {
                /** @var FinanceBonusRule $rule */
                $rule = $cfg->rule;

                // Determine exact counting_started_at = GREATEST(tenure.tenure_start_at, cfg.activated_at)
                $tenureStart = $tenure->tenure_start_at ?: now();
                $roleActivatedAt = $cfg->activated_at ?: $tenureStart;
                $countingStartedAt = $roleActivatedAt->gt($tenureStart) ? $roleActivatedAt->copy() : $tenureStart->copy();

                $effectiveTimeframeDays = $cfg->override_timeframe_days !== null
                    ? (int) $cfg->override_timeframe_days
                    : ($rule->timeframe_days !== null ? (int) $rule->timeframe_days : null);

                $deadlineAt = $effectiveTimeframeDays !== null
                    ? $countingStartedAt->copy()->addDays($effectiveTimeframeDays)->endOfDay()
                    : null;

                $effectiveBonusAmount = $cfg->override_bonus_amount_inr !== null
                    ? (float) $cfg->override_bonus_amount_inr
                    : (float) $rule->bonus_amount_inr;

                $effectiveTargetMembers = $cfg->override_target_members !== null
                    ? (int) $cfg->override_target_members
                    : ($rule->target_members_count !== null ? (int) $rule->target_members_count : null);

                $evalResult = $this->calculateMetricForRule(
                    tenure: $tenure,
                    rule: $rule,
                    countingStartedAt: $countingStartedAt,
                    deadlineAt: $deadlineAt,
                    effectiveTargetMembers: $effectiveTargetMembers,
                    manualMetrics: $manualMetrics,
                );

                $award = FinanceLeaderBonusAward::query()->firstOrNew([
                    'bonus_rule_id' => $rule->id,
                    'leader_tenure_id' => $tenure->id,
                ]);

                // If already credited in this tenure, preserve credited state
                if ($award->exists && $award->status === 'credited') {
                    $evaluatedAwards[] = $this->formatAwardOutput($award, $rule);

                    continue;
                }

                $award->fill([
                    'role_config_id' => $cfg->id,
                    'leader_user_id' => $tenure->user_id,
                    'role_code' => $roleCode,
                    'scope_type' => $tenure->scope_type,
                    'scope_id' => $tenure->scope_id,
                    'counting_started_at' => $countingStartedAt,
                    'deadline_at' => $deadlineAt,
                    'current_metric_value' => $evalResult['current_value'],
                    'target_metric_value' => $evalResult['target_value'],
                    'progress_percentage' => $evalResult['progress_percentage'],
                    'bonus_amount_inr' => $effectiveBonusAmount,
                    'reward_perks' => $rule->reward_perks,
                    'proof_metadata' => $evalResult['proof_metadata'],
                ]);

                if ($evalResult['is_achieved']) {
                    $award->achieved_at = $award->achieved_at ?: now();

                    if ($rule->requires_approval) {
                        if ($award->status !== 'rejected') {
                            $award->status = 'pending_approval';
                        }
                        $award->save();
                    } else {
                        $award->save();
                        $creditedAward = $this->creditPersonalBonusToLeaderWallet($award, $rule, $tenure, $actorAdminId);
                        $newlyCreditedAmount += (float) $creditedAward->bonus_amount_inr;
                        $award = $creditedAward;
                    }
                } else {
                    if ($deadlineAt !== null && now()->gt($deadlineAt) && $award->status !== 'pending_approval') {
                        $award->status = 'expired';
                    } else {
                        $award->status = 'in_progress';
                    }
                    $award->save();
                }

                $evaluatedAwards[] = $this->formatAwardOutput($award, $rule);
            }
        });

        $personalWallet = FinanceWallet::query()
            ->where('wallet_type', 'leader_wallet')
            ->where('scope_type', 'user')
            ->where('scope_id', (string) $tenure->user_id)
            ->first();

        return [
            'leader_tenure_id' => $tenure->id,
            'leader_user_id' => $tenure->user_id,
            'leader_name' => trim(($tenure->user?->first_name ?? '').' '.($tenure->user?->last_name ?? '')) ?: $tenure->user?->display_name,
            'role_code' => $roleCode,
            'scope_type' => $tenure->scope_type,
            'scope_id' => $tenure->scope_id,
            'scope_name' => $tenure->scope_name_snapshot,
            'tenure_start_at' => $tenure->tenure_start_at?->toIso8601String(),
            'newly_credited_personal_bonus_inr' => round($newlyCreditedAmount, 2),
            'personal_wallet' => $personalWallet ? [
                'wallet_id' => $personalWallet->id,
                'account_number' => $personalWallet->account_number,
                'wallet_name' => $personalWallet->wallet_name,
                'wallet_type' => $personalWallet->wallet_type,
                'current_balance_inr' => (float) $personalWallet->current_balance,
                'total_credited_inr' => (float) $personalWallet->total_credited,
                'total_debited_inr' => (float) $personalWallet->total_debited,
            ] : null,
            'evaluated_bonuses_count' => count($evaluatedAwards),
            'bonuses' => $evaluatedAwards,
        ];
    }

    /**
     * Get a complete Personal Bonus Wallet & Awards Summary for a specific Leader (`userId`).
     *
     * @return array<string, mixed>
     */
    public function getLeaderPersonalBonusSummary(string $userId): array
    {
        $user = User::query()->findOrFail($userId);
        $leaderName = trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: ($user->display_name ?: 'Leader');

        $wallet = FinanceWallet::query()
            ->where('wallet_type', 'leader_wallet')
            ->where('scope_type', 'user')
            ->where('scope_id', (string) $user->id)
            ->first();

        $tenures = FinanceLeaderTenure::query()
            ->where('user_id', (string) $user->id)
            ->orderByDesc('tenure_start_at')
            ->get();

        $awards = FinanceLeaderBonusAward::query()
            ->with(['rule:id,rule_code,bonus_category,metric_type,title,requires_approval'])
            ->where('leader_user_id', (string) $user->id)
            ->orderByDesc('updated_at')
            ->get();

        $ledgerTransactions = $wallet
            ? FinanceLedgerTransaction::query()
                ->where('wallet_id', $wallet->id)
                ->orderByDesc('created_at')
                ->get()
            : collect();

        return [
            'leader' => [
                'id' => $user->id,
                'name' => $leaderName,
                'email' => $user->email,
            ],
            'personal_bonus_wallet' => $wallet ? [
                'wallet_id' => $wallet->id,
                'account_number' => $wallet->account_number,
                'wallet_name' => $wallet->wallet_name,
                'wallet_type' => $wallet->wallet_type,
                'current_balance_inr' => (float) $wallet->current_balance,
                'total_credited_inr' => (float) $wallet->total_credited,
                'total_debited_inr' => (float) $wallet->total_debited,
                'locked_balance_inr' => (float) $wallet->locked_balance,
                'credit_count' => (int) $wallet->credit_count,
                'debit_count' => (int) $wallet->debit_count,
            ] : null,
            'summary_counts' => [
                'total_tenures' => $tenures->count(),
                'credited_bonuses_count' => $awards->where('status', 'credited')->count(),
                'pending_approval_bonuses_count' => $awards->where('status', 'pending_approval')->count(),
                'in_progress_bonuses_count' => $awards->where('status', 'in_progress')->count(),
                'total_personal_bonus_earned_inr' => (float) $awards->where('status', 'credited')->sum('bonus_amount_inr'),
            ],
            'tenures' => $tenures,
            'bonus_awards' => $awards,
            'passbook_transactions' => $ledgerTransactions,
        ];
    }

    /**
     * Approve a pending_approval (or in_progress) bonus award and immediately credit the Leader's Personal Bonus Wallet.
     */
    public function approveBonusAward(string $awardId, ?string $adminNotes = null, ?string $adminId = null): FinanceLeaderBonusAward
    {
        return DB::transaction(function () use ($awardId, $adminNotes, $adminId): FinanceLeaderBonusAward {
            /** @var FinanceLeaderBonusAward $award */
            $award = FinanceLeaderBonusAward::query()
                ->with(['rule', 'tenure.user'])
                ->lockForUpdate()
                ->findOrFail($awardId);

            if ($award->status === 'credited') {
                return $award->load(['wallet', 'ledgerTransaction']);
            }

            if ($adminNotes !== null) {
                $award->admin_notes = $adminNotes;
            }
            $award->achieved_at = $award->achieved_at ?: now();
            $award->approved_by_admin_id = $adminId;
            $award->approved_at = now();
            $award->progress_percentage = 100.00;
            $award->save();

            return $this->creditPersonalBonusToLeaderWallet(
                award: $award,
                rule: $award->rule,
                tenure: $award->tenure,
                actorAdminId: $adminId,
            )->load(['rule', 'wallet', 'ledgerTransaction']);
        });
    }

    /**
     * Reject a pending bonus award.
     */
    public function rejectBonusAward(string $awardId, string $reason, ?string $adminId = null): FinanceLeaderBonusAward
    {
        return DB::transaction(function () use ($awardId, $reason, $adminId): FinanceLeaderBonusAward {
            /** @var FinanceLeaderBonusAward $award */
            $award = FinanceLeaderBonusAward::query()->lockForUpdate()->findOrFail($awardId);

            if ($award->status === 'credited') {
                throw new RuntimeException('Cannot reject a bonus award that has already been credited to the leader wallet.');
            }

            $award->status = 'rejected';
            $award->admin_notes = $reason;
            $award->approved_by_admin_id = $adminId;
            $award->approved_at = now();
            $award->save();

            return $award->load('rule');
        });
    }

    /**
     * Credit the personal bonus amount directly to the Leader's Personal Bonus Wallet (`leader_wallet`)
     * and write an immutable CREDIT row into `finance_ledger_transactions`.
     */
    private function creditPersonalBonusToLeaderWallet(
        FinanceLeaderBonusAward $award,
        FinanceBonusRule $rule,
        FinanceLeaderTenure $tenure,
        ?string $actorAdminId = null,
    ): FinanceLeaderBonusAward {
        $leaderUser = $tenure->user ?: User::query()->find($tenure->user_id);
        $leaderName = $leaderUser
            ? (trim(($leaderUser->first_name ?? '').' '.($leaderUser->last_name ?? '')) ?: ($leaderUser->display_name ?: 'Leader'))
            : 'Leader';

        $wallet = FinanceWallet::query()->firstOrCreate(
            [
                'wallet_type' => 'leader_wallet',
                'scope_type' => 'user',
                'scope_id' => (string) $tenure->user_id,
            ],
            [
                'wallet_name' => $leaderName.' — Personal Bonus & Incentive Wallet',
                'current_custodian_user_id' => (string) $tenure->user_id,
                'currency' => 'INR',
                'current_balance' => 0.00,
                'total_credited' => 0.00,
                'total_debited' => 0.00,
                'locked_balance' => 0.00,
                'credit_count' => 0,
                'debit_count' => 0,
                'status' => 'active',
            ]
        );

        /** @var FinanceWallet $lockedWallet */
        $lockedWallet = FinanceWallet::query()->lockForUpdate()->findOrFail($wallet->id);

        $openingBalance = (float) $lockedWallet->current_balance;
        $creditAmount = (float) $award->bonus_amount_inr;
        $closingBalance = round($openingBalance + $creditAmount, 2);

        $lockedWallet->current_balance = $closingBalance;
        $lockedWallet->total_credited = round((float) $lockedWallet->total_credited + $creditAmount, 2);
        $lockedWallet->credit_count = (int) $lockedWallet->credit_count + 1;
        $lockedWallet->save();

        $perksSuffix = ! empty($award->reward_perks) ? " + {$award->reward_perks}" : '';
        $narration = sprintf(
            'Personal Bonus Credit ₹%s%s [%s: %s] for %s (%s - %s)',
            number_format($creditAmount, 2),
            $perksSuffix,
            $rule->rule_code,
            $rule->title,
            $leaderName,
            $tenure->role_code,
            $tenure->scope_name_snapshot ?: $tenure->scope_id,
        );

        $txn = FinanceLedgerTransaction::query()->create([
            'wallet_id' => $lockedWallet->id,
            'transaction_type' => 'CREDIT',
            'entry_category' => 'LEADER_PERFORMANCE_BONUS',
            'leader_user_id' => $tenure->user_id,
            'leader_tenure_id' => $tenure->id,
            'role_id' => $tenure->role_id,
            'role_code' => $tenure->role_code,
            'scope_type' => 'user',
            'scope_id' => (string) $tenure->user_id,
            'base_amount' => $creditAmount,
            'applied_percentage' => 100.00,
            'amount' => $creditAmount,
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'status' => 'posted',
            'narration' => $narration,
            'metadata' => [
                'bonus_award_id' => $award->id,
                'award_ref' => $award->award_ref,
                'bonus_rule_id' => $rule->id,
                'rule_code' => $rule->rule_code,
                'bonus_category' => $rule->bonus_category,
                'metric_type' => $rule->metric_type,
                'title' => $rule->title,
                'reward_perks' => $award->reward_perks,
                'tenure_scope_type' => $tenure->scope_type,
                'tenure_scope_id' => $tenure->scope_id,
                'tenure_scope_name' => $tenure->scope_name_snapshot,
            ],
            'created_by_admin_id' => $actorAdminId,
        ]);

        $award->status = 'credited';
        $award->wallet_id = $lockedWallet->id;
        $award->ledger_transaction_id = $txn->id;
        $award->save();

        return $award;
    }

    /**
     * Compute the live metric value, target, and achievement status for a specific bonus rule and leader tenure.
     *
     * @param  array<string, mixed>  $manualMetrics
     * @return array{current_value: float, target_value: float, progress_percentage: float, is_achieved: bool, proof_metadata: array<string, mixed>}
     */
    private function calculateMetricForRule(
        FinanceLeaderTenure $tenure,
        FinanceBonusRule $rule,
        Carbon $countingStartedAt,
        ?Carbon $deadlineAt,
        ?int $effectiveTargetMembers,
        array $manualMetrics,
    ): array {
        $metricType = $rule->metric_type;

        switch ($metricType) {
            case 'NEW_MEMBERS_IN_DAYS':
                $target = (float) ($effectiveTargetMembers ?: 10);
                $freeExcludedCount = 0;
                if (isset($manualMetrics['new_members_count'])) {
                    $current = (float) $manualMetrics['new_members_count'];
                    $freeExcludedCount = (int) ($manualMetrics['free_members_excluded_count'] ?? 0);
                } else {
                    // Strictly count ONLY PAID peers (exclude free_peer / free_trial_peer / visitor)
                    $windowEnd = $deadlineAt ?: now();
                    $creditedPeerIds = FinanceLedgerTransaction::query()
                        ->where('leader_tenure_id', $tenure->id)
                        ->where('transaction_type', 'CREDIT')
                        ->where('entry_category', 'SUBSCRIPTION_COMMISSION')
                        ->where('status', 'posted')
                        ->where('created_at', '>=', $countingStartedAt)
                        ->where('created_at', '<=', $windowEnd)
                        ->whereNotNull('member_user_id')
                        ->distinct()
                        ->pluck('member_user_id')
                        ->map(fn ($id) => (string) $id)
                        ->all();

                    $paidCirclePeerIds = [];
                    if ($tenure->scope_type === 'circle' && ! empty($tenure->scope_id) && Schema::hasTable('circle_members')) {
                        $circleUserIds = CircleMember::query()
                            ->where('circle_id', $tenure->scope_id)
                            ->whereNull('deleted_at')
                            ->where('user_id', '!=', $tenure->user_id)
                            ->where('created_at', '>=', $countingStartedAt)
                            ->where('created_at', '<=', $windowEnd)
                            ->distinct()
                            ->pluck('user_id')
                            ->map(fn ($id) => (string) $id)
                            ->all();

                        if (! empty($circleUserIds)) {
                            [$paidCirclePeerIds, $freeExcludedCount] = $this->partitionPaidAndFreeUserIds($circleUserIds, $creditedPeerIds);
                        }
                    }

                    // Also check direct referrals of this leader who joined in the window to count how many were free vs paid
                    if (Schema::hasColumn('users', 'introduced_by')) {
                        $referredUserIds = User::query()
                            ->where('introduced_by', $tenure->user_id)
                            ->where('id', '!=', $tenure->user_id)
                            ->where('created_at', '>=', $countingStartedAt)
                            ->where('created_at', '<=', $windowEnd)
                            ->pluck('id')
                            ->map(fn ($id) => (string) $id)
                            ->all();

                        if (! empty($referredUserIds)) {
                            [$paidReferredIds, $referredFreeCount] = $this->partitionPaidAndFreeUserIds($referredUserIds, $creditedPeerIds);
                            $paidCirclePeerIds = array_values(array_unique(array_merge($paidCirclePeerIds, $paidReferredIds)));
                            $freeExcludedCount = max($freeExcludedCount, $referredFreeCount);
                        }
                    }

                    $allPaidIds = array_values(array_unique(array_merge($creditedPeerIds, $paidCirclePeerIds)));
                    $current = (float) count($allPaidIds);
                }

                $progress = $target > 0 ? min(100.0, round(($current / $target) * 100.0, 2)) : 0.0;
                $isWithinDeadline = $deadlineAt === null || now()->lte($deadlineAt) || isset($manualMetrics['new_members_count']);

                return [
                    'current_value' => $current,
                    'target_value' => $target,
                    'progress_percentage' => $progress,
                    'is_achieved' => $current >= $target && $isWithinDeadline,
                    'proof_metadata' => [
                        'metric_type' => $metricType,
                        'counting_started_at' => $countingStartedAt->toIso8601String(),
                        'deadline_at' => $deadlineAt?->toIso8601String(),
                        'new_members_counted' => (int) $current,
                        'paid_members_counted' => (int) $current,
                        'free_members_excluded_count' => $freeExcludedCount,
                        'eligibility_policy' => 'PAID_PEERS_ONLY (Free Peers / Free Trial Peers are strictly excluded)',
                    ],
                ];

            case 'NET_MEMBER_STRENGTH':
                $target = (float) ($effectiveTargetMembers ?: 50);
                $freeExcludedCount = 0;
                if (isset($manualMetrics['net_member_strength'])) {
                    $current = (float) $manualMetrics['net_member_strength'];
                    $freeExcludedCount = (int) ($manualMetrics['free_members_excluded_count'] ?? 0);
                } elseif ($tenure->scope_type === 'circle' && ! empty($tenure->scope_id)) {
                    $circleUserIds = CircleMember::query()
                        ->where('circle_id', $tenure->scope_id)
                        ->whereNull('deleted_at')
                        ->distinct()
                        ->pluck('user_id')
                        ->map(fn ($id) => (string) $id)
                        ->all();

                    [$paidIds, $freeExcludedCount] = $this->partitionPaidAndFreeUserIds($circleUserIds);
                    $current = (float) count($paidIds);
                } else {
                    // Industry (ID) or District (DED): count PAID members across all circles in scope
                    $circleIds = $this->resolveCircleIdsForScope($tenure->scope_type, $tenure->scope_id, $tenure->user_id);
                    if (! empty($circleIds)) {
                        $scopeUserIds = CircleMember::query()
                            ->whereIn('circle_id', $circleIds)
                            ->whereNull('deleted_at')
                            ->distinct()
                            ->pluck('user_id')
                            ->map(fn ($id) => (string) $id)
                            ->all();
                        [$paidIds, $freeExcludedCount] = $this->partitionPaidAndFreeUserIds($scopeUserIds);
                        $current = (float) count($paidIds);
                    } else {
                        $current = 0.0;
                    }
                }

                $progress = $target > 0 ? min(100.0, round(($current / $target) * 100.0, 2)) : 0.0;

                return [
                    'current_value' => $current,
                    'target_value' => $target,
                    'progress_percentage' => $progress,
                    'is_achieved' => $current >= $target,
                    'proof_metadata' => [
                        'metric_type' => $metricType,
                        'scope_type' => $tenure->scope_type,
                        'net_active_members' => (int) $current,
                        'paid_members_counted' => (int) $current,
                        'free_members_excluded_count' => $freeExcludedCount,
                        'eligibility_policy' => 'PAID_PEERS_ONLY (Free Peers / Free Trial Peers are strictly excluded)',
                    ],
                ];

            case 'CIRCLES_LAUNCHED_IN_DAYS':
                $target = (float) ($rule->target_circles_count ?: 5);
                $minAvgMembers = (int) ($rule->min_avg_members_per_circle ?: 0);

                if (isset($manualMetrics['circles_launched_count'])) {
                    $current = (float) $manualMetrics['circles_launched_count'];
                    $avgMembers = isset($manualMetrics['avg_members_per_circle'])
                        ? (float) $manualMetrics['avg_members_per_circle']
                        : (float) max($minAvgMembers, 25);
                } else {
                    $windowEnd = $deadlineAt ?: now();
                    $circleIds = $this->resolveCircleIdsForScope($tenure->scope_type, $tenure->scope_id, $tenure->user_id);
                    $launchedCircles = ! empty($circleIds)
                        ? Circle::query()
                            ->whereIn('id', $circleIds)
                            ->where('created_at', '>=', $countingStartedAt)
                            ->where('created_at', '<=', $windowEnd)
                            ->pluck('id')
                            ->all()
                        : [];

                    $current = (float) count($launchedCircles);
                    if ($current > 0) {
                        $circleMembersRows = CircleMember::query()
                            ->whereIn('circle_id', $launchedCircles)
                            ->whereNull('deleted_at')
                            ->get(['circle_id', 'user_id']);
                        $allMemberUserIds = $circleMembersRows->pluck('user_id')->map(fn ($id) => (string) $id)->unique()->values()->all();
                        [$paidUserIds] = $this->partitionPaidAndFreeUserIds($allMemberUserIds);
                        $paidLookup = array_flip($paidUserIds);
                        $totalPaidMembers = $circleMembersRows->filter(fn ($row) => isset($paidLookup[(string) $row->user_id]))->count();
                        $avgMembers = round($totalPaidMembers / $current, 2);
                    } else {
                        $avgMembers = 0.0;
                    }
                }

                $meetsAvgRequirement = $minAvgMembers <= 0 || $avgMembers >= $minAvgMembers;
                $progress = $target > 0 ? min(100.0, round(($current / $target) * 100.0, 2)) : 0.0;

                return [
                    'current_value' => $current,
                    'target_value' => $target,
                    'progress_percentage' => $progress,
                    'is_achieved' => $current >= $target && $meetsAvgRequirement,
                    'proof_metadata' => [
                        'metric_type' => $metricType,
                        'circles_launched' => (int) $current,
                        'avg_members_per_circle' => $avgMembers,
                        'required_min_avg_members' => $minAvgMembers,
                        'meets_avg_requirement' => $meetsAvgRequirement,
                        'eligibility_policy' => 'PAID_PEERS_ONLY',
                    ],
                ];

            case 'MEETING_ATTENDANCE_RATE':
                $target = (float) ($rule->target_percentage ?: 90.00);
                $requiredMeetings = (int) ($rule->target_meetings_count ?: 6);
                $current = isset($manualMetrics['meeting_attendance_rate']) ? (float) $manualMetrics['meeting_attendance_rate'] : 0.0;
                $meetingsCount = isset($manualMetrics['meetings_evaluated_count']) ? (int) $manualMetrics['meetings_evaluated_count'] : ($current > 0 ? $requiredMeetings : 0);
                $progress = $target > 0 ? min(100.0, round(($current / $target) * 100.0, 2)) : 0.0;

                return [
                    'current_value' => $current,
                    'target_value' => $target,
                    'progress_percentage' => $progress,
                    'is_achieved' => $current >= $target && $meetingsCount >= $requiredMeetings,
                    'proof_metadata' => [
                        'metric_type' => $metricType,
                        'window_mode' => $rule->meeting_window_mode,
                        'required_meetings' => $requiredMeetings,
                        'meetings_evaluated' => $meetingsCount,
                        'attendance_rate_percent' => $current,
                    ],
                ];

            case 'ANNUAL_RENEWAL_RATE':
                $target = (float) ($rule->target_percentage ?: 90.00);
                $current = isset($manualMetrics['annual_renewal_rate']) ? (float) $manualMetrics['annual_renewal_rate'] : 0.0;
                $progress = $target > 0 ? min(100.0, round(($current / $target) * 100.0, 2)) : 0.0;

                return [
                    'current_value' => $current,
                    'target_value' => $target,
                    'progress_percentage' => $progress,
                    'is_achieved' => $current >= $target,
                    'proof_metadata' => [
                        'metric_type' => $metricType,
                        'renewal_rate_percent' => $current,
                    ],
                ];

            case 'DOCUMENTED_COLLABORATIONS':
                $target = (float) ($rule->target_collaborations_count ?: 100);
                $current = isset($manualMetrics['documented_collaborations_count']) ? (float) $manualMetrics['documented_collaborations_count'] : 0.0;
                $progress = $target > 0 ? min(100.0, round(($current / $target) * 100.0, 2)) : 0.0;

                return [
                    'current_value' => $current,
                    'target_value' => $target,
                    'progress_percentage' => $progress,
                    'is_achieved' => $current >= $target,
                    'proof_metadata' => [
                        'metric_type' => $metricType,
                        'documented_collaborations_count' => (int) $current,
                    ],
                ];

            case 'ZERO_CATEGORY_DISPUTES':
                $maxAllowed = (int) ($rule->max_disputes_allowed ?? 0);
                $hasDisputeInput = array_key_exists('category_disputes_count', $manualMetrics) && $manualMetrics['category_disputes_count'] !== null;
                $disputes = $hasDisputeInput ? (int) $manualMetrics['category_disputes_count'] : 0;
                $isAchieved = $hasDisputeInput && $disputes <= $maxAllowed;

                return [
                    'current_value' => (float) $disputes,
                    'target_value' => (float) $maxAllowed,
                    'progress_percentage' => $isAchieved ? 100.0 : 0.0,
                    'is_achieved' => $isAchieved,
                    'proof_metadata' => [
                        'metric_type' => $metricType,
                        'category_disputes_count' => $disputes,
                        'max_disputes_allowed' => $maxAllowed,
                    ],
                ];

            case 'POST_HANDOVER_CONSISTENCY':
                $targetDays = (float) ($rule->timeframe_days ?: 180);
                $consistentDays = isset($manualMetrics['post_handover_consistent_days']) ? (float) $manualMetrics['post_handover_consistent_days'] : 0.0;
                $progress = $targetDays > 0 ? min(100.0, round(($consistentDays / $targetDays) * 100.0, 2)) : 0.0;

                return [
                    'current_value' => $consistentDays,
                    'target_value' => $targetDays,
                    'progress_percentage' => $progress,
                    'is_achieved' => $consistentDays >= $targetDays,
                    'proof_metadata' => [
                        'metric_type' => $metricType,
                        'post_handover_consistent_days' => (int) $consistentDays,
                    ],
                ];

            default:
                return [
                    'current_value' => 0.0,
                    'target_value' => 1.0,
                    'progress_percentage' => 0.0,
                    'is_achieved' => false,
                    'proof_metadata' => ['metric_type' => $metricType],
                ];
        }
    }

    /**
     * Resolve all Circle IDs belonging to an Industry (ID) or District (DED) scope.
     *
     * @return array<int, string>
     */
    private function resolveCircleIdsForScope(string $scopeType, ?string $scopeId, ?string $leaderUserId = null): array
    {
        if (! Schema::hasTable('circles')) {
            return [];
        }

        $circleIds = [];

        if ($scopeType === 'industry' && $scopeId) {
            if (Schema::hasColumn('circles', 'template_id')) {
                $circleIds = array_merge(
                    $circleIds,
                    Circle::query()->where('template_id', $scopeId)->pluck('id')->all()
                );
            }
            if (Schema::hasTable('circle_category_mappings')) {
                $circleIds = array_merge(
                    $circleIds,
                    DB::table('circle_category_mappings')->where('category_id', $scopeId)->pluck('circle_id')->all()
                );
            }
        } elseif ($scopeType === 'district' && $scopeId) {
            if (Schema::hasColumn('circles', 'district_id')) {
                $circleIds = array_merge(
                    $circleIds,
                    Circle::query()->where('district_id', $scopeId)->pluck('id')->all()
                );
            }
        }

        if ($leaderUserId) {
            if (Schema::hasColumn('circles', 'industry_director_user_id')) {
                $circleIds = array_merge(
                    $circleIds,
                    Circle::query()->where('industry_director_user_id', $leaderUserId)->pluck('id')->all()
                );
            }
            if (Schema::hasColumn('circles', 'ded_user_id')) {
                $circleIds = array_merge(
                    $circleIds,
                    Circle::query()->where('ded_user_id', $leaderUserId)->pluck('id')->all()
                );
            }
        }

        return array_values(array_unique(array_map('strval', array_filter($circleIds))));
    }

    /**
     * Partition candidate user IDs into [paidUserIds, freeExcludedCount].
     * Strictly excludes Free Peers ('free_peer', 'Free Peer', 'free_trial_peer', 'visitor', 'free')
     * who have not paid a subscription fee.
     *
     * @param  array<int, string>  $candidateUserIds
     * @param  array<int, string>  $knownPaidUserIds
     * @return array{0: array<int, string>, 1: int}
     */
    private function partitionPaidAndFreeUserIds(array $candidateUserIds, array $knownPaidUserIds = []): array
    {
        $candidateUserIds = array_values(array_unique(array_filter(array_map('strval', $candidateUserIds))));
        if (empty($candidateUserIds)) {
            return [[], 0];
        }

        $ledgerPaidIds = FinanceLedgerTransaction::query()
            ->whereIn('member_user_id', $candidateUserIds)
            ->where('transaction_type', 'CREDIT')
            ->where('entry_category', 'SUBSCRIPTION_COMMISSION')
            ->where('status', 'posted')
            ->distinct()
            ->pluck('member_user_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $paidLookup = array_flip(array_merge($knownPaidUserIds, $ledgerPaidIds));

        $hasMembershipCol = Schema::hasColumn('users', 'membership_status');
        $hasLastPaymentCol = Schema::hasColumn('users', 'last_payment_at');

        $selectCols = ['id'];
        if ($hasMembershipCol) {
            $selectCols[] = 'membership_status';
        }
        if ($hasLastPaymentCol) {
            $selectCols[] = 'last_payment_at';
        }

        $users = User::query()
            ->whereIn('id', $candidateUserIds)
            ->get($selectCols)
            ->keyBy('id');

        $paidIds = [];
        $freeCount = 0;
        $freeStatuses = ['free_peer', 'free peer', 'free_trial_peer', 'visitor', 'free', ''];

        foreach ($candidateUserIds as $uid) {
            if (isset($paidLookup[$uid])) {
                $paidIds[] = $uid;

                continue;
            }

            $u = $users->get($uid);
            if (! $u) {
                continue;
            }

            $mStatus = $hasMembershipCol ? strtolower(trim((string) ($u->getAttribute('membership_status') ?? ''))) : '';
            $hasPaidAt = $hasLastPaymentCol && $u->getAttribute('last_payment_at') !== null;

            if ($hasPaidAt || ($hasMembershipCol && ! in_array($mStatus, $freeStatuses, true))) {
                $paidIds[] = $uid;
            } else {
                $freeCount++;
            }
        }

        return [array_values(array_unique($paidIds)), $freeCount];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAwardOutput(FinanceLeaderBonusAward $award, FinanceBonusRule $rule): array
    {
        return [
            'award_id' => $award->id,
            'award_ref' => $award->award_ref,
            'bonus_rule_id' => $rule->id,
            'rule_code' => $rule->rule_code,
            'bonus_category' => $rule->bonus_category,
            'metric_type' => $rule->metric_type,
            'title' => $rule->title,
            'counting_started_at' => $award->counting_started_at?->toIso8601String(),
            'deadline_at' => $award->deadline_at?->toIso8601String(),
            'current_metric_value' => (float) $award->current_metric_value,
            'target_metric_value' => (float) $award->target_metric_value,
            'progress_percentage' => (float) $award->progress_percentage,
            'bonus_amount_inr' => (float) $award->bonus_amount_inr,
            'reward_perks' => $award->reward_perks,
            'requires_approval' => (bool) $rule->requires_approval,
            'status' => $award->status,
            'achieved_at' => $award->achieved_at?->toIso8601String(),
            'wallet_id' => $award->wallet_id,
            'ledger_transaction_id' => $award->ledger_transaction_id,
            'proof_metadata' => $award->proof_metadata,
        ];
    }
}
