<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureUserPushTokens();
        $this->ensureCampaigns();
        $this->ensureAppNotifications();
        $this->ensureCampaignRuns();
        $this->ensureDeliveryLogs();
        $this->ensurePreferences();
        $this->ensureSuppressionLogs();
        $this->indexes();
    }

    public function down(): void
    {
        // Safe forward-only compatibility migration. Do not drop notification data.
    }

    private function ensureUserPushTokens(): void
    {
        if (! Schema::hasTable('user_push_tokens')) {
            Schema::create('user_push_tokens', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->index();
                $table->text('token')->unique();
                $table->string('platform')->nullable();
                $table->string('device_id')->nullable();
                $table->string('app_version')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('user_push_tokens', function (Blueprint $table): void {
            if (! Schema::hasColumn('user_push_tokens', 'platform')) {
                $table->string('platform')->nullable();
            }
            if (! Schema::hasColumn('user_push_tokens', 'device_id')) {
                $table->string('device_id')->nullable();
            }
            if (! Schema::hasColumn('user_push_tokens', 'app_version')) {
                $table->string('app_version')->nullable();
            }
            if (! Schema::hasColumn('user_push_tokens', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (! Schema::hasColumn('user_push_tokens', 'last_used_at')) {
                $table->timestamp('last_used_at')->nullable();
            }
        });
    }

    private function ensureCampaigns(): void
    {
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
            });
        }
    }

    private function ensureAppNotifications(): void
    {
        if (! Schema::hasTable('app_notifications')) {
            Schema::create('app_notifications', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->index();
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
            });
        }
    }

    private function ensureCampaignRuns(): void
    {
        if (! Schema::hasTable('notification_campaign_runs')) {
            Schema::create('notification_campaign_runs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('campaign_id')->index();
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
            });
        }
    }

    private function ensureDeliveryLogs(): void
    {
        if (! Schema::hasTable('notification_delivery_logs')) {
            Schema::create('notification_delivery_logs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('notification_id')->nullable();
                $table->uuid('user_id')->nullable();
                $table->uuid('campaign_id')->nullable();
                $table->string('channel')->default('push');
                $table->string('provider')->nullable();
                $table->string('provider_message_id')->nullable();
                $table->string('status')->default('pending');
                $table->jsonb('request_payload')->default(DB::raw("'{}'::jsonb"));
                $table->jsonb('response_payload')->default(DB::raw("'{}'::jsonb"));
                $table->text('error_message')->nullable();
                $table->timestamp('attempted_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('notification_delivery_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('notification_delivery_logs', 'campaign_id')) {
                $table->uuid('campaign_id')->nullable();
            }
            if (! Schema::hasColumn('notification_delivery_logs', 'notification_id')) {
                $table->uuid('notification_id')->nullable();
            }
            if (! Schema::hasColumn('notification_delivery_logs', 'user_id')) {
                $table->uuid('user_id')->nullable();
            }
        });
    }

    private function ensurePreferences(): void
    {
        if (! Schema::hasTable('notification_preferences')) {
            Schema::create('notification_preferences', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->unique();
                $table->boolean('push_enabled')->default(true);
                $table->boolean('email_enabled')->default(true);
                $table->boolean('chat_enabled')->default(true);
                $table->boolean('event_enabled')->default(true);
                $table->boolean('circle_enabled')->default(true);
                $table->boolean('business_enabled')->default(true);
                $table->boolean('campaign_enabled')->default(true);
                $table->time('quiet_hours_start')->nullable();
                $table->time('quiet_hours_end')->nullable();
                $table->jsonb('config')->default(DB::raw("'{}'::jsonb"));
                $table->timestamps();
            });
        }
    }

    private function ensureSuppressionLogs(): void
    {
        if (! Schema::hasTable('notification_suppression_logs')) {
            Schema::create('notification_suppression_logs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->index();
                $table->uuid('campaign_id')->nullable();
                $table->string('type');
                $table->string('dedupe_key')->nullable();
                $table->timestamp('last_sent_at');
                $table->integer('send_count')->default(1);
                $table->timestamps();
            });
        }
    }

    private function indexes(): void
    {
        foreach ([
            ['notification_campaigns', 'notification_campaigns_is_active_idx', 'is_active'],
            ['notification_campaigns', 'notification_campaigns_category_idx', 'category'],
            ['notification_campaigns', 'notification_campaigns_priority_idx', 'priority'],
            ['user_push_tokens', 'user_push_tokens_is_active_idx', 'is_active'],
            ['user_push_tokens', 'user_push_tokens_platform_idx', 'platform'],
            ['app_notifications', 'app_notifications_campaign_id_idx', 'campaign_id'],
            ['app_notifications', 'app_notifications_type_idx', 'type'],
            ['app_notifications', 'app_notifications_status_idx', 'status'],
            ['app_notifications', 'app_notifications_category_idx', 'category'],
            ['app_notifications', 'app_notifications_priority_idx', 'priority'],
            ['app_notifications', 'app_notifications_read_at_idx', 'read_at'],
            ['app_notifications', 'app_notifications_clicked_at_idx', 'clicked_at'],
            ['app_notifications', 'app_notifications_created_at_idx', 'created_at'],
            ['notification_delivery_logs', 'notification_delivery_logs_user_id_idx', 'user_id'],
            ['notification_delivery_logs', 'notification_delivery_logs_campaign_id_idx', 'campaign_id'],
            ['notification_delivery_logs', 'notification_delivery_logs_status_idx', 'status'],
            ['notification_delivery_logs', 'notification_delivery_logs_created_at_idx', 'created_at'],
        ] as [$table, $index, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                DB::statement(sprintf('CREATE INDEX IF NOT EXISTS %s ON %s (%s)', $index, $table, $column));
            }
        }
    }
};
