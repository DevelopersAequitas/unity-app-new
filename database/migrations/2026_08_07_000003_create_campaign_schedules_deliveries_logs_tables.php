<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('campaign_schedules')) {
            Schema::create('campaign_schedules', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('campaign_id');
                $table->string('schedule_type');
                $table->date('start_date');
                $table->string('end_type')->default('never');
                $table->date('end_date')->nullable();
                $table->string('send_time');
                $table->string('timezone');
                $table->string('recurrence_type')->nullable();
                $table->integer('frequency_interval')->nullable();
                $table->string('weekdays')->nullable();
                $table->string('monthly_basis')->nullable();
                $table->integer('monthly_day_of_month')->nullable();
                $table->string('monthly_position')->nullable();
                $table->string('monthly_day_of_week')->nullable();
                $table->integer('yearly_month')->nullable();
                $table->integer('yearly_day')->nullable();
                $table->string('custom_unit')->nullable();
                $table->integer('cycle_send_days')->nullable();
                $table->integer('cycle_pause_days')->nullable();
                $table->timestamp('next_run_at')->nullable();
                $table->timestamp('last_run_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('campaign_deliveries')) {
            Schema::create('campaign_deliveries', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('campaign_id');
                $table->uuid('schedule_id')->nullable();
                $table->string('status')->default('scheduled');
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->integer('total_recipients')->default(0);
                $table->integer('total_email_sent')->default(0);
                $table->integer('total_notification_sent')->default(0);
                $table->integer('total_failed')->default(0);
                $table->text('error_message')->nullable();
                $table->string('batch_id')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('campaign_logs')) {
            Schema::create('campaign_logs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('delivery_id');
                $table->uuid('user_id');
                $table->string('email')->nullable();
                $table->string('email_status')->default('queued');
                $table->string('notification_status')->default('queued');
                $table->boolean('email_sent')->default(false);
                $table->boolean('notification_sent')->default(false);
                $table->text('error_message')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_logs');
        Schema::dropIfExists('campaign_deliveries');
        Schema::dropIfExists('campaign_schedules');
    }
};
