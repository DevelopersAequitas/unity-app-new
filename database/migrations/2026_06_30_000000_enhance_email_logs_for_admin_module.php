<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_logs')) {
            return;
        }

        Schema::table('email_logs', function (Blueprint $table) {
            $columns = Schema::getColumnListing('email_logs');
            $add = fn (string $column): bool => ! in_array($column, $columns, true);

            if ($add('recipient_user_type')) $table->string('recipient_user_type', 100)->nullable();
            if ($add('triggered_by')) $table->string('triggered_by', 100)->nullable();
            if ($add('trigger_user_id')) $table->uuid('trigger_user_id')->nullable();
            if ($add('trigger_user_type')) $table->string('trigger_user_type', 100)->nullable();
            if ($add('trigger_user_name')) $table->string('trigger_user_name')->nullable();
            if ($add('trigger_user_email')) $table->string('trigger_user_email')->nullable();
            if ($add('trigger_user_role')) $table->string('trigger_user_role')->nullable();
            if ($add('ip_address')) $table->string('ip_address', 45)->nullable();
            if ($add('user_agent')) $table->text('user_agent')->nullable();
            if ($add('admin_id')) $table->uuid('admin_id')->nullable();
            if ($add('admin_name')) $table->string('admin_name')->nullable();
            if ($add('admin_email')) $table->string('admin_email')->nullable();
            if ($add('admin_role')) $table->string('admin_role')->nullable();
            if ($add('admin_session_id')) $table->string('admin_session_id')->nullable();
            if ($add('admin_login_time')) $table->timestampTz('admin_login_time')->nullable();
            if ($add('admin_last_activity')) $table->timestampTz('admin_last_activity')->nullable();
            if ($add('admin_ip_address')) $table->string('admin_ip_address', 45)->nullable();
            if ($add('admin_user_agent')) $table->text('admin_user_agent')->nullable();
            if ($add('mail_provider')) $table->string('mail_provider')->nullable();
            if ($add('mail_driver')) $table->string('mail_driver')->nullable();
            if ($add('smtp_host')) $table->string('smtp_host')->nullable();
            if ($add('queue_id')) $table->string('queue_id')->nullable();
            if ($add('message_id')) $table->string('message_id')->nullable();
            if ($add('queue_name')) $table->string('queue_name')->nullable();
            if ($add('queue_job_id')) $table->string('queue_job_id')->nullable();
            if ($add('attempts')) $table->unsignedInteger('attempts')->nullable();
            if ($add('processing_time_ms')) $table->unsignedInteger('processing_time_ms')->nullable();
            if ($add('provider_response')) $table->text('provider_response')->nullable();
            if ($add('plain_text')) $table->longText('plain_text')->nullable();
            if ($add('template_name')) $table->string('template_name')->nullable();
            if ($add('template_version')) $table->string('template_version')->nullable();
            if ($add('variables_used')) $table->json('variables_used')->nullable();
            if ($add('attachments')) $table->json('attachments')->nullable();
            if ($add('exception_class')) $table->string('exception_class')->nullable();
            if ($add('stack_trace')) $table->longText('stack_trace')->nullable();
            if ($add('retry_count')) $table->unsignedInteger('retry_count')->nullable();
            if ($add('last_retry_at')) $table->timestampTz('last_retry_at')->nullable();
            if ($add('created_by')) $table->string('created_by')->nullable();
            if ($add('updated_by')) $table->string('updated_by')->nullable();
            if ($add('updated_at')) $table->timestampTz('updated_at')->nullable();
        });

        DB::statement('CREATE INDEX IF NOT EXISTS email_logs_status_index ON email_logs (status)');
        DB::statement('CREATE INDEX IF NOT EXISTS email_logs_template_key_index ON email_logs (template_key)');
        DB::statement('CREATE INDEX IF NOT EXISTS email_logs_source_module_index ON email_logs (source_module)');
        DB::statement('CREATE INDEX IF NOT EXISTS email_logs_recipient_email_index ON email_logs (to_email)');
        DB::statement('CREATE INDEX IF NOT EXISTS email_logs_created_at_index ON email_logs (created_at)');
    }

    public function down(): void
    {
        // Intentionally keep audit data. Indexes are harmless and support existing reports.
    }
};
