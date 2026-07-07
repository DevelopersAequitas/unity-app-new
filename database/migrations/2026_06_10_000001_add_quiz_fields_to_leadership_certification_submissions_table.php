<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leadership_certification_submissions')) {
            Schema::table('leadership_certification_submissions', function (Blueprint $table) {
                $columns = [
                    'team_struggling_action',
                    'leader_definition',
                    'junior_challenged_idea',
                    'leader_when_wrong',
                    'team_motivation',
                    'leadership_meaning',
                    'different_background_team_first_step',
                    'group_task_approach',
                    'team_conflict_action',
                    'leader_makes_others_feel',
                    'team_big_achievement_action',
                    'guide_new_entrepreneurs',
                    'local_business_group_thought',
                    'silent_team_meeting_action',
                    'leadership_starts_with',
                    'business_community_approach',
                    'low_confidence_person_action',
                    'support_most_in_team',
                    'good_leadership_means',
                    'feedback_frequency',
                    'unhappy_customer_action',
                    'new_network_person_action',
                    'local_event_speaking_action',
                    'leadership_role_offer_action',
                    'great_leader_opinion',
                ];

                foreach ($columns as $column) {
                    if (! Schema::hasColumn('leadership_certification_submissions', $column)) {
                        $table->text($column)->nullable();
                    }
                }

                if (! Schema::hasColumn('leadership_certification_submissions', 'total_score')) {
                    $table->integer('total_score')->default(0);
                }

                if (! Schema::hasColumn('leadership_certification_submissions', 'percentage')) {
                    $table->decimal('percentage', 5, 2)->default(0);
                }

                if (! Schema::hasColumn('leadership_certification_submissions', 'certification_level')) {
                    $table->string('certification_level', 100)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leadership_certification_submissions')) {
            Schema::table('leadership_certification_submissions', function (Blueprint $table) {
                $columns = [
                    'team_struggling_action', 'leader_definition', 'junior_challenged_idea',
                    'leader_when_wrong', 'team_motivation', 'leadership_meaning',
                    'different_background_team_first_step', 'group_task_approach', 'team_conflict_action',
                    'leader_makes_others_feel', 'team_big_achievement_action', 'guide_new_entrepreneurs',
                    'local_business_group_thought', 'silent_team_meeting_action', 'leadership_starts_with',
                    'business_community_approach', 'low_confidence_person_action', 'support_most_in_team',
                    'good_leadership_means', 'feedback_frequency', 'unhappy_customer_action',
                    'new_network_person_action', 'local_event_speaking_action', 'leadership_role_offer_action',
                    'great_leader_opinion', 'total_score', 'percentage', 'certification_level',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('leadership_certification_submissions', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
