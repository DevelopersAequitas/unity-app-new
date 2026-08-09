<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_user_roles')) {
            return;
        }

        Schema::table('admin_user_roles', function (Blueprint $table): void {
            if (! Schema::hasColumn('admin_user_roles', 'allowed_sections')) {
                $table->json('allowed_sections')->nullable()->after('role_id');
            }
            if (! Schema::hasColumn('admin_user_roles', 'permission_type')) {
                $table->string('permission_type', 10)->default('edit')->after('allowed_sections');
            }
        });
    }

    public function down(): void
    {
        Schema::table('admin_user_roles', function (Blueprint $table): void {
            $table->dropColumn(['allowed_sections', 'permission_type']);
        });
    }
};
