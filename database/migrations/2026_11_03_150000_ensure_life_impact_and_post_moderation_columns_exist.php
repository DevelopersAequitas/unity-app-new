<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'life_impacted_count')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->integer('life_impacted_count')->default(0);
            });
        }

        if (! Schema::hasTable('life_impact_histories')) {
            Schema::create('life_impact_histories', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->index();
                $table->uuid('triggered_by_user_id')->nullable()->index();
                $table->string('activity_type', 100)->nullable()->index();
                $table->uuid('activity_id')->nullable()->index();
                $table->integer('impact_value')->default(0);
                $table->integer('impact_after')->nullable();
                $table->integer('life_impacted')->nullable();
                $table->boolean('counted_in_total')->default(true);
                $table->string('impact_category', 100)->nullable();
                $table->string('action_key', 100)->nullable();
                $table->string('action_label', 255)->nullable();
                $table->text('remarks')->nullable();
                $table->string('status', 50)->default('approved');
                $table->string('title', 255)->nullable();
                $table->text('description')->nullable();
                $table->jsonb('meta')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('posts')) {
            Schema::table('posts', function (Blueprint $table): void {
                if (! Schema::hasColumn('posts', 'status')) {
                    $table->string('status', 50)->default('active');
                }
                if (! Schema::hasColumn('posts', 'active')) {
                    $table->boolean('active')->default(true);
                }
                if (! Schema::hasColumn('posts', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
                if (! Schema::hasColumn('posts', 'deactivation_remarks')) {
                    $table->text('deactivation_remarks')->nullable();
                }
                if (! Schema::hasColumn('posts', 'deactivated_by')) {
                    $table->uuid('deactivated_by')->nullable();
                }
                if (! Schema::hasColumn('posts', 'deactivated_at')) {
                    $table->timestamp('deactivated_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('coins_ledger')) {
            Schema::table('coins_ledger', function (Blueprint $table): void {
                if (! Schema::hasColumn('coins_ledger', 'remark')) {
                    $table->text('remark')->nullable();
                }
                if (! Schema::hasColumn('coins_ledger', 'entry_type')) {
                    $table->string('entry_type', 60)->nullable();
                }
                if (! Schema::hasColumn('coins_ledger', 'bucket')) {
                    $table->string('bucket', 60)->nullable();
                }
                if (! Schema::hasColumn('coins_ledger', 'reference_type')) {
                    $table->string('reference_type', 60)->nullable();
                }
                if (! Schema::hasColumn('coins_ledger', 'reference_id')) {
                    $table->uuid('reference_id')->nullable()->index();
                }
                if (! Schema::hasColumn('coins_ledger', 'source_type')) {
                    $table->string('source_type', 60)->nullable();
                }
                if (! Schema::hasColumn('coins_ledger', 'source_id')) {
                    $table->uuid('source_id')->nullable()->index();
                }
                if (! Schema::hasColumn('coins_ledger', 'metadata')) {
                    $table->jsonb('metadata')->nullable();
                }
            });
        }

        if (! Schema::hasTable('app_notifications')) {
            Schema::create('app_notifications', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->index();
                $table->uuid('campaign_id')->nullable();
                $table->string('type', 100)->index();
                $table->string('category', 100)->nullable();
                $table->string('title', 255)->nullable();
                $table->text('body')->nullable();
                $table->string('channel', 50)->default('push');
                $table->string('priority', 50)->default('normal');
                $table->string('reference_type', 100)->nullable();
                $table->string('reference_id', 100)->nullable()->index();
                $table->string('screen', 150)->nullable();
                $table->string('image_url', 500)->nullable();
                $table->jsonb('data')->nullable();
                $table->string('dedupe_key', 191)->nullable();
                $table->string('status', 50)->default('sent');
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('clicked_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->text('failure_reason')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Non-destructive safe migration
    }
};
