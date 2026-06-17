<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_popup_views')) {
            return;
        }

        Schema::create('event_popup_views', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->uuid('event_id')->index();
            $table->unsignedInteger('popup_version');
            $table->timestamp('seen_at');
            $table->timestamps();
            $table->unique(['user_id', 'event_id', 'popup_version'], 'event_popup_views_user_event_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_popup_views');
    }
};
