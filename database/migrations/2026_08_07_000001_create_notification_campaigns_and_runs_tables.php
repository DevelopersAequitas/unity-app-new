<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_campaigns')) {
            Schema::create('notification_campaigns', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('category')->nullable();
                $table->text('description')->nullable();
                $table->string('channel')->default('push');
                $table->string('trigger_type')->nullable();
                $table->string('frequency')->nullable();
                $table->string('priority')->default('medium');
                $table->string('audience_type')->nullable();
                $table->text('title_template')->nullable();
                $table->text('body_template')->nullable();
                $table->text('email_subject_template')->nullable();
                $table->text('email_body_template')->nullable();
                $table->string('tap_screen')->nullable();
                $table->string('stop_rule')->nullable();
                $table->integer('daily_limit')->default(0);
                $table->integer('cooldown_hours')->default(0);
                $table->boolean('is_active')->default(true);
                $table->json('config')->nullable();
                $table->uuid('created_by_user_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('notification_campaign_runs')) {
            Schema::create('notification_campaign_runs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('campaign_id');
                $table->string('run_type')->default('scheduled');
                $table->string('status')->default('pending');
                $table->integer('audience_count')->default(0);
                $table->integer('sent_count')->default(0);
                $table->integer('failed_count')->default(0);
                $table->integer('skipped_count')->default(0);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->foreign('campaign_id')->references('id')->on('notification_campaigns')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_campaign_runs');
        Schema::dropIfExists('notification_campaigns');
    }
};
