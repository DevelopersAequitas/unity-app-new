-- Manual SQL for Industry Director (IDE) industry assignments.
-- Run this manually; no Laravel migration is provided by design.

CREATE TABLE IF NOT EXISTS industry_director_assignments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    admin_user_id UUID REFERENCES admin_users(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    industry_id UUID NOT NULL REFERENCES industries(id) ON DELETE RESTRICT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_industry_director_assignments_user UNIQUE (user_id)
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_industry_director_assignments_admin_user
    ON industry_director_assignments(admin_user_id)
    WHERE admin_user_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_industry_director_assignments_industry_id
    ON industry_director_assignments(industry_id);

CREATE INDEX IF NOT EXISTS idx_industry_director_assignments_user_industry
    ON industry_director_assignments(user_id, industry_id);
