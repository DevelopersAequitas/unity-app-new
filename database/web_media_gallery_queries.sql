-- ==============================================================================
-- DATABASE QUERIES FOR PEERS GLOBAL WEB MEDIA & GALLERY SYSTEM (PostgreSQL)
-- Safe to execute: uses IF NOT EXISTS and ALTER TABLE ... ADD COLUMN IF NOT EXISTS
-- ==============================================================================

-- 1. Table: web_galleries (Gallery Collections/Albums)
CREATE TABLE IF NOT EXISTS web_galleries (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE,
    subtitle VARCHAR(500),
    category_name VARCHAR(100),
    cover_image VARCHAR(1000),
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Ensure all columns exist on web_galleries if table was created previously
ALTER TABLE web_galleries ADD COLUMN IF NOT EXISTS title VARCHAR(255);
ALTER TABLE web_galleries ADD COLUMN IF NOT EXISTS slug VARCHAR(255);
ALTER TABLE web_galleries ADD COLUMN IF NOT EXISTS subtitle VARCHAR(500);
ALTER TABLE web_galleries ADD COLUMN IF NOT EXISTS category_name VARCHAR(100);
ALTER TABLE web_galleries ADD COLUMN IF NOT EXISTS cover_image VARCHAR(1000);
ALTER TABLE web_galleries ADD COLUMN IF NOT EXISTS sort_order INT DEFAULT 0;
ALTER TABLE web_galleries ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE;
ALTER TABLE web_galleries ADD COLUMN IF NOT EXISTS created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE web_galleries ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP;

-- 2. Table: web_gallery_items (Moments / Photos in Gallery)
CREATE TABLE IF NOT EXISTS web_gallery_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    gallery_id UUID REFERENCES web_galleries(id) ON DELETE SET NULL,
    title VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'Annual Summit',
    city VARCHAR(100) NOT NULL DEFAULT 'Ahmedabad',
    circle VARCHAR(100) NOT NULL DEFAULT 'All Circles',
    year VARCHAR(20) NOT NULL DEFAULT '2026',
    image_path VARCHAR(1000) NOT NULL,
    caption TEXT,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Ensure all columns exist on web_gallery_items if table was created previously
ALTER TABLE web_gallery_items ADD COLUMN IF NOT EXISTS gallery_id UUID;
ALTER TABLE web_gallery_items ADD COLUMN IF NOT EXISTS title VARCHAR(255);
ALTER TABLE web_gallery_items ADD COLUMN IF NOT EXISTS category VARCHAR(100) DEFAULT 'Annual Summit';
ALTER TABLE web_gallery_items ADD COLUMN IF NOT EXISTS city VARCHAR(100) DEFAULT 'Ahmedabad';
ALTER TABLE web_gallery_items ADD COLUMN IF NOT EXISTS circle VARCHAR(100) DEFAULT 'All Circles';
ALTER TABLE web_gallery_items ADD COLUMN IF NOT EXISTS year VARCHAR(20) DEFAULT '2026';
ALTER TABLE web_gallery_items ADD COLUMN IF NOT EXISTS image_path VARCHAR(1000);
ALTER TABLE web_gallery_items ADD COLUMN IF NOT EXISTS caption TEXT;
ALTER TABLE web_gallery_items ADD COLUMN IF NOT EXISTS sort_order INT DEFAULT 0;
ALTER TABLE web_gallery_items ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE;
ALTER TABLE web_gallery_items ADD COLUMN IF NOT EXISTS created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE web_gallery_items ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP;

CREATE INDEX IF NOT EXISTS idx_web_gallery_items_category ON web_gallery_items(category);
CREATE INDEX IF NOT EXISTS idx_web_gallery_items_city ON web_gallery_items(city);
CREATE INDEX IF NOT EXISTS idx_web_gallery_items_year ON web_gallery_items(year);
CREATE INDEX IF NOT EXISTS idx_web_gallery_items_active ON web_gallery_items(is_active);

-- 3. Table: web_media (Podcasts, TV, Media Features, and Digital Assets)
CREATE TABLE IF NOT EXISTS web_media (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(500),
    tag VARCHAR(100) DEFAULT 'MANUFACTURING',
    media_format VARCHAR(100) DEFAULT 'podcast',
    duration VARCHAR(50) DEFAULT '45 mins',
    youtube_url VARCHAR(1000),
    youtube_video_id VARCHAR(50),
    spotify_url VARCHAR(1000),
    apple_podcast_url VARCHAR(1000),
    external_url VARCHAR(1000),
    cover_image VARCHAR(1000),
    guest_name VARCHAR(255),
    guest_title VARCHAR(500),
    guest_avatar VARCHAR(1000),
    show_notes JSONB DEFAULT '[]'::jsonb,
    file_name VARCHAR(255),
    file_path VARCHAR(1000),
    file_type VARCHAR(50) DEFAULT 'video',
    file_size BIGINT DEFAULT 0,
    category VARCHAR(100) DEFAULT 'podcast',
    alt_text VARCHAR(255),
    dimensions VARCHAR(50),
    metadata JSONB DEFAULT '{}'::jsonb,
    sort_order INT DEFAULT 0,
    is_featured BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Ensure all podcast & media columns exist on web_media if table was created previously
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS title VARCHAR(255);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS subtitle VARCHAR(500);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS tag VARCHAR(100) DEFAULT 'MANUFACTURING';
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS media_format VARCHAR(100) DEFAULT 'podcast';
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS duration VARCHAR(50) DEFAULT '45 mins';
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS youtube_url VARCHAR(1000);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS youtube_video_id VARCHAR(50);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS spotify_url VARCHAR(1000);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS apple_podcast_url VARCHAR(1000);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS external_url VARCHAR(1000);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS cover_image VARCHAR(1000);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS guest_name VARCHAR(255);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS guest_title VARCHAR(500);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS guest_avatar VARCHAR(1000);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS show_notes JSONB DEFAULT '[]'::jsonb;
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS file_name VARCHAR(255);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS file_path VARCHAR(1000);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS file_type VARCHAR(50) DEFAULT 'video';
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS file_size BIGINT DEFAULT 0;
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS category VARCHAR(100) DEFAULT 'podcast';
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS alt_text VARCHAR(255);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS dimensions VARCHAR(50);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS metadata JSONB DEFAULT '{}'::jsonb;
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS sort_order INT DEFAULT 0;
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS is_featured BOOLEAN DEFAULT FALSE;
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE;
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP;

CREATE INDEX IF NOT EXISTS idx_web_media_tag ON web_media(tag);
CREATE INDEX IF NOT EXISTS idx_web_media_format ON web_media(media_format);
CREATE INDEX IF NOT EXISTS idx_web_media_featured ON web_media(is_featured);
CREATE INDEX IF NOT EXISTS idx_web_media_active ON web_media(is_active);
