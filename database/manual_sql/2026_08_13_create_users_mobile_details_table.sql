-- Manual Schema updates for Mobile Device Tracking and Multi-Device Single Session Enforcement
-- Run these queries directly on your database.

CREATE TABLE IF NOT EXISTS users_mobile_details (
    id UUID PRIMARY KEY,
    user_id UUID NOT NULL,
    device_type VARCHAR(20) NOT NULL, -- 'android' or 'ios'
    device_name VARCHAR(255),         -- e.g., 'Samsung Galaxy S21'
    os_version VARCHAR(50),           -- e.g., '14.0'
    device_id VARCHAR(255) NOT NULL,  -- Unique hardware ID or UUID sent by app
    token_id VARCHAR(255),            -- Sanctum personal_access_tokens ID
    last_login_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE,
    updated_at TIMESTAMP WITH TIME ZONE,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_users_mobile_details_user_platform ON users_mobile_details(user_id, device_type);
CREATE INDEX IF NOT EXISTS idx_users_mobile_details_device ON users_mobile_details(device_id);
