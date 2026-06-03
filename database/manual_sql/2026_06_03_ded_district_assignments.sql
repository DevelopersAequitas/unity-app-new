-- Manual PostgreSQL SQL for DED (District Executive Director) state/district assignments.
-- Run this manually; do not run as a Laravel migration.
-- This creates/updates storage only. Populate official Government of India LGD data with:
-- php artisan import:india-districts storage/app/india_districts.csv

CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE TABLE IF NOT EXISTS states (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(150) NOT NULL UNIQUE,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS districts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    state_id UUID NOT NULL REFERENCES states(id) ON DELETE RESTRICT,
    name VARCHAR(150) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT districts_state_name_unique UNIQUE (state_id, name)
);

-- Rollback-safe upgrades for environments where an older districts table was created before state_id/status existed.
ALTER TABLE districts ADD COLUMN IF NOT EXISTS state_id UUID;
ALTER TABLE districts ADD COLUMN IF NOT EXISTS status VARCHAR(30) NOT NULL DEFAULT 'active';
ALTER TABLE districts ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ NOT NULL DEFAULT NOW();
ALTER TABLE districts ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW();

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'districts_state_id_fk'
    ) THEN
        ALTER TABLE districts
            ADD CONSTRAINT districts_state_id_fk FOREIGN KEY (state_id) REFERENCES states(id) ON DELETE RESTRICT;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'districts_state_name_unique'
    ) THEN
        ALTER TABLE districts
            ADD CONSTRAINT districts_state_name_unique UNIQUE (state_id, name);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM districts WHERE state_id IS NULL) THEN
        ALTER TABLE districts ALTER COLUMN state_id SET NOT NULL;
    END IF;
END $$;

CREATE TABLE IF NOT EXISTS admin_ded_districts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    admin_user_id UUID NOT NULL,
    user_id UUID NULL,
    state_id UUID NOT NULL,
    district_id UUID NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT admin_ded_districts_admin_user_unique UNIQUE (admin_user_id),
    CONSTRAINT admin_ded_districts_admin_user_fk FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    CONSTRAINT admin_ded_districts_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT admin_ded_districts_state_fk FOREIGN KEY (state_id) REFERENCES states(id) ON DELETE RESTRICT,
    CONSTRAINT admin_ded_districts_district_fk FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE RESTRICT
);

-- Rollback-safe upgrade for environments where an older admin_ded_districts table existed without state_id.
ALTER TABLE admin_ded_districts ADD COLUMN IF NOT EXISTS state_id UUID;

UPDATE admin_ded_districts addd
SET state_id = d.state_id, updated_at = NOW()
FROM districts d
WHERE addd.state_id IS NULL
  AND addd.district_id = d.id;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'admin_ded_districts_state_fk'
    ) THEN
        ALTER TABLE admin_ded_districts
            ADD CONSTRAINT admin_ded_districts_state_fk FOREIGN KEY (state_id) REFERENCES states(id) ON DELETE RESTRICT;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM admin_ded_districts WHERE state_id IS NULL) THEN
        ALTER TABLE admin_ded_districts ALTER COLUMN state_id SET NOT NULL;
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS states_status_idx ON states (status);
CREATE INDEX IF NOT EXISTS districts_state_id_idx ON districts (state_id);
CREATE INDEX IF NOT EXISTS districts_status_idx ON districts (status);
CREATE INDEX IF NOT EXISTS admin_ded_districts_state_id_idx ON admin_ded_districts (state_id);
CREATE INDEX IF NOT EXISTS admin_ded_districts_district_id_idx ON admin_ded_districts (district_id);
CREATE INDEX IF NOT EXISTS cities_lower_state_district_idx ON cities (LOWER(state), LOWER(district));

-- Rollback (manual, only if you need to remove this feature's storage):
-- DROP TABLE IF EXISTS admin_ded_districts;
-- DROP INDEX IF EXISTS cities_lower_state_district_idx;
-- DROP TABLE IF EXISTS districts;
-- DROP TABLE IF EXISTS states;
