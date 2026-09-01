<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. brand_partner_categories
        DB::statement("
            CREATE TABLE IF NOT EXISTS brand_partner_categories (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                name VARCHAR(100) NOT NULL,
                icon VARCHAR(100) NULL,
                color VARCHAR(7) NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            );
        ");

        // 2. brand_partners
        DB::statement('
            CREATE TABLE IF NOT EXISTS brand_partners (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                uuid UUID NOT NULL DEFAULT gen_random_uuid(),
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                logo VARCHAR(255) NULL,
                cover_image VARCHAR(255) NULL,
                short_description VARCHAR(500) NULL,
                description TEXT NULL,
                category_id UUID NULL,
                website VARCHAR(255) NULL,
                contact_email VARCHAR(255) NULL,
                contact_number VARCHAR(50) NULL,
                whatsapp VARCHAR(50) NULL,
                address VARCHAR(500) NULL,
                offer_title VARCHAR(255) NULL,
                offer_description TEXT NULL,
                coupon_code VARCHAR(100) NULL,
                discount_type VARCHAR(50) NULL,
                discount_value NUMERIC(12,2) NULL,
                valid_from TIMESTAMPTZ NULL,
                valid_to TIMESTAMPTZ NULL,
                terms_and_conditions TEXT NULL,
                priority INTEGER NOT NULL DEFAULT 0,
                is_featured BOOLEAN NOT NULL DEFAULT FALSE,
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                is_verified BOOLEAN NOT NULL DEFAULT FALSE,
                is_sponsored BOOLEAN NOT NULL DEFAULT FALSE,
                meta_title VARCHAR(255) NULL,
                meta_description VARCHAR(500) NULL,
                keywords VARCHAR(255) NULL,
                created_by UUID NULL,
                updated_by UUID NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                metadata JSONB NULL,
                CONSTRAINT uq_brand_partners_slug UNIQUE (slug),
                CONSTRAINT uq_brand_partners_uuid UNIQUE (uuid)
            );
        ');

        // 3. brand_partner_views
        DB::statement('
            CREATE TABLE IF NOT EXISTS brand_partner_views (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                user_id UUID NULL,
                brand_partner_id UUID NOT NULL,
                viewed_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                session_id VARCHAR(255) NULL,
                ip_address VARCHAR(45) NULL
            );
        ');

        // 4. brand_partner_clicks
        DB::statement('
            CREATE TABLE IF NOT EXISTS brand_partner_clicks (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                user_id UUID NULL,
                brand_partner_id UUID NOT NULL,
                click_type VARCHAR(30) NOT NULL,
                ip VARCHAR(45) NULL,
                device TEXT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                session_id VARCHAR(255) NULL,
                ip_address VARCHAR(45) NULL
            );
        ');

        // 5. brand_partner_saved
        DB::statement('
            CREATE TABLE IF NOT EXISTS brand_partner_saved (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                user_id UUID NOT NULL,
                brand_partner_id UUID NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT uq_brand_partner_saved UNIQUE (user_id, brand_partner_id)
            );
        ');

        // 6. ads
        DB::statement('
            CREATE TABLE IF NOT EXISTS ads (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                title VARCHAR(255) NOT NULL,
                subtitle VARCHAR(255) NULL,
                description TEXT NULL,
                image_path VARCHAR(255) NULL,
                redirect_url VARCHAR(500) NULL,
                button_text VARCHAR(100) NULL,
                placement VARCHAR(50) NULL,
                page_name VARCHAR(100) NULL,
                timeline_position INTEGER NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                starts_at TIMESTAMPTZ NULL,
                ends_at TIMESTAMPTZ NULL,
                created_by UUID NULL,
                deleted_at TIMESTAMPTZ NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            );
        ');

        // 7. ad_views
        DB::statement('
            CREATE TABLE IF NOT EXISTS ad_views (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                user_id UUID NULL,
                ad_id UUID NOT NULL,
                viewed_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                session_id VARCHAR(255) NULL,
                ip_address VARCHAR(45) NULL
            );
        ');

        // 8. ad_clicks
        DB::statement("
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
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS ad_clicks CASCADE;');
        DB::statement('DROP TABLE IF EXISTS ad_views CASCADE;');
        DB::statement('DROP TABLE IF EXISTS ads CASCADE;');
        DB::statement('DROP TABLE IF EXISTS brand_partner_saved CASCADE;');
        DB::statement('DROP TABLE IF EXISTS brand_partner_clicks CASCADE;');
        DB::statement('DROP TABLE IF EXISTS brand_partner_views CASCADE;');
        DB::statement('DROP TABLE IF EXISTS brand_partners CASCADE;');
        DB::statement('DROP TABLE IF EXISTS brand_partner_categories CASCADE;');
    }
};
