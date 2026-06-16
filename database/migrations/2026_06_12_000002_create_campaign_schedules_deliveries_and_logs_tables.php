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
        if (!Schema::hasTable('campaign_schedules')) {
            Schema::create('campaign_schedules', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('campaign_id');
                $table->string('schedule_type', 50);
                $table->date('start_date');
                $table->string('end_type', 50)->default('never');
                $table->date('end_date')->nullable();
                $table->time('send_time');
                $table->string('timezone', 100)->default('UTC');
                $table->string('recurrence_type', 50)->nullable();
                $table->integer('frequency_interval')->nullable();
                $table->string('weekdays', 100)->nullable();
                $table->string('monthly_basis', 50)->nullable();
                $table->integer('monthly_day_of_month')->nullable();
                $table->string('monthly_position', 50)->nullable();
                $table->string('monthly_day_of_week', 50)->nullable();
                $table->integer('yearly_month')->nullable();
                $table->integer('yearly_day')->nullable();
                $table->string('custom_unit', 50)->nullable();
                $table->integer('cycle_send_days')->nullable();
                $table->integer('cycle_pause_days')->nullable();
                $table->timestamp('next_run_at')->nullable();
                $table->timestamp('last_run_at')->nullable();
                $table->timestamps();

                $table->foreign('campaign_id')->references('id')->on('admin_campaigns')->onDelete('cascade');
                $table->index('next_run_at');
                $table->index('campaign_id');
            });
        }

        if (!Schema::hasTable('campaign_deliveries')) {
            Schema::create('campaign_deliveries', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('campaign_id');
                $table->uuid('schedule_id')->nullable();
                $table->string('status', 50)->default('scheduled');
                $table->integer('total_recipients')->default(0);
                $table->integer('total_email_sent')->default(0);
                $table->integer('total_notification_sent')->default(0);
                $table->integer('total_failed')->default(0);
                $table->text('error_message')->nullable();
                $table->string('batch_id', 255)->nullable();
                $table->timestamp('scheduled_at');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->foreign('campaign_id')->references('id')->on('admin_campaigns')->onDelete('cascade');
                $table->foreign('schedule_id')->references('id')->on('campaign_schedules')->onDelete('set null');
                $table->index('campaign_id');
                $table->index('status');
            });
        }

        if (!Schema::hasTable('campaign_logs')) {
            Schema::create('campaign_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('delivery_id');
                $table->uuid('user_id');
                $table->string('email', 255)->nullable();
                $table->string('email_status', 50)->default('pending');
                $table->string('notification_status', 50)->default('pending');
                $table->boolean('email_sent')->default(false);
                $table->boolean('notification_sent')->default(false);
                $table->text('error_message')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->foreign('delivery_id')->references('id')->on('campaign_deliveries')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->unique(['delivery_id', 'user_id']);
                $table->index('delivery_id');
                $table->index('user_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_logs');
        Schema::dropIfExists('campaign_deliveries');
        Schema::dropIfExists('campaign_schedules');
    }
};
