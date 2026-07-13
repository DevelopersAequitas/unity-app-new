<?php

declare(strict_types=1);

use App\Support\SqliteMigrator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        SqliteMigrator::run(<<<'SQL'
ALTER TYPE notification_type_enum ADD VALUE IF NOT EXISTS 'coin_claim_submitted';
ALTER TYPE notification_type_enum ADD VALUE IF NOT EXISTS 'coin_claim_approved';
ALTER TYPE notification_type_enum ADD VALUE IF NOT EXISTS 'coin_claim_rejected';
ALTER TYPE notification_type_enum ADD VALUE IF NOT EXISTS 'requirement_interest';
ALTER TYPE notification_type_enum ADD VALUE IF NOT EXISTS 'requirement_created';
ALTER TYPE notification_type_enum ADD VALUE IF NOT EXISTS 'impact_submitted';
ALTER TYPE notification_type_enum ADD VALUE IF NOT EXISTS 'impact_approved';
ALTER TYPE notification_type_enum ADD VALUE IF NOT EXISTS 'impact_rejected';
ALTER TYPE notification_type_enum ADD VALUE IF NOT EXISTS 'connection_request_pending_reminder';
ALTER TYPE notification_type_enum ADD VALUE IF NOT EXISTS 'circular';
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // PostgreSQL does not support removing enum values safely.
    }
};
