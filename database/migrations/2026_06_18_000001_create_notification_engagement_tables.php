<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_push_tokens')) {
            Schema::create('user_push_tokens', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->text('token')->unique();
                $table->string('platform')->nullable();
                $table->string('device_id')->nullable();
                $table->string('app_version')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->index(['user_id', 'is_active']);
            });
        } else {
            foreach ([
                'platform' => 'ALTER TABLE user_push_tokens ADD COLUMN IF NOT EXISTS platform VARCHAR(255) NULL',
                'device_id' => 'ALTER TABLE user_push_tokens ADD COLUMN IF NOT EXISTS device_id VARCHAR(255) NULL',
                'app_version' => 'ALTER TABLE user_push_tokens ADD COLUMN IF NOT EXISTS app_version VARCHAR(255) NULL',
                'is_active' => 'ALTER TABLE user_push_tokens ADD COLUMN IF NOT EXISTS is_active BOOLEAN NOT NULL DEFAULT TRUE',
                'last_used_at' => 'ALTER TABLE user_push_tokens ADD COLUMN IF NOT EXISTS last_used_at TIMESTAMP NULL',
            ] as $sql) {
                DB::statement($sql);
            }
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS user_push_tokens_token_unique_idx ON user_push_tokens (token)');
            DB::statement('CREATE INDEX IF NOT EXISTS user_push_tokens_user_active_idx ON user_push_tokens (user_id, is_active)');
        }

        if (! Schema::hasTable('notification_campaigns')) {
            Schema::create('notification_campaigns', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('category');
                $table->text('description')->nullable();
                $table->string('channel')->default('push');
                $table->string('trigger_type');
                $table->string('frequency')->nullable();
                $table->string('priority')->default('medium');
                $table->string('audience_type')->nullable();
                $table->string('title_template');
                $table->text('body_template');
                $table->string('email_subject_template')->nullable();
                $table->text('email_body_template')->nullable();
                $table->string('tap_screen')->nullable();
                $table->text('stop_rule')->nullable();
                $table->integer('daily_limit')->nullable();
                $table->integer('cooldown_hours')->nullable();
                $table->boolean('is_active')->default(true);
                $table->jsonb('config')->default(DB::raw("'{}'::jsonb"));
                $table->uuid('created_by_user_id')->nullable();
                $table->timestamps();
                $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
                $table->index(['category', 'is_active']);
                $table->index(['trigger_type', 'is_active']);
            });
        }

        if (! Schema::hasTable('app_notifications')) {
            Schema::create('app_notifications', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->uuid('campaign_id')->nullable();
                $table->string('type');
                $table->string('category')->nullable();
                $table->string('title');
                $table->text('body');
                $table->string('channel')->default('push');
                $table->string('priority')->default('medium');
                $table->string('reference_type')->nullable();
                $table->uuid('reference_id')->nullable();
                $table->string('screen')->nullable();
                $table->jsonb('data')->default(DB::raw("'{}'::jsonb"));
                $table->string('dedupe_key')->nullable();
                $table->string('status')->default('pending');
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('clicked_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->text('failure_reason')->nullable();
                $table->timestamps();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('campaign_id')->references('id')->on('notification_campaigns')->nullOnDelete();
                $table->index(['user_id', 'read_at', 'created_at']);
                $table->index(['type', 'status']);
                $table->index('campaign_id');
                $table->index('dedupe_key');
                $table->unique(['user_id', 'dedupe_key'], 'app_notifications_user_dedupe_unique');
            });
        }

        if (! Schema::hasTable('notification_campaign_runs')) {
            Schema::create('notification_campaign_runs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('campaign_id');
                $table->string('run_type')->default('scheduled');
                $table->string('status')->default('running');
                $table->integer('audience_count')->default(0);
                $table->integer('sent_count')->default(0);
                $table->integer('failed_count')->default(0);
                $table->integer('skipped_count')->default(0);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->jsonb('meta')->default(DB::raw("'{}'::jsonb"));
                $table->timestamps();
                $table->foreign('campaign_id')->references('id')->on('notification_campaigns')->cascadeOnDelete();
                $table->index(['campaign_id', 'created_at']);
                $table->index('status');
            });
        }

        if (! Schema::hasTable('notification_delivery_logs')) {
            Schema::create('notification_delivery_logs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('notification_id');
                $table->uuid('user_id');
                $table->string('channel');
                $table->string('provider')->nullable();
                $table->string('provider_message_id')->nullable();
                $table->string('status')->default('pending');
                $table->jsonb('request_payload')->default(DB::raw("'{}'::jsonb"));
                $table->jsonb('response_payload')->default(DB::raw("'{}'::jsonb"));
                $table->text('error_message')->nullable();
                $table->timestamp('attempted_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();
                $table->foreign('notification_id')->references('id')->on('app_notifications')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->index(['notification_id', 'channel']);
                $table->index(['user_id', 'status', 'created_at']);
            });
        }

        if (! Schema::hasTable('notification_preferences')) {
            Schema::create('notification_preferences', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->unique();
                foreach (['push', 'email', 'chat', 'event', 'circle', 'business', 'campaign'] as $name) {
                    $table->boolean($name.'_enabled')->default(true);
                }
                $table->time('quiet_hours_start')->nullable();
                $table->time('quiet_hours_end')->nullable();
                $table->jsonb('config')->default(DB::raw("'{}'::jsonb"));
                $table->timestamps();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('notification_suppression_logs')) {
            Schema::create('notification_suppression_logs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->uuid('campaign_id')->nullable();
                $table->string('type');
                $table->string('dedupe_key')->nullable();
                $table->timestamp('last_sent_at');
                $table->integer('send_count')->default(1);
                $table->timestamps();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('campaign_id')->references('id')->on('notification_campaigns')->nullOnDelete();
                $table->index(['user_id', 'type', 'last_sent_at']);
                $table->index('campaign_id');
                $table->index('dedupe_key');
                $table->unique(['user_id', 'type', 'dedupe_key'], 'notification_suppression_user_type_dedupe_unique');
            });
        }

        DB::statement('ALTER TABLE users ADD COLUMN IF NOT EXISTS last_app_opened_at TIMESTAMP NULL');
        DB::statement('ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_completed_percent SMALLINT NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE users ADD COLUMN IF NOT EXISTS membership_tier VARCHAR(255) NULL');
        DB::statement('ALTER TABLE users ADD COLUMN IF NOT EXISTS subscription_expires_at TIMESTAMP NULL');
    }

    public function down(): void {}
};
