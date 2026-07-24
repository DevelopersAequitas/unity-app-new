-- =============================================================================
-- Migration: Add Peer Unique ID system (peer_id) to users table
-- Target DB: PostgreSQL
-- Description: Idempotent & Production-safe script to introduce peer_id,
--              deterministically backfill existing users, enforce uniqueness,
--              set sequence defaults, and lock down NOT NULL.
-- Prefix: PG3182736
-- =============================================================================

-- 1. Create PostgreSQL sequence for Peer ID tracking if not exists
CREATE SEQUENCE IF NOT EXISTS peer_id_seq START WITH 1 INCREMENT BY 1 MINVALUE 1;

-- 2. Add peer_id column to users table safely if missing (initially NULLable)
ALTER TABLE users ADD COLUMN IF NOT EXISTS peer_id VARCHAR(50) NULL;

-- 3. Backfill existing users (including soft-deleted) where peer_id IS NULL
-- Deterministically ordered by created_at ASC NULLS LAST, id ASC
DO $$
DECLARE
    start_seq BIGINT := 0;
    r RECORD;
    curr_seq BIGINT;
    max_after_backfill BIGINT := 0;
BEGIN
    -- Determine current maximum numeric suffix among existing valid peer_ids matching ^PG3182736[0-9]+$
    SELECT COALESCE(MAX(CAST(SUBSTRING(peer_id FROM 10) AS BIGINT)), 0)
    INTO start_seq
    FROM users
    WHERE peer_id ~ '^PG3182736[0-9]+$';

    curr_seq := start_seq;

    -- Update all users where peer_id IS NULL (including soft-deleted rows)
    FOR r IN (
        SELECT id
        FROM users
        WHERE peer_id IS NULL
        ORDER BY created_at ASC NULLS LAST, id ASC
    ) LOOP
        curr_seq := curr_seq + 1;
        UPDATE users
        SET peer_id = 'PG3182736' || curr_seq
        WHERE id = r.id;
    END LOOP;

    -- Calculate the highest numeric sequence in database after backfill
    SELECT COALESCE(MAX(CAST(SUBSTRING(peer_id FROM 10) AS BIGINT)), 0)
    INTO max_after_backfill
    FROM users
    WHERE peer_id ~ '^PG3182736[0-9]+$';

    -- Synchronize sequence peer_id_seq to highest existing suffix
    IF max_after_backfill > 0 THEN
        PERFORM setval('peer_id_seq', max_after_backfill, true);
    ELSE
        PERFORM setval('peer_id_seq', 1, false);
    END IF;
END $$;

-- 4. Add UNIQUE constraint to users(peer_id) if not exists
-- Note: Creating UNIQUE constraint automatically creates a unique B-tree index under the hood.
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'users_peer_id_unique'
    ) THEN
        ALTER TABLE users ADD CONSTRAINT users_peer_id_unique UNIQUE (peer_id);
    END IF;
END $$;

-- 5. Set column DEFAULT using PostgreSQL sequence
ALTER TABLE users ALTER COLUMN peer_id SET DEFAULT ('PG3182736' || nextval('peer_id_seq'));

-- 6. Enforce NOT NULL after backfill is verified complete
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM users WHERE peer_id IS NULL LIMIT 1) THEN
        ALTER TABLE users ALTER COLUMN peer_id SET NOT NULL;
    END IF;
END $$;
