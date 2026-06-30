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

        DB::statement('CREATE INDEX IF NOT EXISTS email_logs_status_index ON email_logs (status)');
        DB::statement('CREATE INDEX IF NOT EXISTS email_logs_template_key_index ON email_logs (template_key)');
        DB::statement('CREATE INDEX IF NOT EXISTS email_logs_source_module_index ON email_logs (source_module)');
        DB::statement('CREATE INDEX IF NOT EXISTS email_logs_recipient_email_index ON email_logs (to_email)');
        DB::statement('CREATE INDEX IF NOT EXISTS email_logs_created_at_index ON email_logs (created_at)');
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_logs')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS email_logs_created_at_index');
        DB::statement('DROP INDEX IF EXISTS email_logs_recipient_email_index');
        DB::statement('DROP INDEX IF EXISTS email_logs_source_module_index');
        DB::statement('DROP INDEX IF EXISTS email_logs_template_key_index');
        DB::statement('DROP INDEX IF EXISTS email_logs_status_index');
    }
};
