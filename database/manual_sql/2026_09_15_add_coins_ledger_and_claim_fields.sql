-- ==============================================================================
-- Manual SQL for Coins Ledger and Coin Claims Enhancements
-- Date: 2026-09-15
-- Run this script in PostgreSQL (pgAdmin, DBeaver, or psql)
-- ==============================================================================

-- 1. Add source tracking columns to coins_ledger table
ALTER TABLE coins_ledger ADD COLUMN IF NOT EXISTS source_type VARCHAR(100) DEFAULT NULL;
ALTER TABLE coins_ledger ADD COLUMN IF NOT EXISTS source_id VARCHAR(255) DEFAULT NULL;
ALTER TABLE coins_ledger ADD COLUMN IF NOT EXISTS remark TEXT DEFAULT NULL;

-- 2. Add performance indexes
CREATE INDEX IF NOT EXISTS idx_coins_ledger_user_source ON coins_ledger (user_id, source_type, source_id);
CREATE INDEX IF NOT EXISTS idx_users_introduced_by ON users (introduced_by);

-- 3. Ensure coin_claim_requests has all approval/rejection tracking columns
ALTER TABLE coin_claim_requests ADD COLUMN IF NOT EXISTS reviewed_by_admin_id UUID DEFAULT NULL;
ALTER TABLE coin_claim_requests ADD COLUMN IF NOT EXISTS admin_notes TEXT DEFAULT NULL;
ALTER TABLE coin_claim_requests ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP DEFAULT NULL;
ALTER TABLE coin_claim_requests ADD COLUMN IF NOT EXISTS rejected_at TIMESTAMP DEFAULT NULL;
