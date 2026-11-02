<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 0. Ensure roles table has base_amount_inr column if roles table exists
        if (Schema::hasTable('roles') && ! Schema::hasColumn('roles', 'base_amount_inr')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->decimal('base_amount_inr', 12, 2)->default(18000.00);
            });
        }

        // 1. finance_acquisition_channels (Conditions Master Table: A, B, C, D, etc.)
        if (! Schema::hasTable('finance_acquisition_channels')) {
            Schema::create('finance_acquisition_channels', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('category_code', 10)->index();
                $table->string('channel_key', 60)->unique();
                $table->string('name', 150);
                $table->text('description')->nullable();
                $table->json('conditions_list')->nullable();
                $table->json('responsibilities_list')->nullable();
                $table->integer('priority_order')->default(1);
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. finance_commission_rules (Role x Condition Percentage Matrix)
        if (! Schema::hasTable('finance_commission_rules')) {
            Schema::create('finance_commission_rules', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('role_id')->nullable()->index();
                $table->string('role_code', 50)->index();
                $table->string('role_name', 120);
                $table->uuid('channel_id')->index();
                $table->string('category_code', 10)->index();
                $table->string('channel_key', 60)->index();
                $table->string('allocation_target', 40)->default('circle_budget'); // circle_budget, district_budget, industry_budget, leader_wallet
                $table->decimal('base_amount_inr', 12, 2)->default(18000.00);
                $table->decimal('share_percentage', 5, 2)->default(0.00);
                $table->decimal('payout_amount', 12, 2)->default(0.00);
                $table->json('conditions_list')->nullable();
                $table->json('responsibilities_list')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('effective_from')->nullable();
                $table->timestamp('effective_to')->nullable();
                $table->uuid('updated_by_admin_id')->nullable();
                $table->timestamps();

                $table->unique(['role_code', 'channel_key', 'allocation_target'], 'uq_finance_rule_role_channel_target');
            });
        }

        // 3. finance_leader_tenures (6-Month Leader Rotation & Role Tenure Audit Table)
        if (! Schema::hasTable('finance_leader_tenures')) {
            Schema::create('finance_leader_tenures', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->index();
                $table->uuid('admin_user_id')->nullable()->index();
                $table->uuid('role_id')->nullable()->index();
                $table->string('role_key', 60)->index();
                $table->string('role_code', 30)->index();
                $table->string('scope_type', 30)->default('circle')->index(); // circle, district, industry, global
                $table->string('scope_id', 64)->nullable()->index();
                $table->string('scope_name_snapshot', 200)->nullable();
                $table->timestamp('tenure_start_at')->index();
                $table->timestamp('tenure_end_at')->nullable()->index();
                $table->timestamp('expected_end_at')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->uuid('assigned_by_admin_id')->nullable();
                $table->uuid('removed_by_admin_id')->nullable();
                $table->string('assignment_source', 50)->default('system'); // rbac_hierarchy, circle_edit, circle_member, user_edit, api
                $table->string('removal_reason', 255)->nullable();
                $table->timestamps();
            });
        }

        // 4. finance_wallets (Bank-Style Accounts for Circle / District / Industry Budgets & Leader Wallets)
        if (! Schema::hasTable('finance_wallets')) {
            Schema::create('finance_wallets', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('account_number', 50)->unique();
                $table->string('wallet_type', 40)->index(); // circle_budget, district_budget, industry_budget, leader_wallet, global_treasury
                $table->string('scope_type', 30)->index(); // circle, district, industry, user, global
                $table->string('scope_id', 64)->nullable()->index();
                $table->string('wallet_name', 200);
                $table->uuid('current_custodian_user_id')->nullable()->index();
                $table->string('currency', 10)->default('INR');
                $table->decimal('current_balance', 14, 2)->default(0.00);
                $table->decimal('total_credited', 14, 2)->default(0.00);
                $table->decimal('total_debited', 14, 2)->default(0.00);
                $table->decimal('locked_balance', 14, 2)->default(0.00);
                $table->integer('credit_count')->default(0);
                $table->integer('debit_count')->default(0);
                $table->string('status', 20)->default('active'); // active, frozen, closed
                $table->timestamps();

                $table->unique(['wallet_type', 'scope_type', 'scope_id'], 'uq_finance_wallet_scope');
            });
        }

        // 5. finance_ledger_transactions (Immutable Bank Passbook for All Credits & Debits)
        if (! Schema::hasTable('finance_ledger_transactions')) {
            Schema::create('finance_ledger_transactions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('transaction_ref', 60)->unique();
                $table->uuid('wallet_id')->index();
                $table->string('transaction_type', 10)->index(); // CREDIT, DEBIT
                $table->string('entry_category', 40)->index(); // SUBSCRIPTION_COMMISSION, WITHDRAWAL_PAYOUT, MANUAL_ADJUSTMENT, REFUND_REVERSAL
                $table->uuid('source_payment_id')->nullable()->index();
                $table->uuid('member_user_id')->nullable()->index();
                $table->uuid('introducer_user_id')->nullable()->index();
                $table->uuid('leader_user_id')->nullable()->index();
                $table->uuid('leader_tenure_id')->nullable()->index();
                $table->uuid('role_id')->nullable()->index();
                $table->string('role_code', 30)->nullable()->index();
                $table->uuid('channel_id')->nullable()->index();
                $table->string('channel_code', 60)->nullable()->index();
                $table->string('category_code', 10)->nullable();
                $table->uuid('rule_id')->nullable()->index();
                $table->string('scope_type', 30)->nullable()->index();
                $table->string('scope_id', 64)->nullable()->index();
                $table->decimal('base_amount', 12, 2)->default(18000.00);
                $table->decimal('applied_percentage', 5, 2)->nullable();
                $table->decimal('amount', 12, 2);
                $table->decimal('opening_balance', 14, 2);
                $table->decimal('closing_balance', 14, 2);
                $table->string('status', 20)->default('posted')->index(); // posted, pending, reversed
                $table->string('narration', 500);
                $table->json('metadata')->nullable();
                $table->uuid('created_by_admin_id')->nullable();
                $table->timestamp('created_at')->useCurrent()->index();
            });
        }

        // 6. finance_withdrawal_requests (Budget / Commission Withdrawal & Expense Payouts)
        if (! Schema::hasTable('finance_withdrawal_requests')) {
            Schema::create('finance_withdrawal_requests', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('request_number', 50)->unique();
                $table->uuid('wallet_id')->index();
                $table->uuid('leader_user_id')->index();
                $table->uuid('leader_tenure_id')->nullable()->index();
                $table->decimal('amount', 12, 2);
                $table->string('purpose', 255);
                $table->text('notes')->nullable();
                $table->string('status', 20)->default('pending')->index(); // pending, approved, paid, rejected, cancelled
                $table->string('payout_method', 40)->nullable(); // bank_transfer, upi, cheque, expense_reimbursement
                $table->string('payout_reference_utr', 100)->nullable();
                $table->uuid('receipt_file_id')->nullable();
                $table->uuid('ledger_transaction_id')->nullable()->index();
                $table->uuid('approved_by_admin_id')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->string('rejection_reason', 500)->nullable();
                $table->timestamps();
            });
        }

        // 7. finance_policy_audit_logs (Audit Log for Rate, Channel & Manual Ledger Changes)
        if (! Schema::hasTable('finance_policy_audit_logs')) {
            Schema::create('finance_policy_audit_logs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('admin_user_id')->nullable()->index();
                $table->string('action', 80)->index();
                $table->string('target_table', 60)->index();
                $table->uuid('target_id')->index();
                $table->json('before_snapshot')->nullable();
                $table->json('after_snapshot')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        $this->seedDefaultChannelsAndRules();
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_policy_audit_logs');
        Schema::dropIfExists('finance_withdrawal_requests');
        Schema::dropIfExists('finance_ledger_transactions');
        Schema::dropIfExists('finance_wallets');
        Schema::dropIfExists('finance_leader_tenures');
        Schema::dropIfExists('finance_commission_rules');
        Schema::dropIfExists('finance_acquisition_channels');
    }

    private function seedDefaultChannelsAndRules(): void
    {
        $now = now();

        $channels = [
            [
                'category_code' => 'A',
                'channel_key' => 'RENEWAL_EXISTING',
                'name' => 'Renewal of Existing Circle Members',
                'description' => 'Core income engine; rewards long-term stewardship, leadership retention, culture maintenance, and consistent value delivery.',
                'conditions_list' => json_encode(['Member renewal']),
                'responsibilities_list' => json_encode(['Leadership retention', 'Culture maintenance', 'Consistent value delivery']),
                'priority_order' => 1,
                'is_system' => true,
            ],
            [
                'category_code' => 'B',
                'channel_key' => 'DIRECT_EFFORT',
                'name' => 'New Member Joins via Direct Effort (Leader Referral)',
                'description' => 'Leader personally brings a new member and maintains category alignment.',
                'conditions_list' => json_encode(['Direct bring-in by CD', 'Category alignment maintained']),
                'responsibilities_list' => json_encode(['Direct recruitment', 'Category alignment']),
                'priority_order' => 2,
                'is_system' => true,
            ],
            [
                'category_code' => 'C',
                'channel_key' => 'MEMBER_REFERRAL',
                'name' => 'New Member Joins via Existing Circle Member',
                'description' => 'Member joins via referral from an active circle member. Rewards building a referral culture, maintaining engagement, and fostering circle trust.',
                'conditions_list' => json_encode(['Referred by existing Circle member']),
                'responsibilities_list' => json_encode(['Referral culture', 'Engagement maintenance', 'Circle trust']),
                'priority_order' => 3,
                'is_system' => true,
            ],
            [
                'category_code' => 'D',
                'channel_key' => 'TEAM_ASSIGNED',
                'name' => 'New Member Added by Peers Global / District / Regional Team',
                'description' => 'Assigned or added by central, district, or regional teams. Rewards on-ground facilitation, smooth onboarding, and retention responsibility.',
                'conditions_list' => json_encode(['Added by Peers Global / District / Regional Team']),
                'responsibilities_list' => json_encode(['On-ground facilitation', 'Smooth onboarding', 'Retention responsibility']),
                'priority_order' => 4,
                'is_system' => true,
            ],
            [
                'category_code' => 'E',
                'channel_key' => 'DIRECT_MARKETING',
                'name' => 'New Member Joins via Marketing / Direct in App',
                'description' => 'Member joins directly through the mobile app or marketing campaigns without an individual peer referral.',
                'conditions_list' => json_encode(['Joined directly via App or Marketing campaign']),
                'responsibilities_list' => json_encode(['On-ground facilitation', 'Smooth onboarding', 'Retention responsibility']),
                'priority_order' => 5,
                'is_system' => true,
            ],
        ];

        $channelIdsByKey = [];
        foreach ($channels as $ch) {
            $existing = DB::table('finance_acquisition_channels')
                ->where('channel_key', $ch['channel_key'])
                ->first();

            if ($existing) {
                $channelIdsByKey[$ch['channel_key']] = (string) $existing->id;
            } else {
                $id = (string) Str::uuid();
                DB::table('finance_acquisition_channels')->insert(array_merge($ch, [
                    'id' => $id,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
                $channelIdsByKey[$ch['channel_key']] = $id;
            }
        }

        // Resolve role IDs if roles table has matching keys
        $resolveRoleId = function (array $candidateKeys): ?string {
            if (! Schema::hasTable('roles')) {
                return null;
            }
            $id = DB::table('roles')->whereIn('key', $candidateKeys)->value('id');

            return $id ? (string) $id : null;
        };

        $cdRoleId = $resolveRoleId(['circle_director', 'cd', 'director']);
        $cfRoleId = $resolveRoleId(['circle_founder', 'cf', 'founder']);
        $dedRoleId = $resolveRoleId(['ded', 'district_exec_director']);
        $idRoleId = $resolveRoleId(['industry_director', 'id']);

        $defaultRules = [
            // Circle Director (CD) — Exact Policy Matrix (25%, 25%, 20%, 10% on ₹18,000)
            [
                'role_id' => $cdRoleId,
                'role_code' => 'CD',
                'role_name' => 'Circle Director',
                'channel_key' => 'RENEWAL_EXISTING',
                'category_code' => 'A',
                'allocation_target' => 'circle_budget',
                'base_amount_inr' => 18000.00,
                'share_percentage' => 25.00,
                'payout_amount' => 4500.00,
                'conditions_list' => json_encode(['Member renewal']),
                'responsibilities_list' => json_encode(['Leadership retention', 'Culture maintenance', 'Consistent value delivery']),
            ],
            [
                'role_id' => $cdRoleId,
                'role_code' => 'CD',
                'role_name' => 'Circle Director',
                'channel_key' => 'DIRECT_EFFORT',
                'category_code' => 'B',
                'allocation_target' => 'circle_budget',
                'base_amount_inr' => 18000.00,
                'share_percentage' => 25.00,
                'payout_amount' => 4500.00,
                'conditions_list' => json_encode(['Direct bring-in by CD', 'Category alignment maintained']),
                'responsibilities_list' => json_encode(['Direct recruitment', 'Category alignment']),
            ],
            [
                'role_id' => $cdRoleId,
                'role_code' => 'CD',
                'role_name' => 'Circle Director',
                'channel_key' => 'MEMBER_REFERRAL',
                'category_code' => 'C',
                'allocation_target' => 'circle_budget',
                'base_amount_inr' => 18000.00,
                'share_percentage' => 20.00,
                'payout_amount' => 3600.00,
                'conditions_list' => json_encode(['Referred by existing Circle member']),
                'responsibilities_list' => json_encode(['Referral culture', 'Engagement maintenance', 'Circle trust']),
            ],
            [
                'role_id' => $cdRoleId,
                'role_code' => 'CD',
                'role_name' => 'Circle Director',
                'channel_key' => 'TEAM_ASSIGNED',
                'category_code' => 'D',
                'allocation_target' => 'circle_budget',
                'base_amount_inr' => 18000.00,
                'share_percentage' => 10.00,
                'payout_amount' => 1800.00,
                'conditions_list' => json_encode(['Added by Peers Global / District / Regional Team']),
                'responsibilities_list' => json_encode(['On-ground facilitation', 'Smooth onboarding', 'Retention responsibility']),
            ],
            [
                'role_id' => $cdRoleId,
                'role_code' => 'CD',
                'role_name' => 'Circle Director',
                'channel_key' => 'DIRECT_MARKETING',
                'category_code' => 'E',
                'allocation_target' => 'circle_budget',
                'base_amount_inr' => 18000.00,
                'share_percentage' => 10.00,
                'payout_amount' => 1800.00,
                'conditions_list' => json_encode(['Joined directly via App or Marketing']),
                'responsibilities_list' => json_encode(['On-ground facilitation', 'Smooth onboarding', 'Retention responsibility']),
            ],

            // Circle Founder (CF) Default Rules
            [
                'role_id' => $cfRoleId,
                'role_code' => 'CF',
                'role_name' => 'Circle Founder',
                'channel_key' => 'RENEWAL_EXISTING',
                'category_code' => 'A',
                'allocation_target' => 'circle_budget',
                'base_amount_inr' => 18000.00,
                'share_percentage' => 10.00,
                'payout_amount' => 1800.00,
                'conditions_list' => json_encode(['Member renewal']),
                'responsibilities_list' => json_encode(['Founding vision', 'Circle mentorship']),
            ],
            [
                'role_id' => $cfRoleId,
                'role_code' => 'CF',
                'role_name' => 'Circle Founder',
                'channel_key' => 'DIRECT_EFFORT',
                'category_code' => 'B',
                'allocation_target' => 'circle_budget',
                'base_amount_inr' => 18000.00,
                'share_percentage' => 15.00,
                'payout_amount' => 2700.00,
                'conditions_list' => json_encode(['Direct bring-in by CF']),
                'responsibilities_list' => json_encode(['Direct recruitment', 'Founding stewardship']),
            ],
            [
                'role_id' => $cfRoleId,
                'role_code' => 'CF',
                'role_name' => 'Circle Founder',
                'channel_key' => 'MEMBER_REFERRAL',
                'category_code' => 'C',
                'allocation_target' => 'circle_budget',
                'base_amount_inr' => 18000.00,
                'share_percentage' => 10.00,
                'payout_amount' => 1800.00,
                'conditions_list' => json_encode(['Referred by existing Circle member']),
                'responsibilities_list' => json_encode(['Circle culture support']),
            ],
            [
                'role_id' => $cfRoleId,
                'role_code' => 'CF',
                'role_name' => 'Circle Founder',
                'channel_key' => 'TEAM_ASSIGNED',
                'category_code' => 'D',
                'allocation_target' => 'circle_budget',
                'base_amount_inr' => 18000.00,
                'share_percentage' => 5.00,
                'payout_amount' => 900.00,
                'conditions_list' => json_encode(['Added by Peers Global / District / Regional Team']),
                'responsibilities_list' => json_encode(['Onboarding support']),
            ],

            // District Executive Director (DED) Default Rules -> District Budget
            [
                'role_id' => $dedRoleId,
                'role_code' => 'DED',
                'role_name' => 'District Executive Director',
                'channel_key' => 'RENEWAL_EXISTING',
                'category_code' => 'A',
                'allocation_target' => 'district_budget',
                'base_amount_inr' => 18000.00,
                'share_percentage' => 10.00,
                'payout_amount' => 1800.00,
                'conditions_list' => json_encode(['Member renewal in District Circle']),
                'responsibilities_list' => json_encode(['District retention oversight']),
            ],
            [
                'role_id' => $dedRoleId,
                'role_code' => 'DED',
                'role_name' => 'District Executive Director',
                'channel_key' => 'DIRECT_EFFORT',
                'category_code' => 'B',
                'allocation_target' => 'district_budget',
                'base_amount_inr' => 18000.00,
                'share_percentage' => 15.00,
                'payout_amount' => 2700.00,
                'conditions_list' => json_encode(['Direct bring-in by DED']),
                'responsibilities_list' => json_encode(['District expansion', 'Direct recruitment']),
            ],
            [
                'role_id' => $dedRoleId,
                'role_code' => 'DED',
                'role_name' => 'District Executive Director',
                'channel_key' => 'MEMBER_REFERRAL',
                'category_code' => 'C',
                'allocation_target' => 'district_budget',
                'base_amount_inr' => 18000.00,
                'share_percentage' => 5.00,
                'payout_amount' => 900.00,
                'conditions_list' => json_encode(['Referred by Circle member in District']),
                'responsibilities_list' => json_encode(['District governance']),
            ],
            [
                'role_id' => $dedRoleId,
                'role_code' => 'DED',
                'role_name' => 'District Executive Director',
                'channel_key' => 'TEAM_ASSIGNED',
                'category_code' => 'D',
                'allocation_target' => 'district_budget',
                'base_amount_inr' => 18000.00,
                'share_percentage' => 5.00,
                'payout_amount' => 900.00,
                'conditions_list' => json_encode(['Added by Peers Global / District Team']),
                'responsibilities_list' => json_encode(['District placement & facilitation']),
            ],

            // Industry Director (ID) Default Rules -> Industry Budget
            [
                'role_id' => $idRoleId,
                'role_code' => 'ID',
                'role_name' => 'Industry Director',
                'channel_key' => 'RENEWAL_EXISTING',
                'category_code' => 'A',
                'allocation_target' => 'industry_budget',
                'base_amount_inr' => 18000.00,
                'share_percentage' => 5.00,
                'payout_amount' => 900.00,
                'conditions_list' => json_encode(['Member renewal in Industry vertical']),
                'responsibilities_list' => json_encode(['Industry vertical engagement']),
            ],
            [
                'role_id' => $idRoleId,
                'role_code' => 'ID',
                'role_name' => 'Industry Director',
                'channel_key' => 'DIRECT_EFFORT',
                'category_code' => 'B',
                'allocation_target' => 'industry_budget',
                'base_amount_inr' => 18000.00,
                'share_percentage' => 15.00,
                'payout_amount' => 2700.00,
                'conditions_list' => json_encode(['Direct bring-in by Industry Director']),
                'responsibilities_list' => json_encode(['Industry leadership recruitment']),
            ],
            [
                'role_id' => $idRoleId,
                'role_code' => 'ID',
                'role_name' => 'Industry Director',
                'channel_key' => 'MEMBER_REFERRAL',
                'category_code' => 'C',
                'allocation_target' => 'industry_budget',
                'base_amount_inr' => 18000.00,
                'share_percentage' => 5.00,
                'payout_amount' => 900.00,
                'conditions_list' => json_encode(['Referred by member within Industry vertical']),
                'responsibilities_list' => json_encode(['Cross-circle industry synergy']),
            ],
            [
                'role_id' => $idRoleId,
                'role_code' => 'ID',
                'role_name' => 'Industry Director',
                'channel_key' => 'TEAM_ASSIGNED',
                'category_code' => 'D',
                'allocation_target' => 'industry_budget',
                'base_amount_inr' => 18000.00,
                'share_percentage' => 5.00,
                'payout_amount' => 900.00,
                'conditions_list' => json_encode(['Added by Peers Global Team']),
                'responsibilities_list' => json_encode(['Industry onboarding']),
            ],
        ];

        foreach ($defaultRules as $rule) {
            $channelId = $channelIdsByKey[$rule['channel_key']] ?? null;
            if (! $channelId) {
                continue;
            }

            $exists = DB::table('finance_commission_rules')
                ->where('role_code', $rule['role_code'])
                ->where('channel_key', $rule['channel_key'])
                ->where('allocation_target', $rule['allocation_target'])
                ->exists();

            if (! $exists) {
                DB::table('finance_commission_rules')->insert(array_merge($rule, [
                    'id' => (string) Str::uuid(),
                    'channel_id' => $channelId,
                    'is_active' => true,
                    'effective_from' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }
};

