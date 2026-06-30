<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('app_notifications')) {
            Schema::table('app_notifications', function (Blueprint $table): void {
                if (! Schema::hasColumn('app_notifications', 'deleted_at')) {
                    $table->timestamp('deleted_at')->nullable()->index();
                }
            });
            DB::statement("ALTER TABLE app_notifications ALTER COLUMN type SET DEFAULT 'test_notification'");
            DB::statement("ALTER TABLE app_notifications ALTER COLUMN data SET DEFAULT '{}'::jsonb");
        }

        if (Schema::hasTable('notification_delivery_logs')) {
            Schema::table('notification_delivery_logs', function (Blueprint $table): void {
                if (! Schema::hasColumn('notification_delivery_logs', 'campaign_id')) {
                    $table->uuid('campaign_id')->nullable()->index();
                }
            });
            DB::statement("ALTER TABLE notification_delivery_logs ALTER COLUMN request_payload SET DEFAULT '{}'::jsonb");
            DB::statement("ALTER TABLE notification_delivery_logs ALTER COLUMN response_payload SET DEFAULT '{}'::jsonb");
        }

        if (Schema::hasTable('user_push_tokens') && Schema::hasColumn('user_push_tokens', 'is_active')) {
            DB::statement('CREATE INDEX IF NOT EXISTS user_push_tokens_user_id_is_active_idx ON user_push_tokens (user_id, is_active)');
        }
    }

    public function down(): void
    {
        // Forward-only safety migration. Do not drop notification data or columns.
    }
};
