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
        if (Schema::hasTable('scan_app_users') && ! Schema::hasColumn('scan_app_users', 'plain_password')) {
            Schema::table('scan_app_users', function (Blueprint $table): void {
                $table->string('plain_password')->nullable()->after('password_hash');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('scan_app_users') && Schema::hasColumn('scan_app_users', 'plain_password')) {
            Schema::table('scan_app_users', function (Blueprint $table): void {
                $table->dropColumn('plain_password');
            });
        }
    }
};
