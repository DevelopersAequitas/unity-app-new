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
        if (! Schema::hasTable('event_coupons')) {
            Schema::create('event_coupons', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('code', 50)->unique();
                $table->string('name', 255)->nullable();
                $table->text('description')->nullable();
                $table->enum('discount_type', ['full', 'percentage', 'fixed'])->default('percentage');
                $table->decimal('discount_value', 12, 2)->default(0.00);
                $table->integer('max_uses')->nullable();
                $table->integer('used_count')->default(0);
                $table->timestamp('valid_from')->nullable();
                $table->timestamp('valid_until')->nullable();
                $table->boolean('is_active')->default(true);
                $table->uuid('event_id')->nullable();
                $table->uuid('occurrence_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('event_id');
                $table->index('occurrence_id');
                $table->index('is_active');
            });
        }

        if (Schema::hasTable('event_registrations')) {
            Schema::table('event_registrations', function (Blueprint $table): void {
                if (! Schema::hasColumn('event_registrations', 'coupon_id')) {
                    $table->uuid('coupon_id')->nullable();
                }
                if (! Schema::hasColumn('event_registrations', 'coupon_code')) {
                    $table->string('coupon_code', 50)->nullable();
                }
                if (! Schema::hasColumn('event_registrations', 'original_amount')) {
                    $table->decimal('original_amount', 12, 2)->nullable();
                }
                if (! Schema::hasColumn('event_registrations', 'discount_amount')) {
                    $table->decimal('discount_amount', 12, 2)->nullable();
                }
            });
        }

        if (Schema::hasTable('event_registration_requests')) {
            Schema::table('event_registration_requests', function (Blueprint $table): void {
                if (! Schema::hasColumn('event_registration_requests', 'coupon_id')) {
                    $table->uuid('coupon_id')->nullable();
                }
                if (! Schema::hasColumn('event_registration_requests', 'coupon_code')) {
                    $table->string('coupon_code', 50)->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('event_registration_requests')) {
            Schema::table('event_registration_requests', function (Blueprint $table): void {
                $table->dropColumn(['coupon_id', 'coupon_code']);
            });
        }

        if (Schema::hasTable('event_registrations')) {
            Schema::table('event_registrations', function (Blueprint $table): void {
                $table->dropColumn(['coupon_id', 'coupon_code', 'original_amount', 'discount_amount']);
            });
        }

        Schema::dropIfExists('event_coupons');
    }
};
