<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("DO $$ BEGIN IF to_regtype('notification_type_enum') IS NOT NULL THEN ALTER TYPE notification_type_enum ADD VALUE IF NOT EXISTS 'referral_joined'; END IF; END $$;");
        DB::statement('ALTER TABLE users ADD COLUMN IF NOT EXISTS referral_code VARCHAR(50)');
        DB::statement('ALTER TABLE users ADD COLUMN IF NOT EXISTS referred_by UUID NULL');
        DB::statement("UPDATE users SET referral_code = 'PGU' || UPPER(SUBSTRING(REPLACE(id::text, '-', ''), 1, 8)) WHERE referral_code IS NULL OR referral_code = ''");
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS users_referral_code_unique ON users (LOWER(referral_code)) WHERE referral_code IS NOT NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS users_referred_by_index ON users(referred_by)');

        DB::statement('ALTER TABLE notifications ADD COLUMN IF NOT EXISTS data JSONB NULL');
        DB::statement('ALTER TABLE notifications ADD COLUMN IF NOT EXISTS title VARCHAR(255) NULL');
        DB::statement('ALTER TABLE notifications ADD COLUMN IF NOT EXISTS message TEXT NULL');
        DB::statement('ALTER TABLE notifications ADD COLUMN IF NOT EXISTS source_type VARCHAR(100) NULL');
        DB::statement('ALTER TABLE notifications ADD COLUMN IF NOT EXISTS source_id UUID NULL');
        DB::statement('ALTER TABLE notifications ADD COLUMN IF NOT EXISTS source_event VARCHAR(100) NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS notifications_user_id_index ON notifications(user_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS notifications_type_index ON notifications(type)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS notifications_type_index');
        DB::statement('DROP INDEX IF EXISTS notifications_user_id_index');
        DB::statement('ALTER TABLE notifications DROP COLUMN IF EXISTS data');
        DB::statement('DROP INDEX IF EXISTS users_referred_by_index');
        DB::statement('DROP INDEX IF EXISTS users_referral_code_unique');
        DB::statement('ALTER TABLE users DROP COLUMN IF EXISTS referred_by');
        DB::statement('ALTER TABLE users DROP COLUMN IF EXISTS referral_code');
    }
};
