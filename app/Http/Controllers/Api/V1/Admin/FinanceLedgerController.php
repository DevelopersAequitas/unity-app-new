<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\Finance\ProcessSubscriptionCommissionRequest;
use App\Http\Requests\Admin\Finance\ProcessWithdrawalActionRequest;
use App\Http\Requests\Admin\Finance\RecordLeaderTenureRequest;
use App\Http\Requests\Admin\Finance\StoreAcquisitionChannelRequest;
use App\Http\Requests\Admin\Finance\StoreWithdrawalRequest;
use App\Http\Requests\Admin\Finance\UpdateAcquisitionChannelRequest;
use App\Http\Requests\Admin\Finance\UpsertCommissionRuleRequest;
use App\Models\Finance\FinanceAcquisitionChannel;
use App\Models\Finance\FinanceCommissionRule;
use App\Models\Finance\FinanceLedgerTransaction;
use App\Models\Finance\FinancePolicyAuditLog;
use App\Models\Finance\FinanceWallet;
use App\Models\Finance\FinanceWithdrawalRequest;
use App\Services\Finance\FinanceCommissionEngineService;
use App\Services\Finance\LeaderTenureService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class FinanceLedgerController extends BaseApiController
{
    public function __construct(
        private readonly FinanceCommissionEngineService $engine,
        private readonly LeaderTenureService $tenureService,
    ) {}

    /**
     * GET /api/v1/admin/finance/overview
     */
    public function overview(): JsonResponse
    {
        return $this->success($this->engine->getOverview(), 'Finance overview retrieved successfully.');
    }

    /**
     * GET /api/v1/admin/finance/policy/{roleCode?}
     * Returns the standardized Executive Policy JSON specification (e.g. for CD, CF, DED, ID).
     */
    public function policySpecification(string $roleCode = 'CD'): JsonResponse
    {
        return response()->json($this->engine->getRolePolicySpecification($roleCode));
    }

    /**
     * GET /api/v1/admin/finance/channels
     */
    public function listChannels(): JsonResponse
    {
        $channels = FinanceAcquisitionChannel::query()
            ->orderBy('priority_order')
            ->orderBy('category_code')
            ->get();

        return $this->success($channels, 'Acquisition channels retrieved successfully.');
    }

    /**
     * POST /api/v1/admin/finance/channels
     */
    public function storeChannel(StoreAcquisitionChannelRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['channel_key'] = strtoupper(trim((string) $validated['channel_key']));
        $validated['category_code'] = strtoupper(trim((string) $validated['category_code']));

        $channel = FinanceAcquisitionChannel::query()->create($validated);

        FinancePolicyAuditLog::query()->create([
            'admin_user_id' => $request->user()?->id ? (string) $request->user()->id : null,
            'action' => 'finance.channel.created',
            'target_table' => 'finance_acquisition_channels',
            'target_id' => $channel->id,
            'before_snapshot' => null,
            'after_snapshot' => $channel->toArray(),
            'ip_address' => $request->ip(),
        ]);

        return $this->success($channel, 'Acquisition condition created successfully.', 201);
    }

    /**
     * PUT /api/v1/admin/finance/channels/{id}
     */
    public function updateChannel(UpdateAcquisitionChannelRequest $request, string $id): JsonResponse
    {
        $channel = FinanceAcquisitionChannel::query()->findOrFail($id);
        $before = $channel->toArray();

        $channel->fill($request->validated())->save();

        FinancePolicyAuditLog::query()->create([
            'admin_user_id' => $request->user()?->id ? (string) $request->user()->id : null,
            'action' => 'finance.channel.updated',
            'target_table' => 'finance_acquisition_channels',
            'target_id' => $channel->id,
            'before_snapshot' => $before,
            'after_snapshot' => $channel->fresh()?->toArray(),
            'ip_address' => $request->ip(),
        ]);

        return $this->success($channel->fresh(), 'Acquisition condition updated successfully.');
    }

    /**
     * GET /api/v1/admin/finance/rules
     */
    public function listRules(Request $request): JsonResponse
    {
        $query = FinanceCommissionRule::query()
            ->with('channel')
            ->orderBy('role_code')
            ->orderBy('category_code');

        if ($request->filled('role_code')) {
            $roleCode = $this->tenureService->normalizeRoleCode((string) $request->query('role_code'));
            $query->where('role_code', $roleCode);
        }

        return $this->success($query->get(), 'Commission rules retrieved successfully.');
    }

    /**
     * POST /api/v1/admin/finance/rules
     */
    public function upsertRule(UpsertCommissionRuleRequest $request): JsonResponse
    {
        $adminId = $request->user()?->id ? (string) $request->user()->id : null;
        $rule = $this->engine->upsertCommissionRule($request->validated(), $adminId, $request->ip());

        return $this->success($rule, 'Commission rule saved successfully.');
    }

    /**
     * POST /api/v1/admin/finance/simulate
     */
    public function simulate(ProcessSubscriptionCommissionRequest $request): JsonResponse
    {
        $result = $this->engine->simulateAllocation($request->validated());

        return $this->success($result, 'Commission simulation calculated successfully.');
    }

    /**
     * POST /api/v1/admin/finance/process-payment
     */
    public function processPayment(ProcessSubscriptionCommissionRequest $request): JsonResponse
    {
        $adminId = $request->user()?->id ? (string) $request->user()->id : null;
        $result = $this->engine->processSubscriptionPayment($request->validated(), $adminId);

        return $this->success($result, 'Subscription payment processed and budgets credited successfully.', 201);
    }

    /**
     * GET /api/v1/admin/finance/wallets
     */
    public function listWallets(Request $request): JsonResponse
    {
        $query = FinanceWallet::query()
            ->with('custodian:id,first_name,last_name,display_name,email')
            ->orderByDesc('current_balance');

        if ($request->filled('wallet_type')) {
            $query->where('wallet_type', (string) $request->query('wallet_type'));
        }
        if ($request->filled('scope_id')) {
            $query->where('scope_id', (string) $request->query('scope_id'));
        }

        return $this->success($query->get(), 'Finance wallets retrieved successfully.');
    }

    /**
     * GET /api/v1/admin/finance/wallets/{id}/passbook
     */
    public function walletPassbook(Request $request, string $id): JsonResponse
    {
        $wallet = FinanceWallet::query()
            ->with('custodian:id,first_name,last_name,display_name,email')
            ->findOrFail($id);

        $transactionsQuery = FinanceLedgerTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->with([
                'member:id,first_name,last_name,display_name,email',
                'introducer:id,first_name,last_name,display_name,email',
                'leader:id,first_name,last_name,display_name,email',
                'tenure:id,role_code,scope_name_snapshot,tenure_start_at,tenure_end_at,is_active',
            ])
            ->orderByDesc('created_at');

        if ($request->filled('transaction_type')) {
            $transactionsQuery->where('transaction_type', strtoupper((string) $request->query('transaction_type')));
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 50)));

        return $this->success([
            'wallet' => $wallet,
            'passbook' => $transactionsQuery->paginate($perPage),
        ], 'Wallet passbook statement retrieved successfully.');
    }

    /**
     * GET /api/v1/admin/finance/ledger
     */
    public function listLedger(Request $request): JsonResponse
    {
        $query = FinanceLedgerTransaction::query()
            ->with([
                'wallet:id,account_number,wallet_type,wallet_name,scope_type,scope_id',
                'member:id,first_name,last_name,display_name,email',
                'introducer:id,first_name,last_name,display_name,email',
                'leader:id,first_name,last_name,display_name,email',
                'tenure:id,role_code,scope_name_snapshot,tenure_start_at,tenure_end_at,is_active',
            ])
            ->orderByDesc('created_at');

        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', strtoupper((string) $request->query('transaction_type')));
        }
        if ($request->filled('role_code')) {
            $query->where('role_code', $this->tenureService->normalizeRoleCode((string) $request->query('role_code')));
        }
        if ($request->filled('channel_code')) {
            $query->where('channel_code', strtoupper((string) $request->query('channel_code')));
        }
        if ($request->filled('leader_user_id')) {
            $query->where('leader_user_id', (string) $request->query('leader_user_id'));
        }
        if ($request->filled('leader_tenure_id')) {
            $query->where('leader_tenure_id', (string) $request->query('leader_tenure_id'));
        }
        if ($request->filled('scope_id')) {
            $query->where('scope_id', (string) $request->query('scope_id'));
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 50)));

        return $this->success($query->paginate($perPage), 'Ledger transactions retrieved successfully.');
    }

    /**
     * GET /api/v1/admin/finance/withdrawals
     */
    public function listWithdrawals(Request $request): JsonResponse
    {
        $query = FinanceWithdrawalRequest::query()
            ->with([
                'wallet:id,account_number,wallet_name,wallet_type,current_balance',
                'leader:id,first_name,last_name,display_name,email',
                'tenure:id,role_code,scope_name_snapshot,tenure_start_at,tenure_end_at',
                'ledgerTransaction',
            ])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', strtolower((string) $request->query('status')));
        }
        if ($request->filled('wallet_id')) {
            $query->where('wallet_id', (string) $request->query('wallet_id'));
        }

        return $this->success($query->get(), 'Withdrawal requests retrieved successfully.');
    }

    /**
     * POST /api/v1/admin/finance/withdrawals
     */
    public function storeWithdrawal(StoreWithdrawalRequest $request): JsonResponse
    {
        try {
            $withdrawal = $this->engine->createWithdrawalRequest($request->validated());

            return $this->success($withdrawal->load('wallet'), 'Withdrawal request submitted and balance locked.', 201);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/v1/admin/finance/withdrawals/{id}/approve
     */
    public function approveWithdrawal(ProcessWithdrawalActionRequest $request, string $id): JsonResponse
    {
        try {
            $adminId = $request->user()?->id ? (string) $request->user()->id : null;
            $withdrawal = $this->engine->approveAndPayWithdrawal($id, $request->validated(), $adminId);

            return $this->success($withdrawal, 'Withdrawal approved, paid, and debited in ledger.');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/v1/admin/finance/withdrawals/{id}/reject
     */
    public function rejectWithdrawal(ProcessWithdrawalActionRequest $request, string $id): JsonResponse
    {
        try {
            $adminId = $request->user()?->id ? (string) $request->user()->id : null;
            $reason = (string) ($request->validated('rejection_reason') ?: 'Rejected by administrator');
            $withdrawal = $this->engine->rejectWithdrawal($id, $reason, $adminId);

            return $this->success($withdrawal, 'Withdrawal rejected and locked balance released.');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * GET /api/v1/admin/finance/tenures
     */
    public function listTenures(Request $request): JsonResponse
    {
        $tenures = $this->tenureService->listTenuresWithFinancials($request->only([
            'role_code',
            'user_id',
            'scope_id',
            'is_active',
        ]));

        return $this->success($tenures, 'Leader tenures with financial performance retrieved successfully.');
    }

    /**
     * POST /api/v1/admin/finance/tenures
     */
    public function recordTenure(RecordLeaderTenureRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $adminId = $request->user()?->id ? (string) $request->user()->id : null;

        $startAt = ! empty($validated['tenure_start_at']) ? Carbon::parse((string) $validated['tenure_start_at']) : null;
        $expectedEndAt = ! empty($validated['expected_end_at']) ? Carbon::parse((string) $validated['expected_end_at']) : null;

        $tenure = $this->tenureService->assignOrRotateTenure(
            userId: (string) $validated['user_id'],
            roleKeyOrCode: (string) $validated['role_code'],
            scopeType: (string) $validated['scope_type'],
            scopeId: isset($validated['scope_id']) ? (string) $validated['scope_id'] : null,
            assignedByAdminId: $adminId,
            assignmentSource: 'finance_api',
            startAt: $startAt,
            expectedEndAt: $expectedEndAt,
        );

        return $this->success($tenure, 'Leader tenure assigned and previous tenure closed successfully.', 201);
    }
}

