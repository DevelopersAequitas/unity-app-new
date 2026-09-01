-- Manual PostgreSQL SQL for Ad Views and Clicks Indexes and Foreign Keys
-- Run manually without Laravel migrations

CREATE INDEX IF NOT EXISTS idx_ad_views_user_id ON ad_views(user_id);
CREATE INDEX IF NOT EXISTS idx_ad_views_ad_id ON ad_views(ad_id);
CREATE INDEX IF NOT EXISTS idx_ad_views_viewed_at ON ad_views(viewed_at);
CREATE INDEX IF NOT EXISTS idx_ad_views_unique_tracker ON ad_views(ad_id, user_id, ip_address, session_id, viewed_at);

CREATE INDEX IF NOT EXISTS idx_ad_clicks_user_id ON ad_clicks(user_id);
CREATE INDEX IF NOT EXISTS idx_ad_clicks_ad_id ON ad_clicks(ad_id);
CREATE INDEX IF NOT EXISTS idx_ad_clicks_click_type ON ad_clicks(click_type);
CREATE INDEX IF NOT EXISTS idx_ad_clicks_created_at ON ad_clicks(created_at);
CREATE INDEX IF NOT EXISTS idx_ad_clicks_unique_tracker ON ad_clicks(ad_id, user_id, ip_address, session_id, created_at);

ALTER TABLE ad_views ADD CONSTRAINT fk_ad_views_ad_id FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE;
ALTER TABLE ad_clicks ADD CONSTRAINT fk_ad_clicks_ad_id FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE;
