<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'membership_starts_at')) {
                $table->timestampTz('membership_starts_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'membership_ends_at')) {
                $table->timestampTz('membership_ends_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'membership_approved_at')) {
                $table->timestampTz('membership_approved_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'membership_approved_by')) {
                $table->uuid('membership_approved_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Intentionally left blank so rollback never drops pre-existing membership columns.
    }
};
