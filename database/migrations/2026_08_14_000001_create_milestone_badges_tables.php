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
        if (!Schema::hasTable('milestone_badges')) {
            Schema::create('milestone_badges', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('type', 50);
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->bigInteger('required_count')->default(0);
                $table->string('badge_image_url', 2000)->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('user_milestone_badges')) {
            Schema::create('user_milestone_badges', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->uuid('badge_id');
                $table->string('milestone_type', 50);
                $table->bigInteger('achieved_count')->default(0);
                $table->string('status', 50)->default('earned');
                $table->timestamp('earned_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                // Add foreign key or indexes if needed
                $table->index('user_id');
                $table->index('badge_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_milestone_badges');
        Schema::dropIfExists('milestone_badges');
    }
};
