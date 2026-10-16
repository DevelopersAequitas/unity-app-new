<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('order_items') && ! Schema::hasColumn('order_items', 'updated_at')) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE order_items ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP');
            } else {
                Schema::table('order_items', function (Blueprint $table): void {
                    $table->timestamp('updated_at', 0)->nullable()->useCurrent();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'updated_at')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->dropColumn('updated_at');
            });
        }
    }
};
