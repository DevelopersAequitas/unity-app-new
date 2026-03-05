<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'zoho_customer_id')) {
                $table->string('zoho_customer_id', 50)->nullable();
            }

            if (! Schema::hasColumn('users', 'zoho_subscription_id')) {
                $table->string('zoho_subscription_id', 100)->nullable();
            }

            if (! Schema::hasColumn('users', 'zoho_plan_code')) {
                $table->string('zoho_plan_code', 100)->nullable();
            }

            if (! Schema::hasColumn('users', 'zoho_last_invoice_id')) {
                $table->string('zoho_last_invoice_id', 100)->nullable();
            }

            if (! Schema::hasColumn('users', 'membership_starts_at')) {
                $table->timestampTz('membership_starts_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'membership_ends_at')) {
                $table->timestampTz('membership_ends_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'last_payment_at')) {
                $table->timestampTz('last_payment_at')->nullable();
            }
        });

        DB::statement('CREATE INDEX IF NOT EXISTS users_zoho_customer_id_idx ON users (zoho_customer_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS users_zoho_subscription_id_idx ON users (zoho_subscription_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS users_membership_ends_at_idx ON users (membership_ends_at)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_zoho_customer_id_idx');
        DB::statement('DROP INDEX IF EXISTS users_zoho_subscription_id_idx');
        DB::statement('DROP INDEX IF EXISTS users_membership_ends_at_idx');

        Schema::table('users', function (Blueprint $table) {
            $toDrop = [];

            foreach ([
                'zoho_customer_id',
                'zoho_subscription_id',
                'zoho_plan_code',
                'zoho_last_invoice_id',
                'membership_starts_at',
                'membership_ends_at',
                'last_payment_at',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $toDrop[] = $column;
                }
            }

            if ($toDrop !== []) {
                $table->dropColumn($toDrop);
            }
        });
    }
};
