<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table): void {
                if (! Schema::hasColumn('events', 'status')) {
                    $table->string('status', 50)->default('published')->nullable()->index();
                }
            });

            // Backfill existing events with status = 'published' if null
            try {
                DB::table('events')->whereNull('status')->update(['status' => 'published']);
            } catch (\Throwable) {
                // Ignore if column backfill cannot be run
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('events') && Schema::hasColumn('events', 'status')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->dropColumn('status');
            });
        }
    }
};
