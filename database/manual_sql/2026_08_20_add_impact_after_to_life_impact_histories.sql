-- Manual SQL Query to add impact_after column right next to impact_value in life_impact_histories table
-- Date: 2026-08-20

BEGIN;

-- 1. Create new table with exact column order
CREATE TABLE IF NOT EXISTS life_impact_histories_new (
    id UUID PRIMARY KEY,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    triggered_by_user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    activity_type VARCHAR(100) NOT NULL,
    activity_id UUID NULL,
    impact_value INTEGER NOT NULL,
    impact_after INTEGER NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    meta JSONB NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    life_impacted INTEGER NOT NULL,
    counted_in_total BOOLEAN NOT NULL DEFAULT TRUE,
    impact_category VARCHAR(255) NULL,
    action_key VARCHAR(100) NULL,
    action_label VARCHAR(255) NULL,
    remarks TEXT NULL
);

-- 2. Copy existing data
INSERT INTO life_impact_histories_new (
    id, user_id, triggered_by_user_id, activity_type, activity_id,
    impact_value, impact_after, title, description, meta,
    created_at, updated_at, life_impacted, counted_in_total,
    impact_category, action_key, action_label, remarks
)
SELECT 
    id, user_id, triggered_by_user_id, activity_type, activity_id,
    impact_value, impact_after, title, description, meta,
    created_at, updated_at, life_impacted, counted_in_total,
    impact_category, action_key, action_label, remarks
FROM life_impact_histories;

-- 3. Drop old table and rename new table
DROP TABLE life_impact_histories CASCADE;
ALTER TABLE life_impact_histories_new RENAME TO life_impact_histories;

-- 4. Recreate indexes
CREATE INDEX IF NOT EXISTS idx_life_impact_histories_user_id ON life_impact_histories(user_id);
CREATE INDEX IF NOT EXISTS idx_life_impact_histories_triggered_by_user_id ON life_impact_histories(triggered_by_user_id);
CREATE INDEX IF NOT EXISTS idx_life_impact_histories_activity_type ON life_impact_histories(activity_type);
CREATE INDEX IF NOT EXISTS idx_life_impact_histories_activity_id ON life_impact_histories(activity_id);
CREATE INDEX IF NOT EXISTS idx_life_impact_histories_created_at ON life_impact_histories(created_at DESC);

-- 5. Backfill cumulative running impact_after for all past historical records
UPDATE life_impact_histories h
SET impact_after = sub.running_total
FROM (
    SELECT 
        id,
        SUM(COALESCE(impact_value, life_impacted, 0)) OVER (
            PARTITION BY user_id 
            ORDER BY created_at ASC, id ASC
        ) AS running_total
    FROM life_impact_histories
) sub
WHERE h.id = sub.id
  AND h.impact_after IS NULL;

COMMIT;
