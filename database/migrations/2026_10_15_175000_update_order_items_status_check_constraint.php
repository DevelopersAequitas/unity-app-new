<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_status_ck');
            DB::statement("ALTER TABLE order_items ADD CONSTRAINT order_items_status_ck CHECK (status IN (
                'ACTIVE',
                'PENDING',
                'CONFIRMED',
                'PROCESSING',
                'DISPATCHED',
                'SHIPPED',
                'OUT_FOR_DELIVERY',
                'DELIVERED',
                'CANCELLED',
                'FAILED',
                'RETURN_REQUESTED',
                'RETURN_APPROVED',
                'RETURN_REJECTED',
                'RETURNED'
            ))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_status_ck');
        }
    }
};
