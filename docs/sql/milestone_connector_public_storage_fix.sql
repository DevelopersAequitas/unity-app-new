-- ==============================================================================
-- MILESTONE CONNECTOR & PUBLIC STORAGE FIX
-- Database: PostgreSQL
-- Purpose:
--   1. Ensure milestone_badges table has stable public static HTTPS badge URLs.
--   2. Replace broken /api/v1/files/milestone-badges/ URLs with permanent public assets.
--   3. Idempotent: safe to run multiple times in Adminer / pgAdmin.
-- ==============================================================================

BEGIN;

-- 1. Update Member Introduction Milestone Badge static URLs
UPDATE milestone_badges
SET badge_image_url = 'https://peersunity.com/images/member_introduce_badges/Connector.png'
WHERE type = 'member_introduction' AND required_count = 1;

UPDATE milestone_badges
SET badge_image_url = 'https://peersunity.com/images/member_introduce_badges/Catalyst.png'
WHERE type = 'member_introduction' AND required_count = 3;

UPDATE milestone_badges
SET badge_image_url = 'https://peersunity.com/images/member_introduce_badges/Influencer.png'
WHERE type = 'member_introduction' AND required_count = 5;

UPDATE milestone_badges
SET badge_image_url = 'https://peersunity.com/images/member_introduce_badges/Ambassador.png'
WHERE type = 'member_introduction' AND required_count = 10;

UPDATE milestone_badges
SET badge_image_url = 'https://peersunity.com/images/member_introduce_badges/Rainmaker.png'
WHERE type = 'member_introduction' AND required_count = 20;

UPDATE milestone_badges
SET badge_image_url = 'https://peersunity.com/images/member_introduce_badges/Trailblazer.png'
WHERE type = 'member_introduction' AND required_count = 35;

UPDATE milestone_badges
SET badge_image_url = 'https://peersunity.com/images/member_introduce_badges/Vanguard.png'
WHERE type = 'member_introduction' AND required_count = 50;

UPDATE milestone_badges
SET badge_image_url = 'https://peersunity.com/images/member_introduce_badges/Luminary.png'
WHERE type = 'member_introduction' AND required_count = 75;

UPDATE milestone_badges
SET badge_image_url = 'https://peersunity.com/images/member_introduce_badges/Movement%20Maker.png'
WHERE type = 'member_introduction' AND required_count = 100;

UPDATE milestone_badges
SET badge_image_url = 'https://peersunity.com/images/member_introduce_badges/Community%20Titan.png'
WHERE type = 'member_introduction' AND required_count = 150;

UPDATE milestone_badges
SET badge_image_url = 'https://peersunity.com/images/member_introduce_badges/Network%20Architect.png'
WHERE type = 'member_introduction' AND required_count = 250;

UPDATE milestone_badges
SET badge_image_url = 'https://peersunity.com/images/member_introduce_badges/Global%20Icon.png'
WHERE type = 'member_introduction' AND required_count = 500;

-- 2. Fix any legacy badges pointing to internal /api/v1/files/ paths
UPDATE milestone_badges
SET badge_image_url = REPLACE(badge_image_url, '/api/v1/files/milestone-badges/', '/images/member_introduce_badges/')
WHERE badge_image_url LIKE '%/api/v1/files/milestone-badges/%';

COMMIT;
