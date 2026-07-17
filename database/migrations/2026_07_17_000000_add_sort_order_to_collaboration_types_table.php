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
        if (Schema::hasTable('collaboration_types') && !Schema::hasColumn('collaboration_types', 'sort_order')) {
            Schema::table('collaboration_types', function (Blueprint $table) {
                $table->integer('sort_order')->default(0);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('collaboration_types') && Schema::hasColumn('collaboration_types', 'sort_order')) {
            Schema::table('collaboration_types', function (Blueprint $table) {
                $table->dropColumn('sort_order');
            });
        }
    }
};
