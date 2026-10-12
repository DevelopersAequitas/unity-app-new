<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'min_quantity_per_order')) {
                $table->integer('min_quantity_per_order')->default(1)->nullable()->after('delivery_modes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'min_quantity_per_order')) {
                $table->dropColumn('min_quantity_per_order');
            }
        });
    }
};
