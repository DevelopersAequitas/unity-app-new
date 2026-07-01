<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        \App\Support\SqliteMigrator::run(<<<'SQL'
ALTER TYPE notification_type_enum ADD VALUE IF NOT EXISTS 'p2p_meeting_request';
ALTER TYPE notification_type_enum ADD VALUE IF NOT EXISTS 'p2p_meeting_accepted';
ALTER TYPE notification_type_enum ADD VALUE IF NOT EXISTS 'p2p_meeting_rejected';
ALTER TYPE notification_type_enum ADD VALUE IF NOT EXISTS 'p2p_meeting_cancelled';
SQL);
    }

    public function down(): void
    {
        // PostgreSQL enum values are not removed in down migration.
    }
};
