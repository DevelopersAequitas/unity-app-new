<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TYPE circle_join_request_status_enum ADD VALUE IF NOT EXISTS 'paid'");
    }

    public function down(): void
    {
        // PostgreSQL enum values cannot be safely removed in down migration.
    }
};
