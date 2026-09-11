<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('scan_app_users')) {
            Schema::table('scan_app_users', function (Blueprint $table): void {
                if (! Schema::hasColumn('scan_app_users', 'event_ids')) {
                    $table->jsonb('event_ids')->nullable()->after('event_id');
                }
                if (! Schema::hasColumn('scan_app_users', 'event_name')) {
                    $table->string('event_name')->nullable()->after('event_ids');
                }
                if (! Schema::hasColumn('scan_app_users', 'created_by_admin_id')) {
                    $table->uuid('created_by_admin_id')->nullable()->after('is_active');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('scan_app_users')) {
            Schema::table('scan_app_users', function (Blueprint $table): void {
                if (Schema::hasColumn('scan_app_users', 'event_ids')) {
                    $table->dropColumn('event_ids');
                }
                if (Schema::hasColumn('scan_app_users', 'event_name')) {
                    $table->dropColumn('event_name');
                }
                if (Schema::hasColumn('scan_app_users', 'created_by_admin_id')) {
                    $table->dropColumn('created_by_admin_id');
                }
            });
        }
    }
};
