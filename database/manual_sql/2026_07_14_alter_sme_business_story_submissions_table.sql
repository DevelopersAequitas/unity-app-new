-- Safely add missing columns for the new Vyapaar Jagat form to the existing sme_business_story_submissions table
ALTER TABLE sme_business_story_submissions ADD COLUMN IF NOT EXISTS user_id UUID NULL;
ALTER TABLE sme_business_story_submissions ADD COLUMN IF NOT EXISTS designation VARCHAR(255) NULL;
ALTER TABLE sme_business_story_submissions ADD COLUMN IF NOT EXISTS company_name VARCHAR(255) NULL;
ALTER TABLE sme_business_story_submissions ADD COLUMN IF NOT EXISTS website VARCHAR(255) NULL;
ALTER TABLE sme_business_story_submissions ADD COLUMN IF NOT EXISTS profile_photo UUID NULL;
ALTER TABLE sme_business_story_submissions ADD COLUMN IF NOT EXISTS company_logo UUID NULL;
ALTER TABLE sme_business_story_submissions ADD COLUMN IF NOT EXISTS biggest_challenge TEXT NULL;
ALTER TABLE sme_business_story_submissions ADD COLUMN IF NOT EXISTS biggest_achievement TEXT NULL;
ALTER TABLE sme_business_story_submissions ADD COLUMN IF NOT EXISTS business_impact TEXT NULL;
ALTER TABLE sme_business_story_submissions ADD COLUMN IF NOT EXISTS future_goals TEXT NULL;
ALTER TABLE sme_business_story_submissions ADD COLUMN IF NOT EXISTS advice_for_entrepreneurs TEXT NULL;
ALTER TABLE sme_business_story_submissions ADD COLUMN IF NOT EXISTS linkedin_url VARCHAR(255) NULL;
ALTER TABLE sme_business_story_submissions ADD COLUMN IF NOT EXISTS facebook_url VARCHAR(255) NULL;
ALTER TABLE sme_business_story_submissions ADD COLUMN IF NOT EXISTS instagram_url VARCHAR(255) NULL;
ALTER TABLE sme_business_story_submissions ADD COLUMN IF NOT EXISTS twitter_url VARCHAR(255) NULL;
ALTER TABLE sme_business_story_submissions ADD COLUMN IF NOT EXISTS consent BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE sme_business_story_submissions ADD COLUMN IF NOT EXISTS admin_remark TEXT NULL;
ALTER TABLE sme_business_story_submissions ADD COLUMN IF NOT EXISTS submitted_at TIMESTAMP WITH TIME ZONE NULL;
ALTER TABLE sme_business_story_submissions ADD COLUMN IF NOT EXISTS reviewed_at TIMESTAMP WITH TIME ZONE NULL;

-- Add foreign key constraints if they do not exist
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_sme_business_story_submissions_user') THEN
        ALTER TABLE sme_business_story_submissions 
        ADD CONSTRAINT fk_sme_business_story_submissions_user 
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_sme_business_story_submissions_profile_photo') THEN
        ALTER TABLE sme_business_story_submissions 
        ADD CONSTRAINT fk_sme_business_story_submissions_profile_photo 
        FOREIGN KEY (profile_photo) REFERENCES files(id) ON DELETE SET NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_sme_business_story_submissions_company_logo') THEN
        ALTER TABLE sme_business_story_submissions 
        ADD CONSTRAINT fk_sme_business_story_submissions_company_logo 
        FOREIGN KEY (company_logo) REFERENCES files(id) ON DELETE SET NULL;
    END IF;
END $$;

-- Create index if not exists
CREATE INDEX IF NOT EXISTS idx_sme_business_story_submissions_user_id ON sme_business_story_submissions(user_id);
