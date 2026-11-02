<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Circle;
use App\Models\Finance\FinanceLeaderTenure;
use App\Models\Finance\FinanceWallet;
use App\Models\Role;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LeaderTenureService
{
    /**
     * Normalize any role key or code into a canonical Finance Role Code.
     */
    public function normalizeRoleCode(string $roleKeyOrCode): string
    {
        $clean = strtolower(trim(str_replace(['-', ' '], '_', $roleKeyOrCode)));

        return match ($clean) {
            'cd', 'circle_director', 'director', 'circledirector' => 'CD',
            'cf', 'circle_founder', 'founder', 'circlefounder' => 'CF',
            'ded', 'district_exec_director', 'district_executive_director', 'district_director' => 'DED',
            'id', 'ied', 'industry_director', 'industrydirector' => 'ID',
            'eed', 'executive_director' => 'EED',
            'chair', 'circle_chair', 'circlechair', 'business_growth_committee' => 'CHAIR',
            'vice_chair', 'vicechair', 'membership_growth_committee' => 'VICE_CHAIR',
            'secretary', 'events_impacts_committee' => 'SECRETARY',
            default => strtoupper($clean),
        };
    }

    /**
     * Map canonical Role Code to canonical role_key.
     */
    public function canonicalRoleKey(string $roleCode): string
    {
        return match (strtoupper($roleCode)) {
            'CD' => 'circle_director',
            'CF' => 'circle_founder',
            'DED' => 'ded',
            'ID' => 'industry_director',
            'EED' => 'eed',
            'CHAIR' => 'chair',
            'VICE_CHAIR' => 'vice_chair',
            'SECRETARY' => 'secretary',
            default => strtolower($roleCode),
        };
    }

    /**
     * Assign or rotate a leader for a given role and scope, automatically closing any previous active tenure.
     */
    public function assignOrRotateTenure(
        string $userId,
        string $roleKeyOrCode,
        string $scopeType,
        ?string $scopeId,
        ?string $assignedByAdminId = null,
        string $assignmentSource = 'system',
        ?CarbonInterface $startAt = null,
        ?CarbonInterface $expectedEndAt = null,
    ): FinanceLeaderTenure {
        $roleCode = $this->normalizeRoleCode($roleKeyOrCode);
        $roleKey = $this->canonicalRoleKey($roleCode);
        $scopeType = strtolower(trim($scopeType));
        $effectiveStart = $startAt ? Carbon::instance($startAt) : now();
        $effectiveExpectedEnd = $expectedEndAt ? Carbon::instance($expectedEndAt) : $effectiveStart->copy()->addMonths(6);

        return DB::transaction(function () use (
            $userId,
            $roleCode,
            $roleKey,
            $scopeType,
            $scopeId,
            $assignedByAdminId,
            $assignmentSource,
            $effectiveStart,
            $effectiveExpectedEnd
        ): FinanceLeaderTenure {
            // Check if the same user already has an open active tenure for this exact role & scope
            $existingSameUser = FinanceLeaderTenure::query()
                ->where('user_id', $userId)
                ->where('role_code', $roleCode)
                ->where('scope_type', $scopeType)
                ->when($scopeId !== null, fn ($q) => $q->where('scope_id', $scopeId), fn ($q) => $q->whereNull('scope_id'))
                ->where('is_active', true)
                ->whereNull('tenure_end_at')
                ->first();

            if ($existingSameUser) {
                return $existingSameUser;
            }

            // Close any other leader's active tenure for this role & scope
            FinanceLeaderTenure::query()
                ->where('role_code', $roleCode)
                ->where('scope_type', $scopeType)
                ->when($scopeId !== null, fn ($q) => $q->where('scope_id', $scopeId), fn ($q) => $q->whereNull('scope_id'))
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'tenure_end_at' => $effectiveStart,
                    'removed_by_admin_id' => $assignedByAdminId,
                    'removal_reason' => 'Replaced by new leader assignment',
                    'updated_at' => now(),
                ]);

            $roleId = null;
            if (Schema::hasTable('roles')) {
                $roleId = Role::idByKey($roleKey);
            }

            $adminUserId = null;
            if (Schema::hasTable('admin_users') && Schema::hasTable('users')) {
                $email = DB::table('users')->where('id', $userId)->value('email');
                if ($email) {
                    $adminUserId = DB::table('admin_users')
                        ->whereRaw('LOWER(email) = ?', [strtolower((string) $email)])
                        ->value('id');
                }
            }

            $scopeName = $this->resolveScopeName($scopeType, $scopeId);

            $tenure = FinanceLeaderTenure::query()->create([
                'user_id' => $userId,
                'admin_user_id' => $adminUserId ? (string) $adminUserId : null,
                'role_id' => $roleId,
                'role_key' => $roleKey,
                'role_code' => $roleCode,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'scope_name_snapshot' => $scopeName,
                'tenure_start_at' => $effectiveStart,
                'tenure_end_at' => null,
                'expected_end_at' => $effectiveExpectedEnd,
                'is_active' => true,
                'assigned_by_admin_id' => $assignedByAdminId,
                'assignment_source' => $assignmentSource,
            ]);

            // Update custodian on corresponding scope budget wallet if one exists
            $walletType = match ($scopeType) {
                'circle' => 'circle_budget',
                'district' => 'district_budget',
                'industry' => 'industry_budget',
                default => null,
            };

            if ($walletType && $scopeId) {
                FinanceWallet::query()
                    ->where('wallet_type', $walletType)
                    ->where('scope_type', $scopeType)
                    ->where('scope_id', $scopeId)
                    ->update(['current_custodian_user_id' => $userId]);
            }

            return $tenure;
        });
    }

    /**
     * End an active tenure when a leader is unassigned/removed without an immediate replacement.
     */
    public function closeTenureForScope(
        string $roleKeyOrCode,
        string $scopeType,
        ?string $scopeId,
        ?string $userId = null,
        ?string $removedByAdminId = null,
        string $reason = 'Role unassigned',
    ): int {
        if (! Schema::hasTable('finance_leader_tenures')) {
            return 0;
        }

        $roleCode = $this->normalizeRoleCode($roleKeyOrCode);

        return FinanceLeaderTenure::query()
            ->where('role_code', $roleCode)
            ->where('scope_type', $scopeType)
            ->when($scopeId !== null, fn ($q) => $q->where('scope_id', $scopeId))
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'tenure_end_at' => now(),
                'removed_by_admin_id' => $removedByAdminId,
                'removal_reason' => $reason,
                'updated_at' => now(),
            ]);
    }

    /**
     * Sync all leadership columns of a Circle into finance_leader_tenures whenever a Circle is created/updated.
     */
    public function syncFromCircle(Circle $circle, ?string $actorAdminId = null, string $source = 'circle_sync'): void
    {
        if (! Schema::hasTable('finance_leader_tenures')) {
            return;
        }

        $calendar = is_array($circle->calendar) ? $circle->calendar : [];

        $circleLeaders = [
            'CD' => $circle->circle_director_user_id ?: ($circle->getAttribute('director_user_id') ?? null),
            'CF' => $circle->circle_founder_user_id ?: ($circle->getAttribute('founder_user_id') ?? null),
            'DED' => $circle->ded_user_id,
            'ID' => $circle->industry_director_user_id,
            'EED' => $circle->eed_user_id,
            'CHAIR' => $circle->getAttribute('chair_user_id') ?? data_get($calendar, 'leadership.chair_user_id') ?? data_get($calendar, 'leadership.business_growth_committee_chair_user_id'),
            'VICE_CHAIR' => $circle->getAttribute('vice_chair_user_id') ?? data_get($calendar, 'leadership.vice_chair_user_id') ?? data_get($calendar, 'leadership.membership_growth_committee_chair_user_id'),
            'SECRETARY' => $circle->getAttribute('secretary_user_id') ?? data_get($calendar, 'leadership.secretary_user_id') ?? data_get($calendar, 'leadership.events_impacts_committee_chair_user_id'),
        ];

        foreach ($circleLeaders as $roleCode => $leaderUserId) {
            if (! empty($leaderUserId) && is_string($leaderUserId)) {
                $this->assignOrRotateTenure(
                    userId: $leaderUserId,
                    roleKeyOrCode: $roleCode,
                    scopeType: 'circle',
                    scopeId: (string) $circle->id,
                    assignedByAdminId: $actorAdminId,
                    assignmentSource: $source,
                );
            } else {
                $this->closeTenureForScope(
                    roleKeyOrCode: $roleCode,
                    scopeType: 'circle',
                    scopeId: (string) $circle->id,
                    removedByAdminId: $actorAdminId,
                    reason: 'Cleared on circle leadership update',
                );
            }
        }
    }

    /**
     * Resolve the active FinanceLeaderTenure for a role and scope at a specific timestamp.
     * If no tenure row exists yet but the Circle/District/Industry has a leader assigned, auto-backfills it.
     */
    public function resolveTenureAtDate(
        string $roleCode,
        Circle $circle,
        CarbonInterface $atDate,
    ): ?FinanceLeaderTenure {
        $normalizedRole = $this->normalizeRoleCode($roleCode);

        // 1. Check historical/active tenure covering $atDate for the circle (prioritizing active tenure on exact boundary second)
        $tenure = FinanceLeaderTenure::query()
            ->where('role_code', $normalizedRole)
            ->where('scope_type', 'circle')
            ->where('scope_id', (string) $circle->id)
            ->where('tenure_start_at', '<=', $atDate)
            ->where(function ($q) use ($atDate): void {
                $q->whereNull('tenure_end_at')
                    ->orWhere('tenure_end_at', '>', $atDate);
            })
            ->orderByDesc('is_active')
            ->orderByDesc('tenure_start_at')
            ->first();

        if ($tenure) {
            return $tenure;
        }

        // 2. Check latest active tenure for this circle
        $activeCircleTenure = FinanceLeaderTenure::query()
            ->where('role_code', $normalizedRole)
            ->where('scope_type', 'circle')
            ->where('scope_id', (string) $circle->id)
            ->where('is_active', true)
            ->latest('tenure_start_at')
            ->first();

        if ($activeCircleTenure) {
            return $activeCircleTenure;
        }

        // 3. Fallback: inspect the Circle model columns to auto-register the current leader's tenure
        $leaderUserId = match ($normalizedRole) {
            'CD' => $circle->circle_director_user_id,
            'CF' => $circle->circle_founder_user_id,
            'DED' => $circle->ded_user_id,
            'ID' => $circle->industry_director_user_id,
            'EED' => $circle->eed_user_id,
            'CHAIR' => $circle->chair_user_id,
            'VICE_CHAIR' => $circle->vice_chair_user_id,
            'SECRETARY' => $circle->secretary_user_id,
            default => null,
        };

        if (! empty($leaderUserId)) {
            $startAt = $circle->created_at ? Carbon::parse($circle->created_at) : $atDate->copy()->subMinute();
            if ($startAt->greaterThan($atDate)) {
                $startAt = $atDate->copy()->subMinute();
            }

            return $this->assignOrRotateTenure(
                userId: (string) $leaderUserId,
                roleKeyOrCode: $normalizedRole,
                scopeType: 'circle',
                scopeId: (string) $circle->id,
                assignmentSource: 'circle_backfill',
                startAt: $startAt,
            );
        }

        return null;
    }

    /**
     * Get enriched list of leader tenures with exact financial performance during each tenure.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function listTenuresWithFinancials(array $filters = []): array
    {
        $query = FinanceLeaderTenure::query()
            ->with(['user:id,first_name,last_name,display_name,email,phone'])
            ->orderByDesc('is_active')
            ->orderByDesc('tenure_start_at');

        if (! empty($filters['role_code'])) {
            $query->where('role_code', $this->normalizeRoleCode((string) $filters['role_code']));
        }
        if (! empty($filters['user_id'])) {
            $query->where('user_id', (string) $filters['user_id']);
        }
        if (! empty($filters['scope_id'])) {
            $query->where('scope_id', (string) $filters['scope_id']);
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $tenures = $query->limit(200)->get();

        return $tenures->map(function (FinanceLeaderTenure $tenure): array {
            $creditsQuery = DB::table('finance_ledger_transactions')
                ->where('leader_tenure_id', $tenure->id)
                ->where('transaction_type', 'CREDIT')
                ->where('status', 'posted');

            $debitsQuery = DB::table('finance_ledger_transactions')
                ->where('leader_tenure_id', $tenure->id)
                ->where('transaction_type', 'DEBIT')
                ->where('status', 'posted');

            $totalCredited = (float) (clone $creditsQuery)->sum('amount');
            $creditsCount = (int) (clone $creditsQuery)->count();
            $uniqueMembersCount = (int) (clone $creditsQuery)->whereNotNull('member_user_id')->distinct()->count('member_user_id');

            $totalDebited = (float) (clone $debitsQuery)->sum('amount');
            $debitsCount = (int) (clone $debitsQuery)->count();

            $endDate = $tenure->tenure_end_at ?? now();
            $durationDays = (int) $tenure->tenure_start_at->diffInDays($endDate);

            $user = $tenure->user;
            $leaderName = $user
                ? (trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: ($user->display_name ?? 'Leader'))
                : 'Leader';

            return [
                'tenure_id' => $tenure->id,
                'user_id' => $tenure->user_id,
                'leader_name' => $leaderName,
                'leader_email' => $user?->email,
                'role_code' => $tenure->role_code,
                'role_key' => $tenure->role_key,
                'scope_type' => $tenure->scope_type,
                'scope_id' => $tenure->scope_id,
                'scope_name' => $tenure->scope_name_snapshot ?: $this->resolveScopeName($tenure->scope_type, $tenure->scope_id),
                'tenure_start_at' => $tenure->tenure_start_at?->toIso8601String(),
                'tenure_end_at' => $tenure->tenure_end_at?->toIso8601String(),
                'expected_end_at' => $tenure->expected_end_at?->toIso8601String(),
                'is_active' => $tenure->is_active,
                'duration_days' => $durationDays,
                'assignment_source' => $tenure->assignment_source,
                'removal_reason' => $tenure->removal_reason,
                'financial_summary' => [
                    'members_attributed_count' => $uniqueMembersCount,
                    'credit_transactions_count' => $creditsCount,
                    'total_credited_inr' => round($totalCredited, 2),
                    'debit_transactions_count' => $debitsCount,
                    'total_withdrawn_inr' => round($totalDebited, 2),
                    'net_budget_generated_inr' => round($totalCredited - $totalDebited, 2),
                ],
            ];
        })->all();
    }

    private function resolveScopeName(string $scopeType, ?string $scopeId): string
    {
        if (! $scopeId) {
            return 'Global';
        }

        if ($scopeType === 'circle' && Schema::hasTable('circles')) {
            return (string) (DB::table('circles')->where('id', $scopeId)->value('name') ?: 'Circle');
        }

        if ($scopeType === 'district' && Schema::hasTable('districts')) {
            return (string) (DB::table('districts')->where('id', $scopeId)->value('name') ?: 'District');
        }

        if ($scopeType === 'industry') {
            if (Schema::hasTable('circle_categories') && is_numeric($scopeId)) {
                $name = DB::table('circle_categories')->where('id', (int) $scopeId)->value('name');
                if ($name) {
                    return (string) $name;
                }
            }
            if (Schema::hasTable('industries') && Str::isUuid($scopeId)) {
                $name = DB::table('industries')->where('id', $scopeId)->value('name');
                if ($name) {
                    return (string) $name;
                }
            }

            return 'Industry '.$scopeId;
        }

        return ucfirst($scopeType);
    }
}
