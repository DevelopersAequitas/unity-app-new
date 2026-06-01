-- Manual PostgreSQL setup for DED state/district assignments.
-- Run this SQL manually; do not run Laravel migrations for this task.

CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE TABLE IF NOT EXISTS states (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(150) UNIQUE NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

INSERT INTO states (name, status, created_at, updated_at)
SELECT DISTINCT trim(cities.state), 'active', NOW(), NOW()
FROM cities
WHERE NULLIF(trim(COALESCE(cities.state, '')), '') IS NOT NULL
ON CONFLICT (name) DO UPDATE SET status = EXCLUDED.status, updated_at = NOW();

CREATE TABLE IF NOT EXISTS districts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    state_id UUID REFERENCES states(id) ON DELETE RESTRICT,
    name VARCHAR(150) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

ALTER TABLE districts ADD COLUMN IF NOT EXISTS state_id UUID REFERENCES states(id) ON DELETE RESTRICT;
ALTER TABLE districts ADD COLUMN IF NOT EXISTS status VARCHAR(30) NOT NULL DEFAULT 'active';

UPDATE districts
SET state_id = states.id,
    status = COALESCE(NULLIF(districts.status, ''), 'active'),
    updated_at = NOW()
FROM states
WHERE districts.state_id IS NULL
  AND EXISTS (
      SELECT 1
      FROM cities
      WHERE LOWER(trim(cities.district)) = LOWER(trim(districts.name))
        AND LOWER(trim(cities.state)) = LOWER(states.name)
  );

INSERT INTO districts (state_id, name, status, created_at, updated_at)
SELECT DISTINCT states.id, trim(cities.district), 'active', NOW(), NOW()
FROM cities
JOIN states ON LOWER(states.name) = LOWER(trim(cities.state))
WHERE NULLIF(trim(COALESCE(cities.state, '')), '') IS NOT NULL
  AND NULLIF(trim(COALESCE(cities.district, '')), '') IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM districts
      WHERE districts.state_id = states.id
        AND LOWER(districts.name) = LOWER(trim(cities.district))
  );

CREATE UNIQUE INDEX IF NOT EXISTS idx_districts_unique_state_name
    ON districts (state_id, LOWER(name));
CREATE INDEX IF NOT EXISTS idx_districts_state_id ON districts(state_id);

CREATE TABLE IF NOT EXISTS admin_ded_districts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    admin_user_id UUID NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
    user_id UUID NULL REFERENCES users(id) ON DELETE SET NULL,
    state_id UUID REFERENCES states(id) ON DELETE RESTRICT,
    district_id UUID REFERENCES districts(id) ON DELETE RESTRICT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (admin_user_id)
);

ALTER TABLE admin_ded_districts ADD COLUMN IF NOT EXISTS state_id UUID REFERENCES states(id) ON DELETE RESTRICT;
ALTER TABLE admin_ded_districts ADD COLUMN IF NOT EXISTS district_id UUID REFERENCES districts(id) ON DELETE RESTRICT;

UPDATE admin_ded_districts
SET state_id = districts.state_id,
    updated_at = NOW()
FROM districts
WHERE admin_ded_districts.district_id = districts.id
  AND admin_ded_districts.state_id IS NULL;

ALTER TABLE admin_ded_districts ALTER COLUMN state_id SET NOT NULL;
ALTER TABLE admin_ded_districts ALTER COLUMN district_id SET NOT NULL;

CREATE INDEX IF NOT EXISTS idx_admin_ded_districts_state_id ON admin_ded_districts(state_id);
CREATE INDEX IF NOT EXISTS idx_admin_ded_districts_district_id ON admin_ded_districts(district_id);
CREATE INDEX IF NOT EXISTS idx_admin_ded_districts_user_id ON admin_ded_districts(user_id);

-- Optional compatibility for dynamic DED locations discovered from existing users/circles.
-- These columns allow assigning districts that come from live city text before/without
-- manually maintaining a matching districts row.
ALTER TABLE admin_ded_districts ADD COLUMN IF NOT EXISTS district_name VARCHAR(150);
ALTER TABLE admin_ded_districts ADD COLUMN IF NOT EXISTS state_name VARCHAR(150);
ALTER TABLE admin_ded_districts ALTER COLUMN state_id DROP NOT NULL;
ALTER TABLE admin_ded_districts ALTER COLUMN district_id DROP NOT NULL;

UPDATE admin_ded_districts
SET district_name = districts.name,
    state_name = states.name,
    updated_at = NOW()
FROM districts
LEFT JOIN states ON states.id = districts.state_id
WHERE admin_ded_districts.district_id = districts.id
  AND NULLIF(TRIM(COALESCE(admin_ded_districts.district_name, '')), '') IS NULL;

CREATE INDEX IF NOT EXISTS idx_admin_ded_districts_district_name
    ON admin_ded_districts (LOWER(TRIM(district_name)));
CREATE INDEX IF NOT EXISTS idx_admin_ded_districts_state_name
    ON admin_ded_districts (LOWER(TRIM(state_name)));
