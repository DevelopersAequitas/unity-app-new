ALTER TABLE scan_app_users
    ADD COLUMN IF NOT EXISTS plain_password VARCHAR(255),
    ADD COLUMN IF NOT EXISTS event_ids JSONB;
