<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_galleries') || ! Schema::hasColumn('event_galleries', 'category_id')) {
            return;
        }

        Schema::table('event_galleries', function (Blueprint $table): void {
            try {
                $table->dropForeign(['category_id']);
            } catch (\Throwable $exception) {
                // ignore when foreign key does not exist
            }

            try {
                $table->dropIndex(['category_id']);
            } catch (\Throwable $exception) {
                // ignore when index does not exist
            }

            $table->dropColumn('category_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_galleries') || Schema::hasColumn('event_galleries', 'category_id')) {
            return;
        }

        Schema::table('event_galleries', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->index('category_id');
        });
    }
};
