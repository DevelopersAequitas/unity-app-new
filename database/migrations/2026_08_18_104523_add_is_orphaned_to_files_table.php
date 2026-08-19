<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('files')) {
            return;
        }

        if (! Schema::hasColumn('files', 'is_orphaned')) {
            Schema::table('files', function (Blueprint $table) {
                $table->boolean('is_orphaned')->default(false);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('files')) {
            return;
        }

        if (Schema::hasColumn('files', 'is_orphaned')) {
            Schema::table('files', function (Blueprint $table) {
                $table->dropColumn('is_orphaned');
            });
        }
    }
};
