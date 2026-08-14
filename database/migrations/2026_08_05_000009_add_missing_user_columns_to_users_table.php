<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $columnsToAdd = [
            'membership_ends_at' => 'TIMESTAMP WITHOUT TIME ZONE NULL',
            'business_category_id' => 'VARCHAR(255) NULL',
            'main_business_category_id' => 'VARCHAR(255) NULL',
            'coin_medal_rank' => 'VARCHAR(255) NULL',
            'coin_milestone_title' => 'VARCHAR(255) NULL',
            'coin_milestone_meaning' => 'TEXT NULL',
            'contribution_award_name' => 'VARCHAR(255) NULL',
            'contribution_award_recognition' => 'TEXT NULL',
            'skills' => 'TEXT NULL',
            'interests' => 'TEXT NULL',
            'gender' => 'VARCHAR(50) NULL',
            'dob' => 'DATE NULL',
            'experience_years' => 'INT NULL',
            'experience_summary' => 'TEXT NULL',
            'profile_photo_file_id' => 'UUID NULL',
            'cover_photo_file_id' => 'UUID NULL',
            'zoho_customer_id' => 'VARCHAR(255) NULL',
            'zoho_subscription_id' => 'VARCHAR(255) NULL',
            'zoho_plan_code' => 'VARCHAR(255) NULL',
            'zoho_last_invoice_id' => 'VARCHAR(255) NULL',
            'membership_starts_at' => 'TIMESTAMP WITHOUT TIME ZONE NULL',
            'last_payment_at' => 'TIMESTAMP WITHOUT TIME ZONE NULL',
            'welcome_membership_email_sent_at' => 'TIMESTAMP WITHOUT TIME ZONE NULL',
            'welcome_membership_email_status' => 'VARCHAR(50) NULL',
            'welcome_membership_email_error' => 'TEXT NULL',
            'welcome_membership_email_plan_code' => 'VARCHAR(100) NULL',
            'peer_id' => 'VARCHAR(100) NULL',
            'approval_status' => 'VARCHAR(50) NULL',
        ];

        foreach ($columnsToAdd as $col => $type) {
            try {
                DB::statement("ALTER TABLE users ADD COLUMN IF NOT EXISTS {$col} {$type}");
            } catch (Throwable $e) {
                // Skip if error
            }
        }

        // Ensure PostgreSQL enum or column supports dynamic membership statuses
        $statuses = config('membership.statuses', []);
        $statuses[] = 'free_peer';
        $statuses[] = 'free_trial_peer';
        $statuses = array_unique($statuses);

        foreach ($statuses as $val) {
            try {
                $valEscaped = str_replace("'", "''", $val);
                DB::statement("ALTER TYPE membership_status_enum ADD VALUE IF NOT EXISTS '{$valEscaped}'");
            } catch (Throwable $e) {
                // Type might not exist or driver is not pgsql
            }
        }

        try {
            DB::statement('ALTER TABLE users ALTER COLUMN membership_status TYPE VARCHAR(255) USING membership_status::VARCHAR');
        } catch (Throwable $e) {
            // Driver is not pgsql or column already VARCHAR
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op for safety
    }
};
