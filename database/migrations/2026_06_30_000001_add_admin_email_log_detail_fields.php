<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_logs')) {
            return;
        }

        DB::statement('ALTER TABLE email_logs ADD COLUMN IF NOT EXISTS triggered_by VARCHAR(255) NULL');
        DB::statement('ALTER TABLE email_logs ADD COLUMN IF NOT EXISTS triggered_user_id UUID NULL');
        DB::statement('ALTER TABLE email_logs ADD COLUMN IF NOT EXISTS mail_provider VARCHAR(100) NULL');
        DB::statement('ALTER TABLE email_logs ADD COLUMN IF NOT EXISTS queue_id VARCHAR(255) NULL');
        DB::statement('ALTER TABLE email_logs ADD COLUMN IF NOT EXISTS message_id VARCHAR(255) NULL');
        DB::statement('ALTER TABLE email_logs ADD COLUMN IF NOT EXISTS body_text TEXT NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS email_logs_source_module_index ON email_logs (source_module)');
        DB::statement('CREATE INDEX IF NOT EXISTS email_logs_status_index ON email_logs (status)');
        DB::statement('CREATE INDEX IF NOT EXISTS email_logs_created_at_index ON email_logs (created_at)');
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_logs')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS email_logs_created_at_index');
        DB::statement('DROP INDEX IF EXISTS email_logs_status_index');
        DB::statement('DROP INDEX IF EXISTS email_logs_source_module_index');
        DB::statement('ALTER TABLE email_logs DROP COLUMN IF EXISTS body_text');
        DB::statement('ALTER TABLE email_logs DROP COLUMN IF EXISTS message_id');
        DB::statement('ALTER TABLE email_logs DROP COLUMN IF EXISTS queue_id');
        DB::statement('ALTER TABLE email_logs DROP COLUMN IF EXISTS mail_provider');
        DB::statement('ALTER TABLE email_logs DROP COLUMN IF EXISTS triggered_user_id');
        DB::statement('ALTER TABLE email_logs DROP COLUMN IF EXISTS triggered_by');
    }
};
