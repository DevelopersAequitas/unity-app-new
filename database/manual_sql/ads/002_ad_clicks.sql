-- Manual PostgreSQL SQL for Ad Clicks
-- Run manually without Laravel migrations

CREATE TABLE IF NOT EXISTS ad_clicks (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NULL,
    ad_id UUID NOT NULL,
    click_type VARCHAR(30) NOT NULL DEFAULT 'visit',
    ip VARCHAR(45) NULL,
    device TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    session_id VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL
);
