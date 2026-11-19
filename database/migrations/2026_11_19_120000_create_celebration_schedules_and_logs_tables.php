<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (! Schema::hasColumn('users', 'dob')) {
                    $table->date('dob')->nullable();
                }
                if (! Schema::hasColumn('users', 'anniversary_date')) {
                    $table->date('anniversary_date')->nullable();
                }
            });
        }

        if (Schema::hasTable('posts')) {
            Schema::table('posts', function (Blueprint $table): void {
                if (! Schema::hasColumn('posts', 'post_type')) {
                    $table->string('post_type', 50)->nullable()->default('standard');
                }
                if (! Schema::hasColumn('posts', 'template_id')) {
                    $table->uuid('template_id')->nullable();
                }
                if (! Schema::hasColumn('posts', 'title')) {
                    $table->string('title', 255)->nullable();
                }
                if (! Schema::hasColumn('posts', 'description')) {
                    $table->text('description')->nullable();
                }
                if (! Schema::hasColumn('posts', 'image')) {
                    $table->text('image')->nullable();
                }
                if (! Schema::hasColumn('posts', 'status')) {
                    $table->string('status', 50)->nullable()->default('active');
                }
                if (! Schema::hasColumn('posts', 'active')) {
                    $table->boolean('active')->default(true);
                }
            });
        }

        if (! Schema::hasTable('anniversary_templates')) {
            Schema::create('anniversary_templates', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('image_path', 255);
                $table->text('message')->nullable();
                $table->boolean('is_active')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('celebration_schedules')) {
            Schema::create('celebration_schedules', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->index();
                $table->string('event_type', 32); // 'birthday' | 'anniversary'
                $table->smallInteger('event_year');
                $table->date('source_date');
                $table->date('scheduled_date');
                $table->timestamp('scheduled_for_at'); // Exact 07:00:00 Asia/Kolkata stored in app/UTC timestamp
                $table->string('timezone', 64)->default('Asia/Kolkata');
                $table->integer('milestone_years')->nullable();
                $table->string('status', 32)->default('pending'); // pending | processing | published | failed | cancelled
                $table->text('custom_message')->nullable();
                $table->uuid('post_id')->nullable()->index();
                $table->uuid('creative_file_id')->nullable();
                $table->text('creative_url')->nullable();
                $table->uuid('notification_id')->nullable();
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->unsignedSmallInteger('max_attempts')->default(3);
                $table->timestamp('last_attempted_at')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->string('cancellation_reason', 255)->nullable();
                $table->text('last_error')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(
                    ['user_id', 'event_type', 'event_year'],
                    'celebration_schedules_user_event_year_unique'
                );
                $table->index(['status', 'scheduled_date'], 'celebration_schedules_status_date_idx');
                $table->index(['event_type', 'event_year'], 'celebration_schedules_type_year_idx');
                $table->index(['user_id', 'status'], 'celebration_schedules_user_status_idx');
            });
        }

        if (! Schema::hasTable('celebration_publish_logs')) {
            Schema::create('celebration_publish_logs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('celebration_schedule_id')->nullable()->index();
                $table->uuid('user_id')->index();
                $table->string('event_type', 32);
                $table->smallInteger('event_year');
                $table->string('action', 64);
                $table->string('status', 32)->default('info'); // info | success | warning | failed
                $table->unsignedSmallInteger('attempt_number')->default(1);
                $table->uuid('post_id')->nullable();
                $table->uuid('creative_file_id')->nullable();
                $table->uuid('notification_id')->nullable();
                $table->text('message')->nullable();
                $table->text('error_details')->nullable();
                $table->json('context')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['event_type', 'created_at'], 'celebration_logs_type_created_idx');
                $table->index(['status', 'created_at'], 'celebration_logs_status_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('celebration_publish_logs');
        Schema::dropIfExists('celebration_schedules');
    }
};
