-- Manual PostgreSQL SQL for Ad Views
-- Run manually without Laravel migrations

CREATE TABLE IF NOT EXISTS ad_views (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NULL,
    ad_id UUID NOT NULL,
    viewed_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    session_id VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL
);
