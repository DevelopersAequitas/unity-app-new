-- Industry Director (IDE) dashboard phase setup.
-- Manual SQL only; do not run Laravel migrations for this change.

INSERT INTO roles (id, key, name, description, created_at, updated_at)
VALUES (
    gen_random_uuid(),
    'industry_director',
    'Industry Director',
    'Industry based admin access',
    NOW(),
    NOW()
)
ON CONFLICT (key) DO UPDATE
SET name = EXCLUDED.name,
    description = EXCLUDED.description,
    updated_at = NOW();

CREATE TABLE IF NOT EXISTS industry_director_assignments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    admin_user_id UUID NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
    industry_id UUID NOT NULL,
    assigned_by UUID NULL REFERENCES admin_users(id) ON DELETE SET NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (admin_user_id)
);

CREATE INDEX IF NOT EXISTS idx_ide_assignments_admin_user_id
ON industry_director_assignments(admin_user_id);

CREATE INDEX IF NOT EXISTS idx_ide_assignments_industry_id
ON industry_director_assignments(industry_id);

CREATE INDEX IF NOT EXISTS idx_ide_assignments_active
ON industry_director_assignments(is_active);
