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
        if (Schema::hasTable('circles')) {
            Schema::table('circles', function (Blueprint $table) {
                if (! Schema::hasColumn('circles', 'circle_image_file_id')) {
                    $table->uuid('circle_image_file_id')->nullable()->after('cover_file_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('circles')) {
            Schema::table('circles', function (Blueprint $table) {
                if (Schema::hasColumn('circles', 'circle_image_file_id')) {
                    $table->dropColumn('circle_image_file_id');
                }
            });
        }
    }
};
