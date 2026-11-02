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
        // 1. Master Dynamic Bonus Rules & Conditions Table
        if (! Schema::hasTable('finance_bonus_rules')) {
            Schema::create('finance_bonus_rules', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('rule_code', 80)->unique();
                $table->string('bonus_category', 50)->index(); // speed_bonus, quality_bonus, growth_milestone, circle_launch_bonus, member_milestone, culture_legacy
                $table->string('metric_type', 50)->index(); // NEW_MEMBERS_IN_DAYS, NET_MEMBER_STRENGTH, CIRCLES_LAUNCHED_IN_DAYS, MEETING_ATTENDANCE_RATE, ANNUAL_RENEWAL_RATE, DOCUMENTED_COLLABORATIONS, ZERO_CATEGORY_DISPUTES, POST_HANDOVER_CONSISTENCY
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->integer('target_members_count')->nullable();
                $table->integer('target_circles_count')->nullable();
                $table->integer('min_avg_members_per_circle')->nullable();
                $table->decimal('target_percentage', 5, 2)->nullable();
                $table->integer('target_meetings_count')->nullable();
                $table->string('meeting_window_mode', 20)->nullable(); // first_n, last_n
                $table->integer('target_collaborations_count')->nullable();
                $table->integer('max_disputes_allowed')->nullable();
                $table->integer('timeframe_days')->nullable();
                $table->decimal('bonus_amount_inr', 14, 2)->default(0.00);
                $table->string('reward_perks', 255)->nullable();
                $table->string('exclusivity_group', 80)->nullable();
                $table->boolean('requires_approval')->default(false);
                $table->boolean('is_once_per_tenure')->default(true);
                $table->boolean('is_system')->default(true);
                $table->boolean('is_active')->default(true)->index();
                $table->integer('sort_order')->default(10);
                $table->uuid('updated_by_admin_id')->nullable();
                $table->timestampsTz();
            });
        }

        // 2. Role Activation & Activated Date Configuration Table
        if (! Schema::hasTable('finance_bonus_role_configs')) {
            Schema::create('finance_bonus_role_configs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('bonus_rule_id')->index();
                $table->string('role_code', 50)->index(); // CD, CF, ID, DED, CHAIR
                $table->string('scope_type', 30)->default('circle'); // circle, industry, district
                $table->boolean('is_active')->default(true)->index();
                $table->timestampTz('activated_at')->nullable();
                $table->timestampTz('deactivated_at')->nullable();
                $table->decimal('override_bonus_amount_inr', 14, 2)->nullable();
                $table->integer('override_target_members')->nullable();
                $table->integer('override_timeframe_days')->nullable();
                $table->uuid('updated_by_admin_id')->nullable();
                $table->timestampsTz();

                $table->unique(['bonus_rule_id', 'role_code'], 'uq_finance_bonus_rule_role');
                $table->foreign('bonus_rule_id')->references('id')->on('finance_bonus_rules')->cascadeOnDelete();
            });
        }

        // 3. Leader Bonus Progress, Eligibility & Personal Wallet Payout Awards Table
        if (! Schema::hasTable('finance_leader_bonus_awards')) {
            Schema::create('finance_leader_bonus_awards', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('award_ref', 40)->unique();
                $table->uuid('bonus_rule_id')->index();
                $table->uuid('role_config_id')->nullable()->index();
                $table->uuid('leader_user_id')->index();
                $table->uuid('leader_tenure_id')->index();
                $table->string('role_code', 50)->index();
                $table->string('scope_type', 30)->index();
                $table->string('scope_id', 64)->nullable()->index();
                $table->timestampTz('counting_started_at')->nullable();
                $table->timestampTz('deadline_at')->nullable();
                $table->decimal('current_metric_value', 12, 2)->default(0.00);
                $table->decimal('target_metric_value', 12, 2)->default(0.00);
                $table->decimal('progress_percentage', 5, 2)->default(0.00);
                $table->decimal('bonus_amount_inr', 14, 2)->default(0.00);
                $table->string('reward_perks', 255)->nullable();
                $table->string('status', 30)->default('in_progress')->index(); // in_progress, pending_approval, credited, expired, rejected
                $table->timestampTz('achieved_at')->nullable();
                $table->uuid('approved_by_admin_id')->nullable();
                $table->timestampTz('approved_at')->nullable();
                $table->uuid('wallet_id')->nullable()->index();
                $table->uuid('ledger_transaction_id')->nullable()->index();
                $table->jsonb('proof_metadata')->nullable();
                $table->text('admin_notes')->nullable();
                $table->timestampsTz();

                $table->unique(['bonus_rule_id', 'leader_tenure_id'], 'uq_finance_bonus_rule_tenure');
                $table->foreign('bonus_rule_id')->references('id')->on('finance_bonus_rules')->cascadeOnDelete();
            });
        }

        $this->seedDefaultBonusRulesAndRoleConfigs();
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_leader_bonus_awards');
        Schema::dropIfExists('finance_bonus_role_configs');
        Schema::dropIfExists('finance_bonus_rules');
    }

    private function seedDefaultBonusRulesAndRoleConfigs(): void
    {
        $now = now();

        $rules = [
            // =================================================================
            // 1. SPEED & MOMENTUM BONUSES (CD & CF active by default; ID & DED toggleable)
            // =================================================================
            [
                'rule_code' => 'SPEED_10M_20D',
                'bonus_category' => 'speed_bonus',
                'metric_type' => 'NEW_MEMBERS_IN_DAYS',
                'title' => '10 new Peers in 20 days',
                'description' => 'Leader brings or onboards 10 new paid Peers within 20 days of tenure start or bonus activation.',
                'target_members_count' => 10,
                'timeframe_days' => 20,
                'bonus_amount_inr' => 10000.00,
                'reward_perks' => null,
                'exclusivity_group' => null,
                'requires_approval' => false,
                'sort_order' => 1,
                'active_roles' => ['CD', 'CF'],
                'inactive_roles' => ['ID', 'DED'],
            ],
            [
                'rule_code' => 'CD_SPEED_10M_10D',
                'bonus_category' => 'speed_bonus',
                'metric_type' => 'NEW_MEMBERS_IN_DAYS',
                'title' => '10 new Peers in 10 days',
                'description' => 'Fast-track speed bonus: 10 new paid Peers within 10 days.',
                'target_members_count' => 10,
                'timeframe_days' => 10,
                'bonus_amount_inr' => 25000.00,
                'reward_perks' => null,
                'exclusivity_group' => null,
                'requires_approval' => false,
                'sort_order' => 2,
                'active_roles' => ['CD'],
                'inactive_roles' => ['CF', 'ID', 'DED'],
            ],
            [
                'rule_code' => 'CF_SPEED_30M_40D',
                'bonus_category' => 'speed_bonus',
                'metric_type' => 'NEW_MEMBERS_IN_DAYS',
                'title' => '30 members in 40 days',
                'description' => 'Momentum bonus: 30 paid members within 40 days of tenure start or activation.',
                'target_members_count' => 30,
                'timeframe_days' => 40,
                'bonus_amount_inr' => 25000.00,
                'reward_perks' => null,
                'exclusivity_group' => null,
                'requires_approval' => false,
                'sort_order' => 3,
                'active_roles' => ['CF'],
                'inactive_roles' => ['CD', 'ID', 'DED'],
            ],
            [
                'rule_code' => 'CF_SPEED_40M_60D',
                'bonus_category' => 'speed_bonus',
                'metric_type' => 'NEW_MEMBERS_IN_DAYS',
                'title' => '40 members in 60 days',
                'description' => 'Momentum bonus: 40 paid members within 60 days of tenure start or activation.',
                'target_members_count' => 40,
                'timeframe_days' => 60,
                'bonus_amount_inr' => 40000.00,
                'reward_perks' => null,
                'exclusivity_group' => null,
                'requires_approval' => false,
                'sort_order' => 4,
                'active_roles' => ['CF'],
                'inactive_roles' => ['CD', 'ID', 'DED'],
            ],
            [
                'rule_code' => 'CF_SPEED_50M_75D',
                'bonus_category' => 'speed_bonus',
                'metric_type' => 'NEW_MEMBERS_IN_DAYS',
                'title' => '50 members in 75 days',
                'description' => 'Momentum bonus: 50 paid members within 75 days of tenure start or activation.',
                'target_members_count' => 50,
                'timeframe_days' => 75,
                'bonus_amount_inr' => 50000.00,
                'reward_perks' => 'Founder Dinner / Retreat',
                'exclusivity_group' => null,
                'requires_approval' => false,
                'sort_order' => 5,
                'active_roles' => ['CF'],
                'inactive_roles' => ['CD', 'ID', 'DED'],
            ],

            // =================================================================
            // 2. QUALITY, RETENTION, COLLABORATION & LEGACY BONUSES
            // =================================================================
            [
                'rule_code' => 'CD_QUALITY_ATTENDANCE_90_LAST6',
                'bonus_category' => 'quality_bonus',
                'metric_type' => 'MEETING_ATTENDANCE_RATE',
                'title' => '90%+ average attendance in last 6 meetings',
                'description' => 'Maintain 90% or higher average member attendance across the last 6 circle meetings.',
                'target_percentage' => 90.00,
                'target_meetings_count' => 6,
                'meeting_window_mode' => 'last_n',
                'bonus_amount_inr' => 15000.00,
                'reward_perks' => null,
                'requires_approval' => false,
                'sort_order' => 6,
                'active_roles' => ['CD'],
                'inactive_roles' => ['ID', 'DED'],
            ],
            [
                'rule_code' => 'CF_QUALITY_ATTENDANCE_90_FIRST6',
                'bonus_category' => 'quality_bonus',
                'metric_type' => 'MEETING_ATTENDANCE_RATE',
                'title' => '90%+ average attendance in first 6 meetings',
                'description' => 'Achieve 90% or higher average member attendance across the first 6 circle meetings.',
                'target_percentage' => 90.00,
                'target_meetings_count' => 6,
                'meeting_window_mode' => 'first_n',
                'bonus_amount_inr' => 15000.00,
                'reward_perks' => 'Award',
                'requires_approval' => false,
                'sort_order' => 7,
                'active_roles' => ['CF'],
                'inactive_roles' => ['ID', 'DED'],
            ],
            [
                'rule_code' => 'QUALITY_RENEWAL_90_YEAR1',
                'bonus_category' => 'quality_bonus',
                'metric_type' => 'ANNUAL_RENEWAL_RATE',
                'title' => '90%+ renewal rate at Year 1',
                'description' => 'Achieve 90% or higher member renewal rate at Year 1 (365 days).',
                'target_percentage' => 90.00,
                'timeframe_days' => 365,
                'bonus_amount_inr' => 25000.00,
                'reward_perks' => 'Award',
                'requires_approval' => false,
                'sort_order' => 8,
                'active_roles' => ['CD', 'CF'],
                'inactive_roles' => ['ID', 'DED'],
            ],
            [
                'rule_code' => 'QUALITY_COLLAB_100_6M',
                'bonus_category' => 'quality_bonus',
                'metric_type' => 'DOCUMENTED_COLLABORATIONS',
                'title' => '100+ documented collaborations in 6 months',
                'description' => 'Facilitate 100 or more documented peer collaborations within 6 months (180 days).',
                'target_collaborations_count' => 100,
                'timeframe_days' => 180,
                'bonus_amount_inr' => 10000.00,
                'reward_perks' => null,
                'requires_approval' => false,
                'sort_order' => 9,
                'active_roles' => ['CD', 'CF'],
                'inactive_roles' => ['ID', 'DED'],
            ],
            [
                'rule_code' => 'QUALITY_ZERO_DISPUTES_12M',
                'bonus_category' => 'quality_bonus',
                'metric_type' => 'ZERO_CATEGORY_DISPUTES',
                'title' => 'Zero category disputes in 12 months',
                'description' => 'Maintain zero category disputes across 12 months (365 days). Subject to Admin approval.',
                'max_disputes_allowed' => 0,
                'timeframe_days' => 365,
                'bonus_amount_inr' => 10000.00,
                'reward_perks' => null,
                'requires_approval' => true,
                'sort_order' => 10,
                'active_roles' => ['CD', 'CF'],
                'inactive_roles' => ['ID', 'DED'],
            ],
            [
                'rule_code' => 'CF_CULTURE_LEGACY_6M',
                'bonus_category' => 'culture_legacy',
                'metric_type' => 'POST_HANDOVER_CONSISTENCY',
                'title' => 'Circle continues meeting consistently 6 months after CF handover to CD',
                'description' => 'Culture Legacy Bonus: Circle continues meeting consistently for 6 months (180 days) after Circle Founder hands over to Circle Director.',
                'timeframe_days' => 180,
                'bonus_amount_inr' => 25000.00,
                'reward_perks' => 'Culture Legacy Bonus',
                'requires_approval' => true,
                'sort_order' => 11,
                'active_roles' => ['CF'],
                'inactive_roles' => ['CD', 'ID', 'DED'],
            ],

            // =================================================================
            // 3. GROWTH MILESTONE BONUSES — NET CIRCLE STRENGTH (CD active by default)
            // =================================================================
            [
                'rule_code' => 'CD_STRENGTH_50P',
                'bonus_category' => 'growth_milestone',
                'metric_type' => 'NET_MEMBER_STRENGTH',
                'title' => 'Growth Milestone: 50 Peers (Net Circle Strength)',
                'description' => 'Circle reaches a net active strength of 50 Peers.',
                'target_members_count' => 50,
                'bonus_amount_inr' => 50000.00,
                'reward_perks' => null,
                'requires_approval' => false,
                'sort_order' => 12,
                'active_roles' => ['CD'],
                'inactive_roles' => ['CF', 'ID', 'DED'],
            ],
            [
                'rule_code' => 'CD_STRENGTH_60P',
                'bonus_category' => 'growth_milestone',
                'metric_type' => 'NET_MEMBER_STRENGTH',
                'title' => 'Growth Milestone: 60 Peers (Net Circle Strength)',
                'description' => 'Circle reaches a net active strength of 60 Peers.',
                'target_members_count' => 60,
                'bonus_amount_inr' => 60000.00,
                'reward_perks' => null,
                'requires_approval' => false,
                'sort_order' => 13,
                'active_roles' => ['CD'],
                'inactive_roles' => ['CF', 'ID', 'DED'],
            ],
            [
                'rule_code' => 'CD_STRENGTH_75P',
                'bonus_category' => 'growth_milestone',
                'metric_type' => 'NET_MEMBER_STRENGTH',
                'title' => 'Growth Milestone: 75 Peers (Net Circle Strength)',
                'description' => 'Circle reaches a net active strength of 75 Peers.',
                'target_members_count' => 75,
                'bonus_amount_inr' => 75000.00,
                'reward_perks' => null,
                'requires_approval' => false,
                'sort_order' => 14,
                'active_roles' => ['CD'],
                'inactive_roles' => ['CF', 'ID', 'DED'],
            ],
            [
                'rule_code' => 'CD_STRENGTH_100P',
                'bonus_category' => 'growth_milestone',
                'metric_type' => 'NET_MEMBER_STRENGTH',
                'title' => 'Growth Milestone: 100 Peers (Net Circle Strength)',
                'description' => 'Circle reaches a net active strength of 100 Peers.',
                'target_members_count' => 100,
                'bonus_amount_inr' => 100000.00,
                'reward_perks' => null,
                'requires_approval' => false,
                'sort_order' => 15,
                'active_roles' => ['CD'],
                'inactive_roles' => ['CF', 'ID', 'DED'],
            ],

            // =================================================================
            // 4. INDUSTRY DIRECTOR (ID) & DED — CIRCLE LAUNCH BONUSES
            // =================================================================
            [
                'rule_code' => 'ID_CIRCLE_LAUNCH_5_1Y',
                'bonus_category' => 'circle_launch_bonus',
                'metric_type' => 'CIRCLES_LAUNCHED_IN_DAYS',
                'title' => '5 circles launched in 1 year (avg. 25+ members each)',
                'description' => 'Launch 5 circles within 1 year (365 days) with an average of 25+ active members each.',
                'target_circles_count' => 5,
                'min_avg_members_per_circle' => 25,
                'timeframe_days' => 365,
                'bonus_amount_inr' => 50000.00,
                'reward_perks' => null,
                'requires_approval' => false,
                'sort_order' => 16,
                'active_roles' => ['ID'],
                'inactive_roles' => ['DED'],
            ],
            [
                'rule_code' => 'ID_CIRCLE_LAUNCH_10_1Y',
                'bonus_category' => 'circle_launch_bonus',
                'metric_type' => 'CIRCLES_LAUNCHED_IN_DAYS',
                'title' => '10 circles launched in 1 year',
                'description' => 'Launch 10 circles within 1 year (365 days).',
                'target_circles_count' => 10,
                'min_avg_members_per_circle' => null,
                'timeframe_days' => 365,
                'bonus_amount_inr' => 250000.00,
                'reward_perks' => null,
                'requires_approval' => false,
                'sort_order' => 17,
                'active_roles' => ['ID'],
                'inactive_roles' => ['DED'],
            ],
            [
                'rule_code' => 'ID_CIRCLE_LAUNCH_25_1Y',
                'bonus_category' => 'circle_launch_bonus',
                'metric_type' => 'CIRCLES_LAUNCHED_IN_DAYS',
                'title' => '25 circles launched in 1 year',
                'description' => 'Launch 25 circles within 1 year (365 days).',
                'target_circles_count' => 25,
                'min_avg_members_per_circle' => null,
                'timeframe_days' => 365,
                'bonus_amount_inr' => 2500000.00,
                'reward_perks' => null,
                'requires_approval' => false,
                'sort_order' => 18,
                'active_roles' => ['ID'],
                'inactive_roles' => ['DED'],
            ],

            // =================================================================
            // 5. INDUSTRY DIRECTOR (ID) & DED — MEMBER MILESTONE BONUSES
            // =================================================================
            [
                'rule_code' => 'ID_MEMBER_MILESTONE_500',
                'bonus_category' => 'member_milestone',
                'metric_type' => 'NET_MEMBER_STRENGTH',
                'title' => '500 members across industry circles',
                'description' => 'Reach 500 active members across all circles in the leader\'s assigned Industry/District scope.',
                'target_members_count' => 500,
                'bonus_amount_inr' => 50000.00,
                'reward_perks' => null,
                'requires_approval' => false,
                'sort_order' => 19,
                'active_roles' => ['ID'],
                'inactive_roles' => ['DED'],
            ],
            [
                'rule_code' => 'ID_MEMBER_MILESTONE_1000',
                'bonus_category' => 'member_milestone',
                'metric_type' => 'NET_MEMBER_STRENGTH',
                'title' => '1,000 members across industry circles',
                'description' => 'Reach 1,000 active members across all circles in the leader\'s assigned Industry/District scope.',
                'target_members_count' => 1000,
                'bonus_amount_inr' => 150000.00,
                'reward_perks' => null,
                'requires_approval' => false,
                'sort_order' => 20,
                'active_roles' => ['ID'],
                'inactive_roles' => ['DED'],
            ],
            [
                'rule_code' => 'ID_MEMBER_MILESTONE_5000',
                'bonus_category' => 'member_milestone',
                'metric_type' => 'NET_MEMBER_STRENGTH',
                'title' => '5,000 members across industry circles',
                'description' => 'Reach 5,000 active members across all circles in the leader\'s assigned Industry/District scope.',
                'target_members_count' => 5000,
                'bonus_amount_inr' => 1000000.00,
                'reward_perks' => null,
                'requires_approval' => false,
                'sort_order' => 21,
                'active_roles' => ['ID'],
                'inactive_roles' => ['DED'],
            ],
        ];

        foreach ($rules as $ruleData) {
            $activeRoles = $ruleData['active_roles'] ?? [];
            $inactiveRoles = $ruleData['inactive_roles'] ?? [];
            unset($ruleData['active_roles'], $ruleData['inactive_roles']);

            $existingRule = DB::table('finance_bonus_rules')->where('rule_code', $ruleData['rule_code'])->first();
            if ($existingRule) {
                $ruleId = (string) $existingRule->id;
            } else {
                $ruleId = (string) Str::uuid();
                DB::table('finance_bonus_rules')->insert(array_merge([
                    'id' => $ruleId,
                    'target_members_count' => null,
                    'target_circles_count' => null,
                    'min_avg_members_per_circle' => null,
                    'target_percentage' => null,
                    'target_meetings_count' => null,
                    'meeting_window_mode' => null,
                    'target_collaborations_count' => null,
                    'max_disputes_allowed' => null,
                    'timeframe_days' => null,
                    'exclusivity_group' => null,
                    'is_once_per_tenure' => true,
                    'is_system' => true,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $ruleData));
            }

            foreach ($activeRoles as $rCode) {
                $scopeType = match ($rCode) {
                    'ID' => 'industry',
                    'DED' => 'district',
                    default => 'circle',
                };
                DB::table('finance_bonus_role_configs')->updateOrInsert(
                    ['bonus_rule_id' => $ruleId, 'role_code' => $rCode],
                    [
                        'id' => (string) Str::uuid(),
                        'scope_type' => $scopeType,
                        'is_active' => true,
                        'activated_at' => $now,
                        'deactivated_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            foreach ($inactiveRoles as $rCode) {
                $scopeType = match ($rCode) {
                    'ID' => 'industry',
                    'DED' => 'district',
                    default => 'circle',
                };
                $exists = DB::table('finance_bonus_role_configs')
                    ->where('bonus_rule_id', $ruleId)
                    ->where('role_code', $rCode)
                    ->exists();

                if (! $exists) {
                    DB::table('finance_bonus_role_configs')->insert([
                        'id' => (string) Str::uuid(),
                        'bonus_rule_id' => $ruleId,
                        'role_code' => $rCode,
                        'scope_type' => $scopeType,
                        'is_active' => false,
                        'activated_at' => null,
                        'deactivated_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }
};

