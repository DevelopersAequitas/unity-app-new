<?php

declare(strict_types=1);

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
        Schema::table('admin_user_roles', function (Blueprint $table): void {
            $table->json('allowed_sections')->nullable()->after('role_id');
            $table->string('permission_type', 10)->default('edit')->after('allowed_sections');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_user_roles', function (Blueprint $table): void {
            $table->dropColumn(['allowed_sections', 'permission_type']);
        });
    }
};
