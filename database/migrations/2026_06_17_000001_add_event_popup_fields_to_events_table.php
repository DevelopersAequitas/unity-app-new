<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'show_popup')) {
                $table->boolean('show_popup')->default(false)->index();
            }
            if (! Schema::hasColumn('events', 'realtime_popup')) {
                $table->boolean('realtime_popup')->default(false)->index();
            }
            if (! Schema::hasColumn('events', 'popup_title')) {
                $table->string('popup_title')->nullable();
            }
            if (! Schema::hasColumn('events', 'popup_message')) {
                $table->text('popup_message')->nullable();
            }
            if (! Schema::hasColumn('events', 'popup_action_url')) {
                $table->string('popup_action_url')->nullable();
            }
            if (! Schema::hasColumn('events', 'popup_last_triggered_at')) {
                $table->timestamp('popup_last_triggered_at')->nullable();
            }
            if (! Schema::hasColumn('events', 'popup_version')) {
                $table->unsignedInteger('popup_version')->default(1);
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            foreach (['show_popup', 'realtime_popup', 'popup_title', 'popup_message', 'popup_action_url', 'popup_last_triggered_at', 'popup_version'] as $column) {
                if (Schema::hasColumn('events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
