<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('app_notifications')) {
            Schema::create('app_notifications', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->nullable();
                $table->string('type')->nullable();
                $table->string('category')->nullable();
                $table->string('title')->nullable();
                $table->text('body')->nullable();
                $table->text('message')->nullable();
                $table->string('channel')->nullable()->default('in_app');
                $table->string('priority')->nullable()->default('normal');
                $table->string('screen')->nullable();
                $table->jsonb('data')->nullable();
                $table->jsonb('payload')->nullable();
                $table->string('dedupe_key')->nullable();
                $table->string('status')->nullable()->default('sent');
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('app_notifications', function (Blueprint $table): void {
                if (! Schema::hasColumn('app_notifications', 'category')) {
                    $table->string('category')->nullable();
                }
                if (! Schema::hasColumn('app_notifications', 'title')) {
                    $table->string('title')->nullable();
                }
                if (! Schema::hasColumn('app_notifications', 'body')) {
                    $table->text('body')->nullable();
                }
                if (! Schema::hasColumn('app_notifications', 'message')) {
                    $table->text('message')->nullable();
                }
                if (! Schema::hasColumn('app_notifications', 'channel')) {
                    $table->string('channel')->nullable()->default('in_app');
                }
                if (! Schema::hasColumn('app_notifications', 'priority')) {
                    $table->string('priority')->nullable()->default('normal');
                }
                if (! Schema::hasColumn('app_notifications', 'screen')) {
                    $table->string('screen')->nullable();
                }
                if (! Schema::hasColumn('app_notifications', 'data')) {
                    $table->jsonb('data')->nullable();
                }
                if (! Schema::hasColumn('app_notifications', 'dedupe_key')) {
                    $table->string('dedupe_key')->nullable();
                }
                if (! Schema::hasColumn('app_notifications', 'status')) {
                    $table->string('status')->nullable()->default('sent');
                }
                if (! Schema::hasColumn('app_notifications', 'sent_at')) {
                    $table->timestamp('sent_at')->nullable();
                }
                if (! Schema::hasColumn('app_notifications', 'read_at')) {
                    $table->timestamp('read_at')->nullable();
                }
                if (! Schema::hasColumn('app_notifications', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (! Schema::hasColumn('app_notifications', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });
        }

        $this->ensureIndexes();
    }

    public function down(): void
    {
        // Forward-only safety migration. Do not drop columns or notification data.
    }

    private function ensureIndexes(): void
    {
        foreach ([
            'app_notifications_user_id_idx' => 'user_id',
            'app_notifications_type_idx' => 'type',
            'app_notifications_dedupe_key_idx' => 'dedupe_key',
        ] as $indexName => $column) {
            if (Schema::hasColumn('app_notifications', $column)) {
                DB::statement("CREATE INDEX IF NOT EXISTS {$indexName} ON app_notifications ({$column})");
            }
        }
    }
};
