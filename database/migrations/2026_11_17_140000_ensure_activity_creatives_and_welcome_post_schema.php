<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_creatives')) {
            Schema::create('activity_creatives', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->index();
                $table->uuid('post_id')->nullable()->index();
                $table->string('activity_type', 100)->index();
                $table->string('activity_id', 100)->nullable()->index();
                $table->string('title', 255)->nullable();
                $table->text('description')->nullable();
                $table->uuid('creative_file_id')->nullable();
                $table->string('creative_url', 500)->nullable();
                $table->string('status', 50)->default('active');
                $table->json('meta')->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            Schema::table('activity_creatives', function (Blueprint $table): void {
                if (! Schema::hasColumn('activity_creatives', 'post_id')) {
                    $table->uuid('post_id')->nullable()->index();
                }
                if (! Schema::hasColumn('activity_creatives', 'activity_type')) {
                    $table->string('activity_type', 100)->default('welcome')->index();
                }
                if (! Schema::hasColumn('activity_creatives', 'activity_id')) {
                    $table->string('activity_id', 100)->nullable()->index();
                }
                if (! Schema::hasColumn('activity_creatives', 'title')) {
                    $table->string('title', 255)->nullable();
                }
                if (! Schema::hasColumn('activity_creatives', 'description')) {
                    $table->text('description')->nullable();
                }
                if (! Schema::hasColumn('activity_creatives', 'creative_file_id')) {
                    $table->uuid('creative_file_id')->nullable();
                }
                if (! Schema::hasColumn('activity_creatives', 'creative_url')) {
                    $table->string('creative_url', 500)->nullable();
                }
                if (! Schema::hasColumn('activity_creatives', 'status')) {
                    $table->string('status', 50)->default('active');
                }
                if (! Schema::hasColumn('activity_creatives', 'meta')) {
                    $table->json('meta')->nullable();
                }
                if (! Schema::hasColumn('activity_creatives', 'created_by')) {
                    $table->uuid('created_by')->nullable();
                }
                if (! Schema::hasColumn('activity_creatives', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        if (! Schema::hasTable('post_mentions')) {
            Schema::create('post_mentions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('post_id')->index();
                $table->uuid('peer_id')->index();
                $table->timestamps();
                $table->unique(['post_id', 'peer_id']);
            });
        }

        if (Schema::hasTable('posts')) {
            Schema::table('posts', function (Blueprint $table): void {
                if (! Schema::hasColumn('posts', 'source_type')) {
                    $table->string('source_type', 100)->nullable()->index();
                }
                if (! Schema::hasColumn('posts', 'source_id')) {
                    $table->uuid('source_id')->nullable()->index();
                }
                if (! Schema::hasColumn('posts', 'source_event')) {
                    $table->string('source_event', 100)->nullable();
                }
                if (! Schema::hasColumn('posts', 'post_type')) {
                    $table->string('post_type', 100)->nullable()->index();
                }
                if (! Schema::hasColumn('posts', 'title')) {
                    $table->string('title', 255)->nullable();
                }
                if (! Schema::hasColumn('posts', 'description')) {
                    $table->text('description')->nullable();
                }
                if (! Schema::hasColumn('posts', 'image')) {
                    $table->string('image', 500)->nullable();
                }
                if (! Schema::hasColumn('posts', 'status')) {
                    $table->string('status', 50)->default('active');
                }
                if (! Schema::hasColumn('posts', 'active')) {
                    $table->boolean('active')->default(true);
                }
                if (! Schema::hasColumn('posts', 'is_deleted')) {
                    $table->boolean('is_deleted')->default(false);
                }
                if (! Schema::hasColumn('posts', 'moderation_status')) {
                    $table->string('moderation_status', 50)->default('approved');
                }
                if (! Schema::hasColumn('posts', 'visibility')) {
                    $table->string('visibility', 50)->default('public');
                }
                if (! Schema::hasColumn('posts', 'sponsored')) {
                    $table->boolean('sponsored')->default(false);
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (! Schema::hasColumn('users', 'welcome_creative_url')) {
                    $table->string('welcome_creative_url', 500)->nullable();
                }
                if (! Schema::hasColumn('users', 'profile_card_image_url')) {
                    $table->string('profile_card_image_url', 500)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Non-destructive safe migration
    }
};
