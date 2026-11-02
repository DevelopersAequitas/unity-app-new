<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\Finance\FinanceAcquisitionChannel;
use App\Models\Finance\FinanceCommissionRule;
use App\Models\Finance\FinanceLeaderTenure;
use App\Models\Finance\FinanceLedgerTransaction;
use App\Models\Finance\FinancePolicyAuditLog;
use App\Models\Finance\FinanceWallet;
use App\Models\Finance\FinanceWithdrawalRequest;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class FinanceCommissionEngineService
{
    public const DEFAULT_BASE_AMOUNT_INR = 18000.00;

    public function __construct(
        private readonly LeaderTenureService $tenureService,
    ) {}

    /**
     * Return the standardized Executive Policy & Commission Matrix JSON specification for a role.
     *
     * @return array<string, mixed>
     */
    public function getRolePolicySpecification(string $roleCode = 'CD'): array
    {
        $normalizedRole = $this->tenureService->normalizeRoleCode($roleCode);

        $rules = FinanceCommissionRule::query()
            ->where('role_code', $normalizedRole)
            ->where('is_active', true)
            ->orderBy('category_code')
            ->get();

        $roleName = match ($normalizedRole) {
            'CD' => 'Circle Director',
            'CF' => 'Circle Founder',
            'DED' => 'District Executive Director',
            'ID' => 'Industry Director',
            'CHAIR' => 'Circle Chair',
            default => $rules->first()?->role_name ?? $normalizedRole,
        };

        $baseAmount = (float) ($rules->first()?->base_amount_inr ?? self::DEFAULT_BASE_AMOUNT_INR);

        $formattedRules = $rules->map(function (FinanceCommissionRule $rule): array {
            return [
                'rule_id' => $rule->id,
                'code' => $rule->category_code,
                'channel' => $rule->channel_key,
                'allocation_target' => $rule->allocation_target,
                'percentage' => (float) $rule->share_percentage,
                'payout_amount' => (float) $rule->payout_amount,
                'conditions' => $rule->conditions_list ?? [],
                'responsibilities' => $rule->responsibilities_list ?? [],
            ];
        })->values()->all();

        return [
            'role' => $roleName,
            'role_code' => $normalizedRole,
            'base_amount_inr' => $baseAmount,
            'commission_rules' => $formattedRules,
        ];
    }

    /**
     * Detect which of the 4+ Acquisition Conditions applies for a given Peer, Circle, and Role Leader.
     */
    public function detectAcquisitionChannel(
        User $member,
        Circle $circle,
        ?string $roleLeaderUserId = null,
        ?string $explicitIntroducerUserId = null,
        bool $isRenewal = false,
        ?string $overrideChannelKey = null,
    ): FinanceAcquisitionChannel {
        if ($overrideChannelKey !== null && trim($overrideChannelKey) !== '') {
            $override = FinanceAcquisitionChannel::query()
                ->where('channel_key', strtoupper(trim($overrideChannelKey)))
                ->orWhere('category_code', strtoupper(trim($overrideChannelKey)))
                ->first();

            if ($override) {
                return $override;
            }
        }

        // Condition 1 (Category A): Renewal of Existing Circle Member
        if ($isRenewal || $this->hasPriorPaidRenewalHistory($member, $circle)) {
            $channel = FinanceAcquisitionChannel::query()->where('channel_key', 'RENEWAL_EXISTING')->first();
            if ($channel) {
                return $channel;
            }
        }

        // Resolve who brought / introduced this peer
        $introducerId = $explicitIntroducerUserId
            ?: ($member->introduced_by ?: ($member->referred_by_user_id ?: null));

        if (! empty($introducerId)) {
            $introducerId = (string) $introducerId;

            // Condition 2 (Category B): Peer comes directly via the Leader (e.g. CD's referral code)
            $circleLeaderIds = array_filter([
                $roleLeaderUserId,
                $circle->circle_director_user_id,
                $circle->circle_founder_user_id,
                $circle->ded_user_id,
                $circle->industry_director_user_id,
            ]);

            if ($roleLeaderUserId !== null && $introducerId === (string) $roleLeaderUserId) {
                $channel = FinanceAcquisitionChannel::query()->where('channel_key', 'DIRECT_EFFORT')->first();
                if ($channel) {
                    return $channel;
                }
            }

            // Condition 3 (Category C): Peer comes via another active Circle Peer of the same circle
            $isCircleMemberReferrer = CircleMember::query()
                ->where('circle_id', $circle->id)
                ->where('user_id', $introducerId)
                ->whereNull('deleted_at')
                ->exists();

            if ($isCircleMemberReferrer || in_array($introducerId, $circleLeaderIds, true)) {
                $channelKey = ($roleLeaderUserId === null && in_array($introducerId, $circleLeaderIds, true))
                    ? 'DIRECT_EFFORT'
                    : 'MEMBER_REFERRAL';

                $channel = FinanceAcquisitionChannel::query()->where('channel_key', $channelKey)->first();
                if ($channel) {
                    return $channel;
                }
            }
        }

        // Condition 4 (Category D): Added by Peers Global / District / Regional Team or Direct App
        $channel = FinanceAcquisitionChannel::query()->where('channel_key', 'TEAM_ASSIGNED')->first();
        if (! $channel) {
            throw new RuntimeException('Default acquisition channel TEAM_ASSIGNED not found in finance_acquisition_channels.');
        }

        return $channel;
    }

    /**
     * Simulate commission and budget allocations for a peer joining/renewing without committing to the database.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function simulateAllocation(array $payload): array
    {
        $member = User::query()->findOrFail((string) $payload['member_user_id']);
        $explicitIntroducerId = isset($payload['introducer_user_id']) ? (string) $payload['introducer_user_id'] : null;
        $circle = $this->resolveCircleForMember(
            member: $member,
            explicitCircleId: ! empty($payload['circle_id']) ? (string) $payload['circle_id'] : null,
            explicitIntroducerId: $explicitIntroducerId,
        );
        $baseAmount = isset($payload['base_amount']) ? (float) $payload['base_amount'] : self::DEFAULT_BASE_AMOUNT_INR;
        $isRenewal = (bool) ($payload['is_renewal'] ?? false);
        $overrideChannel = isset($payload['channel_key']) ? (string) $payload['channel_key'] : null;
        $roleFilter = isset($payload['role_code']) ? $this->tenureService->normalizeRoleCode((string) $payload['role_code']) : null;
        $paymentDate = isset($payload['payment_date']) ? Carbon::parse((string) $payload['payment_date']) : now();

        $allocations = $this->computeAllocations(
            member: $member,
            circle: $circle,
            baseAmount: $baseAmount,
            isRenewal: $isRenewal,
            explicitIntroducerId: $explicitIntroducerId,
            overrideChannelKey: $overrideChannel,
            roleFilter: $roleFilter,
            atDate: $paymentDate,
        );

        $totalAllocated = collect($allocations)->sum('payout_amount');

        return [
            'simulation' => true,
            'member' => [
                'id' => $member->id,
                'name' => trim(($member->first_name ?? '').' '.($member->last_name ?? '')) ?: $member->display_name,
                'email' => $member->email,
                'introduced_by' => $explicitIntroducerId ?: ($member->introduced_by ?: $member->referred_by_user_id),
            ],
            'circle' => [
                'id' => $circle->id,
                'name' => $circle->name,
            ],
            'base_amount_inr' => round($baseAmount, 2),
            'total_allocated_inr' => round((float) $totalAllocated, 2),
            'remaining_company_treasury_inr' => round($baseAmount - (float) $totalAllocated, 2),
            'allocations' => $allocations,
        ];
    }

    /**
     * Automatically process commission & budget allocation when an Admin upgrades a peer's membership
     * (e.g. to 'Only Unity Peer' / 'Circle Peer' with 1-year validity).
     *
     * @return array<string, mixed>|null
     */
    public function autoProcessPeerMembershipUpgrade(User $member, ?string $actorAdminId = null, ?string $circleId = null): ?array
    {
        if (! Schema::hasTable('finance_ledger_transactions') || ! Schema::hasTable('finance_commission_rules')) {
            return null;
        }

        try {
            $circle = $this->resolveCircleForMember($member, $circleId, $member->introduced_by ? (string) $member->introduced_by : null);
        } catch (RuntimeException) {
            return null;
        }

        // Prevent duplicate credit if this member was already credited within the last 24 hours for this circle
        $recentCreditExists = FinanceLedgerTransaction::query()
            ->where('member_user_id', $member->id)
            ->where('scope_id', (string) $circle->id)
            ->where('transaction_type', 'CREDIT')
            ->where('status', 'posted')
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if ($recentCreditExists) {
            return null;
        }

        return $this->processSubscriptionPayment([
            'member_user_id' => (string) $member->id,
            'circle_id' => (string) $circle->id,
            'base_amount' => self::DEFAULT_BASE_AMOUNT_INR,
        ], $actorAdminId);
    }

    /**
     * Process a subscription payment and write atomic CREDIT entries into finance_wallets and finance_ledger_transactions.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function processSubscriptionPayment(array $payload, ?string $actorAdminId = null): array
    {
        $member = User::query()->findOrFail((string) $payload['member_user_id']);
        $explicitIntroducerId = ! empty($payload['introducer_user_id']) ? (string) $payload['introducer_user_id'] : null;
        $circle = $this->resolveCircleForMember(
            member: $member,
            explicitCircleId: ! empty($payload['circle_id']) ? (string) $payload['circle_id'] : null,
            explicitIntroducerId: $explicitIntroducerId,
        );
        $paymentId = ! empty($payload['payment_id']) && Str::isUuid((string) $payload['payment_id'])
            ? (string) $payload['payment_id']
            : null;
        $baseAmount = isset($payload['base_amount']) ? (float) $payload['base_amount'] : self::DEFAULT_BASE_AMOUNT_INR;
        $isRenewal = (bool) ($payload['is_renewal'] ?? false);
        $overrideChannel = ! empty($payload['channel_key']) ? (string) $payload['channel_key'] : null;
        $roleFilter = ! empty($payload['role_code']) ? $this->tenureService->normalizeRoleCode((string) $payload['role_code']) : null;
        $paymentDate = ! empty($payload['payment_date']) ? Carbon::parse((string) $payload['payment_date']) : now();

        if ($paymentId) {
            $paymentModel = Payment::query()->find($paymentId);
            if ($paymentModel && ! isset($payload['base_amount'])) {
                $resolvedBase = (float) ($paymentModel->base_amount ?: ($paymentModel->amount ?: self::DEFAULT_BASE_AMOUNT_INR));
                if ($resolvedBase > 0) {
                    $baseAmount = $resolvedBase;
                }
            }
        }

        $allocations = $this->computeAllocations(
            member: $member,
            circle: $circle,
            baseAmount: $baseAmount,
            isRenewal: $isRenewal,
            explicitIntroducerId: $explicitIntroducerId,
            overrideChannelKey: $overrideChannel,
            roleFilter: $roleFilter,
            atDate: $paymentDate,
        );

        return DB::transaction(function () use (
            $member,
            $circle,
            $paymentId,
            $baseAmount,
            $allocations,
            $actorAdminId
        ): array {
            $createdTransactions = [];

            foreach ($allocations as $alloc) {
                // Prevent duplicate processing for the same payment_id + role_code +rule_id
                if ($paymentId !== null) {
                    $alreadyProcessed = FinanceLedgerTransaction::query()
                        ->where('source_payment_id', $paymentId)
                        ->where('role_code', $alloc['role_code'])
                        ->where('rule_id', $alloc['rule_id'])
                        ->where('transaction_type', 'CREDIT')
                        ->where('status', 'posted')
                        ->exists();

                    if ($alreadyProcessed) {
                        continue;
                    }
                }

                $wallet = $this->resolveOrCreateWalletForAllocation($alloc, $circle);

                // Lock wallet row for atomic balance update
                /** @var FinanceWallet $lockedWallet */
                $lockedWallet = FinanceWallet::query()->lockForUpdate()->findOrFail($wallet->id);

                $openingBalance = (float) $lockedWallet->current_balance;
                $creditAmount = (float) $alloc['payout_amount'];
                $closingBalance = round($openingBalance + $creditAmount, 2);

                $lockedWallet->current_balance = $closingBalance;
                $lockedWallet->total_credited = round((float) $lockedWallet->total_credited + $creditAmount, 2);
                $lockedWallet->credit_count = (int) $lockedWallet->credit_count + 1;
                if (! empty($alloc['leader_user_id'])) {
                    $lockedWallet->current_custodian_user_id = (string) $alloc['leader_user_id'];
                }
                $lockedWallet->save();

                $memberName = trim(($member->first_name ?? '').' '.($member->last_name ?? '')) ?: ($member->display_name ?: 'Peer');
                $leaderLabel = $alloc['leader_name'] ?? 'Unassigned Leader';

                $narration = sprintf(
                    'Credit ₹%s (%s%% - Cat %s %s) for %s [%s] in %s (Leader: %s)',
                    number_format($creditAmount, 2),
                    number_format((float) $alloc['percentage'], 2),
                    $alloc['category_code'],
                    $alloc['channel_key'],
                    $memberName,
                    $alloc['role_code'],
                    $circle->name,
                    $leaderLabel,
                );

                $txn = FinanceLedgerTransaction::query()->create([
                    'wallet_id' => $lockedWallet->id,
                    'transaction_type' => 'CREDIT',
                    'entry_category' => 'SUBSCRIPTION_COMMISSION',
                    'source_payment_id' => $paymentId,
                    'member_user_id' => $member->id,
                    'introducer_user_id' => $alloc['introducer_user_id'],
                    'leader_user_id' => $alloc['leader_user_id'],
                    'leader_tenure_id' => $alloc['leader_tenure_id'],
                    'role_id' => $alloc['role_id'],
                    'role_code' => $alloc['role_code'],
                    'channel_id' => $alloc['channel_id'],
                    'channel_code' => $alloc['channel_key'],
                    'category_code' => $alloc['category_code'],
                    'rule_id' => $alloc['rule_id'],
                    'scope_type' => $alloc['scope_type'],
                    'scope_id' => $alloc['scope_id'],
                    'base_amount' => $baseAmount,
                    'applied_percentage' => $alloc['percentage'],
                    'amount' => $creditAmount,
                    'opening_balance' => $openingBalance,
                    'closing_balance' => $closingBalance,
                    'status' => 'posted',
                    'narration' => $narration,
                    'metadata' => [
                        'circle_id' => $circle->id,
                        'circle_name' => $circle->name,
                        'leader_name' => $alloc['leader_name'],
                        'tenure_start_at' => $alloc['tenure_start_at'],
                        'conditions' => $alloc['conditions'],
                    ],
                    'created_by_admin_id' => $actorAdminId,
                ]);

                $createdTransactions[] = [
                    'transaction_id' => $txn->id,
                    'transaction_ref' => $txn->transaction_ref,
                    'wallet_id' => $lockedWallet->id,
                    'wallet_account_number' => $lockedWallet->account_number,
                    'wallet_name' => $lockedWallet->wallet_name,
                    'wallet_type' => $lockedWallet->wallet_type,
                    'role_code' => $txn->role_code,
                    'category_code' => $txn->category_code,
                    'channel' => $txn->channel_code,
                    'leader_user_id' => $txn->leader_user_id,
                    'leader_name' => $alloc['leader_name'],
                    'leader_tenure_id' => $txn->leader_tenure_id,
                    'base_amount' => (float) $txn->base_amount,
                    'percentage' => (float) $txn->applied_percentage,
                    'credited_amount' => (float) $txn->amount,
                    'opening_balance' => (float) $txn->opening_balance,
                    'closing_balance' => (float) $txn->closing_balance,
                    'narration' => $txn->narration,
                ];
            }

            // Automatically evaluate Speed & Milestone Bonuses for any leader tenures credited in this payment
            $bonusEvaluations = [];
            if (Schema::hasTable('finance_bonus_rules') && Schema::hasTable('finance_leader_bonus_awards')) {
                $tenureIds = collect($createdTransactions)
                    ->pluck('leader_tenure_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $bonusEngine = app(LeaderBonusEngineService::class);
                foreach ($tenureIds as $tId) {
                    $bonusEvaluations[] = $bonusEngine->evaluateTenureBonuses((string) $tId, [], $actorAdminId);
                }
            }

            return [
                'member_user_id' => $member->id,
                'circle_id' => $circle->id,
                'payment_id' => $paymentId,
                'base_amount_inr' => round($baseAmount, 2),
                'total_credited_inr' => round((float) collect($createdTransactions)->sum('credited_amount'), 2),
                'transactions_created_count' => count($createdTransactions),
                'transactions' => $createdTransactions,
                'leader_bonus_evaluations' => $bonusEvaluations,
            ];
        });
    }

    /**
     * Create a withdrawal request from a budget or leader wallet.
     *
     * @param  array<string, mixed>  $data
     */
    public function createWithdrawalRequest(array $data): FinanceWithdrawalRequest
    {
        return DB::transaction(function () use ($data): FinanceWithdrawalRequest {
            /** @var FinanceWallet $wallet */
            $wallet = FinanceWallet::query()->lockForUpdate()->findOrFail((string) $data['wallet_id']);
            $amount = round((float) $data['amount'], 2);

            $availableBalance = round((float) $wallet->current_balance - (float) $wallet->locked_balance, 2);
            if ($amount <= 0 || $amount > $availableBalance) {
                throw new RuntimeException(sprintf(
                    'Insufficient available wallet balance. Available: ₹%s, Requested: ₹%s',
                    number_format($availableBalance, 2),
                    number_format($amount, 2),
                ));
            }

            $leaderUserId = (string) $data['leader_user_id'];
            $tenureId = ! empty($data['leader_tenure_id']) ? (string) $data['leader_tenure_id'] : null;

            if (! $tenureId && Schema::hasTable('finance_leader_tenures')) {
                $tenureId = FinanceLeaderTenure::query()
                    ->where('user_id', $leaderUserId)
                    ->where('is_active', true)
                    ->value('id');
            }

            // Lock the requested amount
            $wallet->locked_balance = round((float) $wallet->locked_balance + $amount, 2);
            $wallet->save();

            return FinanceWithdrawalRequest::query()->create([
                'wallet_id' => $wallet->id,
                'leader_user_id' => $leaderUserId,
                'leader_tenure_id' => $tenureId ? (string) $tenureId : null,
                'amount' => $amount,
                'purpose' => (string) $data['purpose'],
                'notes' => isset($data['notes']) ? (string) $data['notes'] : null,
                'payout_method' => isset($data['payout_method']) ? (string) $data['payout_method'] : 'bank_transfer',
                'status' => 'pending',
            ]);
        });
    }

    /**
     * Approve & pay a withdrawal request: deducts from wallet and creates an immutable DEBIT entry in finance_ledger_transactions.
     *
     * @param  array<string, mixed>  $data
     */
    public function approveAndPayWithdrawal(string $withdrawalId, array $data = [], ?string $adminId = null): FinanceWithdrawalRequest
    {
        return DB::transaction(function () use ($withdrawalId, $data, $adminId): FinanceWithdrawalRequest {
            /** @var FinanceWithdrawalRequest $withdrawal */
            $withdrawal = FinanceWithdrawalRequest::query()->lockForUpdate()->findOrFail($withdrawalId);

            if ($withdrawal->status === 'paid') {
                return $withdrawal->load(['wallet', 'ledgerTransaction']);
            }
            if (in_array($withdrawal->status, ['rejected', 'cancelled'], true)) {
                throw new RuntimeException("Cannot approve withdrawal in '{$withdrawal->status}' status.");
            }

            /** @var FinanceWallet $wallet */
            $wallet = FinanceWallet::query()->lockForUpdate()->findOrFail($withdrawal->wallet_id);
            $debitAmount = (float) $withdrawal->amount;
            $openingBalance = (float) $wallet->current_balance;

            if ($debitAmount > $openingBalance) {
                throw new RuntimeException('Wallet current balance is lower than withdrawal amount.');
            }

            $closingBalance = round($openingBalance - $debitAmount, 2);
            $wallet->current_balance = $closingBalance;
            $wallet->total_debited = round((float) $wallet->total_debited + $debitAmount, 2);
            $wallet->locked_balance = max(0.0, round((float) $wallet->locked_balance - $debitAmount, 2));
            $wallet->debit_count = (int) $wallet->debit_count + 1;
            $wallet->save();

            $utr = isset($data['payout_reference_utr']) ? (string) $data['payout_reference_utr'] : null;
            $narration = sprintf(
                'Debit ₹%s for Withdrawal %s (%s)%s',
                number_format($debitAmount, 2),
                $withdrawal->request_number,
                $withdrawal->purpose,
                $utr ? " [UTR: {$utr}]" : '',
            );

            $debitTxn = FinanceLedgerTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'transaction_type' => 'DEBIT',
                'entry_category' => 'WITHDRAWAL_PAYOUT',
                'leader_user_id' => $withdrawal->leader_user_id,
                'leader_tenure_id' => $withdrawal->leader_tenure_id,
                'scope_type' => $wallet->scope_type,
                'scope_id' => $wallet->scope_id,
                'base_amount' => $debitAmount,
                'amount' => $debitAmount,
                'opening_balance' => $openingBalance,
                'closing_balance' => $closingBalance,
                'status' => 'posted',
                'narration' => $narration,
                'metadata' => [
                    'withdrawal_request_id' => $withdrawal->id,
                    'request_number' => $withdrawal->request_number,
                    'purpose' => $withdrawal->purpose,
                    'payout_method' => $data['payout_method'] ?? $withdrawal->payout_method,
                    'payout_reference_utr' => $utr,
                ],
                'created_by_admin_id' => $adminId,
            ]);

            $withdrawal->status = 'paid';
            $withdrawal->payout_method = isset($data['payout_method']) ? (string) $data['payout_method'] : $withdrawal->payout_method;
            $withdrawal->payout_reference_utr = $utr;
            $withdrawal->ledger_transaction_id = $debitTxn->id;
            $withdrawal->approved_by_admin_id = $adminId;
            $withdrawal->approved_at = now();
            $withdrawal->paid_at = now();
            $withdrawal->save();

            return $withdrawal->load(['wallet', 'ledgerTransaction']);
        });
    }

    /**
     * Reject a pending withdrawal request and unlock the wallet funds.
     */
    public function rejectWithdrawal(string $withdrawalId, string $reason, ?string $adminId = null): FinanceWithdrawalRequest
    {
        return DB::transaction(function () use ($withdrawalId, $reason, $adminId): FinanceWithdrawalRequest {
            /** @var FinanceWithdrawalRequest $withdrawal */
            $withdrawal = FinanceWithdrawalRequest::query()->lockForUpdate()->findOrFail($withdrawalId);

            if ($withdrawal->status !== 'pending') {
                throw new RuntimeException("Only pending withdrawals can be rejected (current: {$withdrawal->status}).");
            }

            /** @var FinanceWallet $wallet */
            $wallet = FinanceWallet::query()->lockForUpdate()->findOrFail($withdrawal->wallet_id);
            $wallet->locked_balance = max(0.0, round((float) $wallet->locked_balance - (float) $withdrawal->amount, 2));
            $wallet->save();

            $withdrawal->status = 'rejected';
            $withdrawal->rejection_reason = $reason;
            $withdrawal->approved_by_admin_id = $adminId;
            $withdrawal->approved_at = now();
            $withdrawal->save();

            return $withdrawal->load('wallet');
        });
    }

    /**
     * Create or update a Commission Rule for a Role + Condition.
     *
     * @param  array<string, mixed>  $data
     */
    public function upsertCommissionRule(array $data, ?string $adminId = null, ?string $ipAddress = null): FinanceCommissionRule
    {
        $roleCode = $this->tenureService->normalizeRoleCode((string) $data['role_code']);
        $channelKey = strtoupper(trim((string) $data['channel_key']));
        $allocationTarget = (string) ($data['allocation_target'] ?? match ($roleCode) {
            'DED' => 'district_budget',
            'ID' => 'industry_budget',
            default => 'circle_budget',
        });

        $channel = FinanceAcquisitionChannel::query()->where('channel_key', $channelKey)->firstOrFail();

        $baseAmount = isset($data['base_amount_inr']) ? (float) $data['base_amount_inr'] : self::DEFAULT_BASE_AMOUNT_INR;
        $percentage = (float) $data['share_percentage'];
        $payoutAmount = isset($data['payout_amount'])
            ? (float) $data['payout_amount']
            : round(($baseAmount * $percentage) / 100.0, 2);

        $existing = FinanceCommissionRule::query()
            ->where('role_code', $roleCode)
            ->where('channel_key', $channelKey)
            ->where('allocation_target', $allocationTarget)
            ->first();

        $beforeSnapshot = $existing ? $existing->toArray() : null;

        $roleName = (string) ($data['role_name'] ?? match ($roleCode) {
            'CD' => 'Circle Director',
            'CF' => 'Circle Founder',
            'DED' => 'District Executive Director',
            'ID' => 'Industry Director',
            'CHAIR' => 'Circle Chair',
            default => $roleCode,
        });

        $payload = [
            'role_id' => $data['role_id'] ?? $existing?->role_id,
            'role_code' => $roleCode,
            'role_name' => $roleName,
            'channel_id' => $channel->id,
            'category_code' => (string) ($data['category_code'] ?? $channel->category_code),
            'channel_key' => $channelKey,
            'allocation_target' => $allocationTarget,
            'base_amount_inr' => $baseAmount,
            'share_percentage' => $percentage,
            'payout_amount' => $payoutAmount,
            'conditions_list' => $data['conditions_list'] ?? $existing?->conditions_list ?? $channel->conditions_list,
            'responsibilities_list' => $data['responsibilities_list'] ?? $existing?->responsibilities_list ?? $channel->responsibilities_list,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            'updated_by_admin_id' => $adminId,
        ];

        if ($existing) {
            $existing->fill($payload)->save();
            $rule = $existing->fresh() ?? $existing;
            $action = 'finance.commission_rule.updated';
        } else {
            $payload['effective_from'] = now();
            $rule = FinanceCommissionRule::query()->create($payload);
            $action = 'finance.commission_rule.created';
        }

        FinancePolicyAuditLog::query()->create([
            'admin_user_id' => $adminId,
            'action' => $action,
            'target_table' => 'finance_commission_rules',
            'target_id' => $rule->id,
            'before_snapshot' => $beforeSnapshot,
            'after_snapshot' => $rule->toArray(),
            'ip_address' => $ipAddress,
        ]);

        return $rule;
    }

    /**
     * Get global finance overview KPIs, wallet totals, and condition breakdown.
     *
     * @return array<string, mixed>
     */
    public function getOverview(): array
    {
        $totalBudgetBalance = (float) FinanceWallet::query()->sum('current_balance');
        $totalCredited = (float) FinanceWallet::query()->sum('total_credited');
        $totalDebited = (float) FinanceWallet::query()->sum('total_debited');
        $totalLocked = (float) FinanceWallet::query()->sum('locked_balance');

        $byWalletType = FinanceWallet::query()
            ->selectRaw('wallet_type, COUNT(*) as wallets_count, SUM(current_balance) as current_balance, SUM(total_credited) as total_credited, SUM(total_debited) as total_debited')
            ->groupBy('wallet_type')
            ->get();

        $byChannel = FinanceLedgerTransaction::query()
            ->where('transaction_type', 'CREDIT')
            ->where('status', 'posted')
            ->selectRaw('category_code, channel_code, COUNT(*) as transactions_count, SUM(amount) as total_amount')
            ->groupBy('category_code', 'channel_code')
            ->orderBy('category_code')
            ->get();

        $activeTenuresCount = FinanceLeaderTenure::query()->where('is_active', true)->count();
        $historicalTenuresCount = FinanceLeaderTenure::query()->where('is_active', false)->count();

        return [
            'base_subscription_fee_inr' => self::DEFAULT_BASE_AMOUNT_INR,
            'summary' => [
                'total_available_balance_inr' => round($totalBudgetBalance, 2),
                'total_credited_inr' => round($totalCredited, 2),
                'total_withdrawn_inr' => round($totalDebited, 2),
                'total_locked_pending_withdrawal_inr' => round($totalLocked, 2),
                'active_leader_tenures_count' => $activeTenuresCount,
                'historical_leader_tenures_count' => $historicalTenuresCount,
            ],
            'wallets_by_type' => $byWalletType,
            'credits_by_channel' => $byChannel,
        ];
    }

    /**
     * Internal helper to compute allocations across roles for a peer payment.
     *
     * @return array<int, array<string, mixed>>
     */
    private function computeAllocations(
        User $member,
        Circle $circle,
        float $baseAmount,
        bool $isRenewal,
        ?string $explicitIntroducerId,
        ?string $overrideChannelKey,
        ?string $roleFilter,
        Carbon $atDate,
    ): array {
        $roleCodesQuery = FinanceCommissionRule::query()
            ->where('is_active', true)
            ->select('role_code')
            ->distinct();

        if ($roleFilter !== null) {
            $roleCodesQuery->where('role_code', $roleFilter);
        }

        $roleCodes = $roleCodesQuery->pluck('role_code')->all();
        $introducerId = $explicitIntroducerId ?: ($member->introduced_by ?: ($member->referred_by_user_id ?: null));

        // Resolve tenures for all candidate roles first
        $resolvedTenures = [];
        $hasAnyAssignedLeader = false;
        foreach ($roleCodes as $rCode) {
            $roleCode = (string) $rCode;
            $tenure = $this->tenureService->resolveTenureAtDate($roleCode, $circle, $atDate);
            $resolvedTenures[$roleCode] = $tenure;
            if ($tenure !== null && ! empty($tenure->user_id)) {
                $hasAnyAssignedLeader = true;
            }
        }

        $allocations = [];

        foreach ($roleCodes as $rCode) {
            $roleCode = (string) $rCode;
            $tenure = $resolvedTenures[$roleCode] ?? null;
            $leaderUserId = $tenure?->user_id;

            // When no explicit role_code filter is passed and the circle has assigned leaders,
            // only credit roles that actually have an assigned leader tenure on this circle/scope.
            if ($roleFilter === null && $hasAnyAssignedLeader && empty($leaderUserId)) {
                continue;
            }

            $channel = $this->detectAcquisitionChannel(
                member: $member,
                circle: $circle,
                roleLeaderUserId: $leaderUserId ? (string) $leaderUserId : null,
                explicitIntroducerUserId: $introducerId ? (string) $introducerId : null,
                isRenewal: $isRenewal,
                overrideChannelKey: $overrideChannelKey,
            );

            $rule = FinanceCommissionRule::query()
                ->where('role_code', $roleCode)
                ->where('channel_key', $channel->channel_key)
                ->where('is_active', true)
                ->first();

            if (! $rule || (float) $rule->share_percentage <= 0) {
                continue;
            }

            $percentage = (float) $rule->share_percentage;
            // Scale payout proportionally if baseAmount differs from rule's base_amount_inr, otherwise use exact payout_amount
            $payoutAmount = abs($baseAmount - (float) $rule->base_amount_inr) < 0.01
                ? (float) $rule->payout_amount
                : round(($baseAmount * $percentage) / 100.0, 2);

            $leaderName = null;
            if ($leaderUserId) {
                $leaderUser = User::query()->select(['id', 'first_name', 'last_name', 'display_name'])->find($leaderUserId);
                if ($leaderUser) {
                    $leaderName = trim(($leaderUser->first_name ?? '').' '.($leaderUser->last_name ?? '')) ?: $leaderUser->display_name;
                }
            }

            [$scopeType, $scopeId] = $this->resolveScopeForAllocationTarget($rule->allocation_target, $circle, $leaderUserId ? (string) $leaderUserId : null);

            $allocations[] = [
                'rule_id' => $rule->id,
                'role_id' => $rule->role_id,
                'role_code' => $roleCode,
                'role_name' => $rule->role_name,
                'channel_id' => $channel->id,
                'category_code' => $rule->category_code,
                'channel_key' => $channel->channel_key,
                'channel_name' => $channel->name,
                'allocation_target' => $rule->allocation_target,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'leader_user_id' => $leaderUserId,
                'leader_name' => $leaderName,
                'leader_tenure_id' => $tenure?->id,
                'tenure_start_at' => $tenure?->tenure_start_at?->toIso8601String(),
                'introducer_user_id' => $introducerId,
                'base_amount_inr' => round($baseAmount, 2),
                'percentage' => $percentage,
                'payout_amount' => $payoutAmount,
                'conditions' => $rule->conditions_list ?? [],
                'responsibilities' => $rule->responsibilities_list ?? [],
            ];
        }

        return $allocations;
    }

    /**
     * Determine scope_type and scope_id based on allocation_target.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveScopeForAllocationTarget(string $allocationTarget, Circle $circle, ?string $leaderUserId): array
    {
        if ($allocationTarget === 'leader_wallet' && $leaderUserId) {
            return ['user', $leaderUserId];
        }

        if ($allocationTarget === 'district_budget') {
            $districtId = null;
            if (Schema::hasColumn('circles', 'district_id') && ! empty($circle->getAttribute('district_id'))) {
                $districtId = (string) $circle->getAttribute('district_id');
            } elseif ($leaderUserId && Schema::hasTable('admin_ded_districts')) {
                $districtId = DB::table('admin_ded_districts')->where('user_id', $leaderUserId)->value('district_id');
            }

            return ['district', $districtId ? (string) $districtId : (string) $circle->id];
        }

        if ($allocationTarget === 'industry_budget') {
            $industryId = null;
            if (Schema::hasColumn('circles', 'template_id') && ! empty($circle->template_id)) {
                $industryId = (string) $circle->template_id;
            } elseif (Schema::hasTable('circle_category_mappings')) {
                $catId = DB::table('circle_category_mappings')->where('circle_id', $circle->id)->value('category_id');
                if ($catId) {
                    $industryId = (string) $catId;
                }
            }

            return ['industry', $industryId ?: (string) $circle->id];
        }

        return ['circle', (string) $circle->id];
    }

    private function resolveOrCreateWalletForAllocation(array $alloc, Circle $circle): FinanceWallet
    {
        $walletType = (string) $alloc['allocation_target'];
        $scopeType = (string) $alloc['scope_type'];
        $scopeId = (string) $alloc['scope_id'];

        $wallet = FinanceWallet::query()
            ->where('wallet_type', $walletType)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->first();

        if ($wallet) {
            return $wallet;
        }

        $walletName = match ($walletType) {
            'circle_budget' => $circle->name.' — Circle Budget',
            'district_budget' => $circle->name.' — District (DED) Budget',
            'industry_budget' => $circle->name.' — Industry (ID) Budget',
            'leader_wallet' => ($alloc['leader_name'] ?? 'Leader').' — Commission Wallet',
            default => $circle->name.' — Finance Wallet',
        };

        return FinanceWallet::query()->create([
            'wallet_type' => $walletType,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'wallet_name' => $walletName,
            'current_custodian_user_id' => $alloc['leader_user_id'] ?? null,
            'currency' => 'INR',
            'current_balance' => 0.00,
            'total_credited' => 0.00,
            'total_debited' => 0.00,
            'locked_balance' => 0.00,
            'credit_count' => 0,
            'debit_count' => 0,
            'status' => 'active',
        ]);
    }

    private function hasPriorPaidRenewalHistory(User $member, Circle $circle): bool
    {
        if (Schema::hasTable('finance_ledger_transactions')) {
            $priorCommissionExists = FinanceLedgerTransaction::query()
                ->where('member_user_id', $member->id)
                ->where('scope_id', (string) $circle->id)
                ->where('transaction_type', 'CREDIT')
                ->where('status', 'posted')
                ->exists();

            if ($priorCommissionExists) {
                return true;
            }
        }

        if (Schema::hasTable('payments')) {
            $paidCount = Payment::query()
                ->where('user_id', $member->id)
                ->whereIn(DB::raw('LOWER(status)'), ['paid', 'success', 'completed'])
                ->count();

            if ($paidCount > 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the Circle for a peer subscription payment:
     * 1. Explicit circle_id if provided
     * 2. Member's active_circle_id or circle_members record
     * 3. Introducer's active leader tenure circle (e.g. when a peer joins as 'Only Unity Peer' via a CF/CD's referral code)
     */
    public function resolveCircleForMember(User $member, ?string $explicitCircleId = null, ?string $explicitIntroducerId = null): Circle
    {
        if (! empty($explicitCircleId)) {
            return Circle::query()->findOrFail($explicitCircleId);
        }

        if (! empty($member->active_circle_id)) {
            $circle = Circle::query()->find((string) $member->active_circle_id);
            if ($circle) {
                return $circle;
            }
        }

        if (Schema::hasTable('circle_members')) {
            $memberCircleId = CircleMember::query()
                ->where('user_id', $member->id)
                ->whereNull('deleted_at')
                ->latest('created_at')
                ->value('circle_id');

            if ($memberCircleId) {
                $circle = Circle::query()->find((string) $memberCircleId);
                if ($circle) {
                    return $circle;
                }
            }
        }

        $introducerId = $explicitIntroducerId ?: ($member->introduced_by ?: ($member->referred_by_user_id ?: null));
        if (! empty($introducerId)) {
            if (Schema::hasTable('finance_leader_tenures')) {
                $tenureScopeId = FinanceLeaderTenure::query()
                    ->where('user_id', (string) $introducerId)
                    ->whereRaw('LOWER(scope_type) = ?', ['circle'])
                    ->where('is_active', true)
                    ->orderByDesc('tenure_start_at')
                    ->value('scope_id');

                if ($tenureScopeId) {
                    $circle = Circle::query()->find((string) $tenureScopeId);
                    if ($circle) {
                        return $circle;
                    }
                }
            }

            if (Schema::hasTable('circle_members')) {
                $introducerCircleId = CircleMember::query()
                    ->where('user_id', (string) $introducerId)
                    ->whereNull('deleted_at')
                    ->latest('created_at')
                    ->value('circle_id');

                if ($introducerCircleId) {
                    $circle = Circle::query()->find((string) $introducerCircleId);
                    if ($circle) {
                        return $circle;
                    }
                }
            }
        }

        throw new RuntimeException('Unable to resolve a Circle for this peer or their introducer. Please provide circle_id or assign the leader/peer to a Circle.');
    }
}
