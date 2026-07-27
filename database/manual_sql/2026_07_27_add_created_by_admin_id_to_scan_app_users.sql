ALTER TABLE scan_app_users
    ADD COLUMN IF NOT EXISTS created_by_admin_id UUID;
