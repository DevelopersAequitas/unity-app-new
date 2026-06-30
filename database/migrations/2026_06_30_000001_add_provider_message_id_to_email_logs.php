<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_logs')) {
            return;
        }

        DB::statement('ALTER TABLE email_logs ADD COLUMN IF NOT EXISTS provider VARCHAR(100) NULL');
        DB::statement('ALTER TABLE email_logs ADD COLUMN IF NOT EXISTS message_id VARCHAR(255) NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_logs')) {
            return;
        }

        DB::statement('ALTER TABLE email_logs DROP COLUMN IF EXISTS message_id');
        DB::statement('ALTER TABLE email_logs DROP COLUMN IF EXISTS provider');
    }
};
