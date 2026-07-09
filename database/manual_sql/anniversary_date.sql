-- ============================================================
-- GREENPRENEUR — New Anniversary Date SQL Script
-- Date: 2026-07-08
-- Run manually on local and production PostgreSQL databases.
-- NO migrations used.
-- ============================================================

-- 1. Add the anniversary_date column to the users table if it does not exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS anniversary_date DATE NULL;

-- 2. Add an index for query optimization on the daily scheduler anniversary check
CREATE INDEX IF NOT EXISTS users_anniversary_date_month_day_idx 
ON users (EXTRACT(month FROM anniversary_date), EXTRACT(day FROM anniversary_date)) 
WHERE anniversary_date IS NOT NULL;

-- ============================================================
-- VERIFICATION QUERY
-- Run this query to verify that the column and index have been added
-- ============================================================
SELECT column_name, data_type, is_nullable
FROM information_schema.columns
WHERE table_name = 'users'
  AND column_name = 'anniversary_date';

-- Verification for Index
SELECT indexname, indexdef
FROM pg_indexes
WHERE tablename = 'users' AND indexname = 'users_anniversary_date_month_day_idx';
