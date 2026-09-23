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
        if (! Schema::hasTable('activity_videos')) {
            Schema::create('activity_videos', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('activity_key', 100)->unique();
                $table->string('activity_name', 255);
                $table->string('video_type', 20)->default('youtube'); // 'youtube' or 'file'
                $table->text('video_url')->nullable();
                $table->uuid('file_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active', 'activity_key']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_videos');
    }
};
