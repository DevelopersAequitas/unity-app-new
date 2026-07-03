<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('entrepreneur_certification_submissions')) {
            Schema::table('entrepreneur_certification_submissions', function (Blueprint $table) {
                $columns = [
                    'business_start_reason',
                    'business_failure_reaction',
                    'successful_entrepreneur_definition',
                    'business_purpose_frequency',
                    'business_challenge_approach',
                    'finance_tracking_frequency',
                    'pricing_decision_method',
                    'business_systems_status',
                    'unhappy_customer_response',
                    'money_separation_status',
                    'failure_recovery_action',
                    'major_decision_method',
                    'competitor_growth_response',
                    'new_idea_action',
                    'risk_approach',
                    'networking_belief',
                    'conflict_handling',
                    'team_motivation_method',
                    'business_meet_frequency',
                    'community_growth_belief',
                    'five_year_business_vision',
                    'success_meaning',
                    'work_life_balance_method',
                    'society_value_belief',
                    'future_mentorship_belief',
                ];

                foreach ($columns as $column) {
                    if (!Schema::hasColumn('entrepreneur_certification_submissions', $column)) {
                        $table->text($column)->nullable();
                    }
                }

                if (!Schema::hasColumn('entrepreneur_certification_submissions', 'total_score')) {
                    $table->integer('total_score')->default(0);
                }

                if (!Schema::hasColumn('entrepreneur_certification_submissions', 'percentage')) {
                    $table->decimal('percentage', 5, 2)->default(0);
                }

                if (!Schema::hasColumn('entrepreneur_certification_submissions', 'certification_tier')) {
                    $table->string('certification_tier', 100)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('entrepreneur_certification_submissions')) {
            Schema::table('entrepreneur_certification_submissions', function (Blueprint $table) {
                $columns = [
                    'business_start_reason', 'business_failure_reaction', 'successful_entrepreneur_definition',
                    'business_purpose_frequency', 'business_challenge_approach', 'finance_tracking_frequency',
                    'pricing_decision_method', 'business_systems_status', 'unhappy_customer_response',
                    'money_separation_status', 'failure_recovery_action', 'major_decision_method',
                    'competitor_growth_response', 'new_idea_action', 'risk_approach',
                    'networking_belief', 'conflict_handling', 'team_motivation_method',
                    'business_meet_frequency', 'community_growth_belief', 'five_year_business_vision',
                    'success_meaning', 'work_life_balance_method', 'society_value_belief',
                    'future_mentorship_belief', 'total_score', 'percentage', 'certification_tier'
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('entrepreneur_certification_submissions', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
