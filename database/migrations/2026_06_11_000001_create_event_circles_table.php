<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('event_circles')) {
            return;
        }

        Schema::create('event_circles', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('event_id');
            $table->uuid('circle_id');
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('circle_id')->references('id')->on('circles')->cascadeOnDelete();
            $table->unique(['event_id', 'circle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_circles');
    }
};
