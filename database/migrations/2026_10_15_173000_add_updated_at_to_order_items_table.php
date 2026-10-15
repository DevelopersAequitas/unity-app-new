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
        if (Schema::hasTable('order_items') && ! Schema::hasColumn('order_items', 'updated_at')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->timestamp('updated_at')->nullable()->useCurrent();
            });
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
