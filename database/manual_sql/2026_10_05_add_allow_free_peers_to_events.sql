-- ==========================================================
-- Add allow_free_peers column to events table
-- ==========================================================

-- PostgreSQL:
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'events' AND column_name = 'allow_free_peers'
    ) THEN
        ALTER TABLE events ADD COLUMN allow_free_peers BOOLEAN DEFAULT TRUE;
        COMMENT ON COLUMN events.allow_free_peers IS 'If true, free peers can attend without Pro membership. If false, Pro membership is required.';
    END IF;
END $$;

-- MySQL (if used):
-- ALTER TABLE `events` ADD COLUMN `allow_free_peers` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'If true, free peers can attend without Pro membership. If false, Pro membership is required.' AFTER `visitor_registration_enabled`;
