-- Manual PostgreSQL SQL to add missing columns to ads table
-- Run manually without Laravel migrations

ALTER TABLE ads ADD COLUMN IF NOT EXISTS placement VARCHAR(50) NULL;
ALTER TABLE ads ADD COLUMN IF NOT EXISTS timeline_position INTEGER NULL;
ALTER TABLE ads ADD COLUMN IF NOT EXISTS sort_order INTEGER DEFAULT 0 NOT NULL;
