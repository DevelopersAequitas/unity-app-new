-- GREENPRENEUR — Anniversary Creative Image & Template SQL Script
-- Run manually on Local & Production PostgreSQL Databases

-- 1. Create anniversary_templates table
CREATE TABLE IF NOT EXISTS anniversary_templates (
    id UUID PRIMARY KEY,
    image_path VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 2. Add new creative columns to posts table
ALTER TABLE posts ADD COLUMN IF NOT EXISTS post_type VARCHAR(50) DEFAULT 'standard' NULL;
ALTER TABLE posts ADD COLUMN IF NOT EXISTS template_id UUID NULL;
ALTER TABLE posts ADD COLUMN IF NOT EXISTS title VARCHAR(255) NULL;
ALTER TABLE posts ADD COLUMN IF NOT EXISTS description TEXT NULL;
ALTER TABLE posts ADD COLUMN IF NOT EXISTS image TEXT NULL;
ALTER TABLE posts ADD COLUMN IF NOT EXISTS status VARCHAR(50) DEFAULT 'active' NULL;

-- 3. Add index on anniversary_templates active flag for query optimization
CREATE INDEX IF NOT EXISTS idx_anniversary_templates_active ON anniversary_templates (is_active) WHERE is_active = TRUE;

-- 4. Verification Queries
SELECT table_name FROM information_schema.tables WHERE table_name = 'anniversary_templates';
SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'posts' AND column_name IN ('post_type', 'template_id', 'title', 'description', 'image', 'status');
