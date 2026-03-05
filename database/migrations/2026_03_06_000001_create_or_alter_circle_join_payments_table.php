<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('circle_join_payments')) {
            Schema::create('circle_join_payments', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->uuid('circle_id');
                $table->string('provider', 30)->default('zoho');
                $table->string('status', 40)->default('pending');
                $table->unsignedSmallInteger('duration_months')->nullable();
                $table->decimal('price', 12, 2)->nullable();
                $table->string('currency', 10)->nullable();
                $table->string('zoho_hostedpage_id')->nullable()->index();
                $table->text('zoho_hostedpage_url')->nullable();
                $table->string('zoho_hostedpage_status')->nullable();
                $table->string('zoho_subscription_id')->nullable();
                $table->string('zoho_invoice_id')->nullable();
                $table->string('zoho_payment_id')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('circle_join_payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('circle_join_payments', 'provider')) {
                $table->string('provider', 30)->default('zoho');
            }
            if (! Schema::hasColumn('circle_join_payments', 'status')) {
                $table->string('status', 40)->default('pending');
            }
            if (! Schema::hasColumn('circle_join_payments', 'duration_months')) {
                $table->unsignedSmallInteger('duration_months')->nullable();
            }
            if (! Schema::hasColumn('circle_join_payments', 'price')) {
                $table->decimal('price', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('circle_join_payments', 'currency')) {
                $table->string('currency', 10)->nullable();
            }
            if (! Schema::hasColumn('circle_join_payments', 'zoho_hostedpage_id')) {
                $table->string('zoho_hostedpage_id')->nullable();
            }
            if (! Schema::hasColumn('circle_join_payments', 'zoho_hostedpage_url')) {
                $table->text('zoho_hostedpage_url')->nullable();
            }
            if (! Schema::hasColumn('circle_join_payments', 'zoho_hostedpage_status')) {
                $table->string('zoho_hostedpage_status')->nullable();
            }
            if (! Schema::hasColumn('circle_join_payments', 'zoho_subscription_id')) {
                $table->string('zoho_subscription_id')->nullable();
            }
            if (! Schema::hasColumn('circle_join_payments', 'zoho_invoice_id')) {
                $table->string('zoho_invoice_id')->nullable();
            }
            if (! Schema::hasColumn('circle_join_payments', 'zoho_payment_id')) {
                $table->string('zoho_payment_id')->nullable();
            }
            if (! Schema::hasColumn('circle_join_payments', 'payload')) {
                $table->json('payload')->nullable();
            }
            if (! Schema::hasColumn('circle_join_payments', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
            if (! Schema::hasColumn('circle_join_payments', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive.
    }
};
