<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        \App\Support\SqliteMigrator::run(<<<'SQL'
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;

CREATE TYPE membership_status_enum AS ENUM ('visitor','premium','charter','suspended');
CREATE TYPE circle_status_enum AS ENUM ('pending','active','archived');
CREATE TYPE circle_member_role_enum AS ENUM (
    'member',
    'founder',
    'director',
    'chair',
    'vice_chair',
    'secretary',
    'committee_leader'
);
CREATE TYPE circle_member_status_enum AS ENUM ('pending','approved','rejected');
CREATE TYPE post_moderation_status_enum AS ENUM ('pending','approved','hidden');
CREATE TYPE post_visibility_enum AS ENUM ('public','circle','connections');
CREATE TYPE event_rsvp_status_enum AS ENUM ('none','going','interested','not_going','waitlisted');
CREATE TYPE activity_type_enum AS ENUM (
    'publish_story_vj',
    'visit_member_spotlight',
    'bring_speaker',
    'join_circle',
    'install_app',
    'renew_membership',
    'post_ask',
    'update_timeline',
    'invite_visitor',
    'new_member_addition',
    'peer_meeting',
    'pass_referral',
    'attend_circle_meeting',
    'close_business_deal',
    'testimonial',
    'need_help_growing',
    'requirement_posted'
);
CREATE TYPE activity_status_enum AS ENUM ('pending','approved','rejected');
CREATE TYPE wallet_tx_type_enum AS ENUM ('topup','deduction','refund');
CREATE TYPE wallet_tx_status_enum AS ENUM ('initiated','success','failed');
CREATE TYPE notification_type_enum AS ENUM (
    'system',
    'new_message',
    'new_follower',
    'event_reminder',
    'coin_earned',
    'activity_update',
    'meetup_recommendation',
    'circle_update',
    'requirement_match'
);
CREATE TYPE referral_status_enum AS ENUM ('active','expired','revoked');
CREATE TYPE visitor_status_enum AS ENUM ('visited','signed_up','upgraded','joined_circle');
CREATE TYPE admin_role_key_enum AS ENUM ('global_admin','industry_director','ded','circle_leader');

CREATE TABLE roles (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    key             admin_role_key_enum NOT NULL,
    name            VARCHAR(100) NOT NULL,
    description     TEXT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE(key)
);

CREATE TABLE membership_tiers (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    code            VARCHAR(50) NOT NULL,
    name            VARCHAR(100) NOT NULL,
    description     TEXT,
    is_default      BOOLEAN NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE(code)
);

CREATE TABLE cities (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name            VARCHAR(150) NOT NULL,
    state           VARCHAR(150),
    district        VARCHAR(150),
    country         VARCHAR(150) NOT NULL,
    country_code    VARCHAR(10),
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_cities_name_trgm ON cities USING GIN (name gin_trgm_ops);

CREATE TABLE tags (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name            VARCHAR(150) NOT NULL,
    slug            VARCHAR(180) NOT NULL,
    category        VARCHAR(100),
    is_approved     BOOLEAN NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE(slug)
);
CREATE INDEX idx_tags_name_trgm ON tags USING GIN (name gin_trgm_ops);

CREATE TABLE circle_templates (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name            VARCHAR(150) NOT NULL,
    slug            VARCHAR(180) NOT NULL,
    description     TEXT,
    config          JSONB,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE(slug)
);

CREATE TABLE users (
    id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email                   VARCHAR(255),
    phone                   VARCHAR(30),
    password_hash           VARCHAR(255) NOT NULL,
    first_name              VARCHAR(100) NOT NULL,
    last_name               VARCHAR(100),
    display_name            VARCHAR(150),
    designation             VARCHAR(100),
    company_name            VARCHAR(150),
    profile_photo_url       TEXT,
    short_bio               TEXT,
    long_bio_html           TEXT,
    industry_tags           JSONB,
    business_type           VARCHAR(100),
    turnover_range          VARCHAR(100),
    city_id                 UUID REFERENCES cities(id) ON DELETE SET NULL,
    membership_status       membership_status_enum NOT NULL DEFAULT 'visitor',
    membership_expiry       TIMESTAMPTZ,
    coins_balance           BIGINT NOT NULL DEFAULT 0,
    introduced_by           UUID REFERENCES users(id) ON DELETE SET NULL,
    members_introduced_count INT NOT NULL DEFAULT 0,
    influencer_stars        INT NOT NULL DEFAULT 0,
    target_regions          JSONB,
    target_business_categories JSONB,
    hobbies_interests       JSONB,
    leadership_roles        JSONB,
    is_sponsored_member     BOOLEAN NOT NULL DEFAULT FALSE,
    public_profile_slug     VARCHAR(80),
    profile_visibility      VARCHAR(50) NOT NULL DEFAULT 'everyone',
    special_recognitions    JSONB,
    created_at              TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at              TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at              TIMESTAMPTZ,
    gdpr_deleted_at         TIMESTAMPTZ,
    anonymized_at           TIMESTAMPTZ,
    is_gdpr_exported        BOOLEAN NOT NULL DEFAULT FALSE,
    last_login_at           TIMESTAMPTZ,
    search_vector           tsvector,
    CONSTRAINT uq_users_email UNIQUE (email),
    CONSTRAINT uq_users_phone UNIQUE (phone),
    CONSTRAINT uq_users_public_slug UNIQUE (public_profile_slug)
);
CREATE INDEX idx_users_city_id ON users(city_id);
CREATE INDEX idx_users_created_at ON users(created_at);
CREATE INDEX idx_users_membership_status ON users(membership_status);
CREATE INDEX idx_users_search_vector ON users USING GIN (search_vector);
CREATE INDEX idx_users_industry_tags_gin ON users USING GIN (industry_tags);
CREATE INDEX idx_users_display_name_trgm ON users USING GIN (display_name gin_trgm_ops);
CREATE INDEX idx_users_profile_visibility ON users(profile_visibility);

CREATE TABLE user_links (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id         UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type            VARCHAR(50) NOT NULL,
    label           VARCHAR(100),
    url             TEXT NOT NULL,
    is_public       BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_user_links_user_id ON user_links(user_id);

CREATE TABLE circles (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name                VARCHAR(200) NOT NULL,
    slug                VARCHAR(200) NOT NULL,
    description         TEXT,
    purpose             TEXT,
    announcement        TEXT,
    founder_user_id     UUID REFERENCES users(id) ON DELETE SET NULL,
    template_id         UUID REFERENCES circle_templates(id) ON DELETE SET NULL,
    status              circle_status_enum NOT NULL DEFAULT 'pending',
    calendar            JSONB,
    city_id             UUID REFERENCES cities(id) ON DELETE SET NULL,
    industry_tags       JSONB,
    referral_score      INT NOT NULL DEFAULT 0,
    visitor_count       INT NOT NULL DEFAULT 0,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    UNIQUE(slug)
);
CREATE INDEX idx_circles_city_id ON circles(city_id);
CREATE INDEX idx_circles_status ON circles(status);
CREATE INDEX idx_circles_name_trgm ON circles USING GIN (name gin_trgm_ops);
CREATE INDEX idx_circles_tags_gin ON circles USING GIN (industry_tags);

CREATE TABLE circle_members (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    circle_id           UUID NOT NULL REFERENCES circles(id) ON DELETE CASCADE,
    user_id             UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role                circle_member_role_enum NOT NULL DEFAULT 'member',
    status              circle_member_status_enum NOT NULL DEFAULT 'pending',
    substitute_count    INT NOT NULL DEFAULT 0,
    joined_at           TIMESTAMPTZ,
    left_at             TIMESTAMPTZ,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    CONSTRAINT uq_circle_member UNIQUE (circle_id, user_id)
);
CREATE INDEX idx_circle_members_circle_id ON circle_members(circle_id);
CREATE INDEX idx_circle_members_user_id ON circle_members(user_id);

CREATE TABLE connections (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    requester_id    UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    addressee_id    UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    is_approved     BOOLEAN NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    approved_at     TIMESTAMPTZ,
    CONSTRAINT uq_connection_pair UNIQUE (requester_id, addressee_id)
);
CREATE INDEX idx_connections_requester ON connections(requester_id);
CREATE INDEX idx_connections_addressee ON connections(addressee_id);

CREATE TABLE posts (
    id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id                 UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    circle_id               UUID REFERENCES circles(id) ON DELETE SET NULL,
    content_text            TEXT,
    media                   JSONB,
    tags                    JSONB,
    visibility              post_visibility_enum NOT NULL DEFAULT 'public',
    moderation_status       post_moderation_status_enum NOT NULL DEFAULT 'pending',
    sponsored               BOOLEAN NOT NULL DEFAULT FALSE,
    is_deleted              BOOLEAN NOT NULL DEFAULT FALSE,
    created_at              TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at              TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at              TIMESTAMPTZ,
    full_text               tsvector
);
CREATE INDEX idx_posts_user_id ON posts(user_id);
CREATE INDEX idx_posts_circle_id ON posts(circle_id);
CREATE INDEX idx_posts_created_at ON posts(created_at);
CREATE INDEX idx_posts_visibility ON posts(visibility);
CREATE INDEX idx_posts_full_text ON posts USING GIN(full_text);
CREATE INDEX idx_posts_tags_gin ON posts USING GIN(tags);

CREATE TABLE post_likes (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    post_id         UUID NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
    user_id         UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_post_like UNIQUE (post_id, user_id)
);
CREATE INDEX idx_post_likes_post_id ON post_likes(post_id);
CREATE INDEX idx_post_likes_user_id ON post_likes(user_id);

CREATE TABLE post_comments (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    post_id         UUID NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
    user_id         UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    parent_id       UUID REFERENCES post_comments(id) ON DELETE SET NULL,
    content         TEXT NOT NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ
);
CREATE INDEX idx_post_comments_post_id ON post_comments(post_id);
CREATE INDEX idx_post_comments_user_id ON post_comments(user_id);

CREATE TABLE events (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    circle_id           UUID REFERENCES circles(id) ON DELETE SET NULL,
    created_by_user_id  UUID REFERENCES users(id) ON DELETE SET NULL,
    title               VARCHAR(255) NOT NULL,
    description         TEXT,
    start_at            TIMESTAMPTZ NOT NULL,
    end_at              TIMESTAMPTZ,
    is_virtual          BOOLEAN NOT NULL DEFAULT TRUE,
    location_text       TEXT,
    agenda              TEXT,
    speakers            JSONB,
    banner_url          TEXT,
    visibility          post_visibility_enum NOT NULL DEFAULT 'public',
    is_paid             BOOLEAN NOT NULL DEFAULT FALSE,
    metadata            JSONB,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ
);
CREATE INDEX idx_events_circle_id ON events(circle_id);
CREATE INDEX idx_events_start_at ON events(start_at);

CREATE TABLE event_rsvps (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_id        UUID NOT NULL REFERENCES events(id) ON DELETE CASCADE,
    user_id         UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    status          event_rsvp_status_enum NOT NULL DEFAULT 'going',
    checked_in      BOOLEAN NOT NULL DEFAULT FALSE,
    checkin_at      TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_event_rsvp UNIQUE (event_id, user_id)
);
CREATE INDEX idx_event_rsvps_event_id ON event_rsvps(event_id);
CREATE INDEX idx_event_rsvps_user_id ON event_rsvps(user_id);

CREATE TABLE activities (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id             UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    related_user_id     UUID REFERENCES users(id) ON DELETE SET NULL,
    circle_id           UUID REFERENCES circles(id) ON DELETE SET NULL,
    event_id            UUID REFERENCES events(id) ON DELETE SET NULL,
    type                activity_type_enum NOT NULL,
    status              activity_status_enum NOT NULL DEFAULT 'pending',
    description         TEXT,
    admin_notes         TEXT,
    requires_verification BOOLEAN NOT NULL DEFAULT TRUE,
    verified_by_admin_id UUID REFERENCES users(id) ON DELETE SET NULL,
    verified_at         TIMESTAMPTZ,
    coins_awarded       INT NOT NULL DEFAULT 0,
    coins_ledger_id     UUID,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_activities_user_id ON activities(user_id);
CREATE INDEX idx_activities_circle_id ON activities(circle_id);
CREATE INDEX idx_activities_type ON activities(type);
CREATE INDEX idx_activities_status ON activities(status);

CREATE TABLE activities_audit (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    activity_id     UUID NOT NULL REFERENCES activities(id) ON DELETE CASCADE,
    changed_by      UUID REFERENCES users(id) ON DELETE SET NULL,
    from_status     activity_status_enum,
    to_status       activity_status_enum,
    change_reason   TEXT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE coins_ledger (
    transaction_id      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id             UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    amount              BIGINT NOT NULL,
    balance_after       BIGINT NOT NULL,
    activity_id         UUID UNIQUE REFERENCES activities(id) ON DELETE SET NULL,
    reference           VARCHAR(255),
    created_by          UUID REFERENCES users(id) ON DELETE SET NULL,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_coins_ledger_user_id ON coins_ledger(user_id);
CREATE INDEX idx_coins_ledger_created_at ON coins_ledger(created_at);

CREATE TABLE wallet_transactions (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id         UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    amount          NUMERIC(12,2) NOT NULL,
    type            wallet_tx_type_enum NOT NULL,
    payment_ref     VARCHAR(255),
    status          wallet_tx_status_enum NOT NULL DEFAULT 'initiated',
    metadata        JSONB,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_wallet_tx_user_id ON wallet_transactions(user_id);
CREATE INDEX idx_wallet_tx_status ON wallet_transactions(status);

CREATE TABLE requirements (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id         UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    subject         VARCHAR(255) NOT NULL,
    description     TEXT,
    media           JSONB,
    region_filter   JSONB,
    category_filter JSONB,
    status          VARCHAR(50) NOT NULL DEFAULT 'open',
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ
);
CREATE INDEX idx_requirements_user_id ON requirements(user_id);

CREATE TABLE support_requests (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id             UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    support_type        VARCHAR(100) NOT NULL,
    details             TEXT,
    attachments         JSONB,
    routed_to_user_id   UUID REFERENCES users(id) ON DELETE SET NULL,
    status              VARCHAR(50) NOT NULL DEFAULT 'open',
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_support_requests_user_id ON support_requests(user_id);
CREATE INDEX idx_support_requests_type ON support_requests(support_type);

CREATE TABLE chats (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user1_id            UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    user2_id            UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    last_message_at     TIMESTAMPTZ,
    last_message_id     UUID,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_chat_pair UNIQUE (user1_id, user2_id)
);
CREATE INDEX idx_chats_last_message_at ON chats(last_message_at);

CREATE TABLE messages (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    chat_id         UUID NOT NULL REFERENCES chats(id) ON DELETE CASCADE,
    sender_id       UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    content         TEXT,
    attachments     JSONB,
    is_read         BOOLEAN NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ
);
CREATE INDEX idx_messages_chat_id_created_at ON messages(chat_id, created_at);
CREATE INDEX idx_messages_sender_id ON messages(sender_id);

CREATE TABLE notifications (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id         UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type            notification_type_enum NOT NULL,
    payload         JSONB NOT NULL,
    is_read         BOOLEAN NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    read_at         TIMESTAMPTZ
);
CREATE INDEX idx_notifications_user_id ON notifications(user_id);
CREATE INDEX idx_notifications_created_at ON notifications(created_at);

CREATE TABLE files (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    uploader_user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    s3_key          TEXT NOT NULL,
    mime_type       VARCHAR(100),
    size_bytes      BIGINT,
    width           INT,
    height          INT,
    duration        INT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_files_uploader ON files(uploader_user_id);
CREATE INDEX idx_files_created_at ON files(created_at);

CREATE TABLE admin_audit_logs (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    admin_user_id   UUID REFERENCES users(id) ON DELETE SET NULL,
    action          VARCHAR(100) NOT NULL,
    target_table    VARCHAR(100),
    target_id       UUID,
    details         JSONB,
    ip_address      INET,
    user_agent      TEXT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_admin_audit_logs_admin ON admin_audit_logs(admin_user_id);
CREATE INDEX idx_admin_audit_logs_created_at ON admin_audit_logs(created_at);

CREATE TABLE referral_links (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    referrer_user_id    UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token               VARCHAR(100) NOT NULL,
    status              referral_status_enum NOT NULL DEFAULT 'active',
    stats               JSONB,
    expires_at          TIMESTAMPTZ,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_referral_token UNIQUE(token)
);
CREATE INDEX idx_referral_links_referrer ON referral_links(referrer_user_id);

CREATE TABLE visitor_leads (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email               VARCHAR(255),
    phone               VARCHAR(30),
    status              visitor_status_enum NOT NULL DEFAULT 'visited',
    referral_link_id    UUID REFERENCES referral_links(id) ON DELETE SET NULL,
    converted_user_id   UUID REFERENCES users(id) ON DELETE SET NULL,
    converted_at        TIMESTAMPTZ,
    notes               TEXT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_visitor_leads_status ON visitor_leads(status);
CREATE INDEX idx_visitor_leads_referral_link_id ON visitor_leads(referral_link_id);

CREATE TABLE email_logs (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    to_email        VARCHAR(255) NOT NULL,
    template_key    VARCHAR(100) NOT NULL,
    payload         JSONB,
    status          VARCHAR(50) NOT NULL,
    sent_at         TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_email_logs_to_email ON email_logs(to_email);
CREATE INDEX idx_email_logs_template ON email_logs(template_key);

CREATE TABLE data_exports (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id         UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    export_type     VARCHAR(50) NOT NULL,
    file_id         UUID REFERENCES files(id) ON DELETE SET NULL,
    requested_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    completed_at    TIMESTAMPTZ,
    status          VARCHAR(50) NOT NULL DEFAULT 'pending',
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_data_exports_user_id ON data_exports(user_id);

CREATE TABLE feedback (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id         UUID REFERENCES users(id) ON DELETE SET NULL,
    type            VARCHAR(50),
    message         TEXT,
    metadata        JSONB,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE ads_banners (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title           VARCHAR(255),
    image_url       TEXT,
    link_url        TEXT,
    position        VARCHAR(50),
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    starts_at       TIMESTAMPTZ,
    ends_at         TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE OR REPLACE FUNCTION users_search_vector_trigger()
RETURNS trigger AS $$
BEGIN
  NEW.search_vector :=
    setweight(to_tsvector('simple', unaccent(coalesce(NEW.display_name,''))), 'A') ||
    setweight(to_tsvector('simple', unaccent(coalesce(NEW.first_name,''))), 'A') ||
    setweight(to_tsvector('simple', unaccent(coalesce(NEW.last_name,''))), 'B') ||
    setweight(to_tsvector('simple', unaccent(coalesce(NEW.designation,''))), 'B') ||
    setweight(to_tsvector('simple', unaccent(coalesce(NEW.company_name,''))), 'C') ||
    setweight(to_tsvector('simple', unaccent(coalesce(NEW.short_bio,''))), 'D');
  RETURN NEW;
END
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_users_search_vector
BEFORE INSERT OR UPDATE OF display_name, first_name, last_name, designation, company_name, short_bio
ON users
FOR EACH ROW
EXECUTE FUNCTION users_search_vector_trigger();

CREATE OR REPLACE FUNCTION posts_full_text_trigger()
RETURNS trigger AS $$
BEGIN
  NEW.full_text :=
    setweight(to_tsvector('simple', unaccent(coalesce(NEW.content_text,''))), 'A');
  RETURN NEW;
END
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_posts_full_text
BEFORE INSERT OR UPDATE OF content_text
ON posts
FOR EACH ROW
EXECUTE FUNCTION posts_full_text_trigger();

CREATE OR REPLACE FUNCTION coins_apply_transaction(
    p_user_id UUID,
    p_amount BIGINT,
    p_activity_id UUID,
    p_reference VARCHAR,
    p_created_by UUID
) RETURNS UUID AS $$
DECLARE
    v_balance BIGINT;
    v_new_balance BIGINT;
    v_tx_id UUID;
BEGIN
    SELECT coins_balance INTO v_balance
    FROM users
    WHERE id = p_user_id
    FOR UPDATE;

    IF v_balance IS NULL THEN
        RAISE EXCEPTION 'User % not found', p_user_id;
    END IF;

    v_new_balance := v_balance + p_amount;

    IF v_new_balance < 0 THEN
        RAISE EXCEPTION 'Insufficient coin balance for user %', p_user_id;
    END IF;

    INSERT INTO coins_ledger (user_id, amount, balance_after, activity_id, reference, created_by)
    VALUES (p_user_id, p_amount, v_new_balance, p_activity_id, p_reference, p_created_by)
    RETURNING transaction_id INTO v_tx_id;

    UPDATE users
    SET coins_balance = v_new_balance,
        updated_at = NOW()
    WHERE id = p_user_id;

    RETURN v_tx_id;
EXCEPTION
    WHEN unique_violation THEN
        RAISE EXCEPTION 'Coins already awarded for activity %', p_activity_id;
END;
$$ LANGUAGE plpgsql;

CREATE MATERIALIZED VIEW mv_coins_per_circle AS
SELECT
    c.id AS circle_id,
    c.name,
    SUM(u.coins_balance) AS total_coins
FROM circles c
JOIN circle_members cm ON cm.circle_id = c.id AND cm.status = 'approved'
JOIN users u ON u.id = cm.user_id
GROUP BY c.id, c.name;
CREATE UNIQUE INDEX mv_coins_per_circle_circle_id ON mv_coins_per_circle(circle_id);

CREATE MATERIALIZED VIEW mv_coins_per_city AS
SELECT
    ci.id AS city_id,
    ci.name,
    ci.country,
    SUM(u.coins_balance) AS total_coins
FROM cities ci
JOIN users u ON u.city_id = ci.id
GROUP BY ci.id, ci.name, ci.country;
CREATE UNIQUE INDEX mv_coins_per_city_city_id ON mv_coins_per_city(city_id);
SQL);
    }

    public function down(): void
    {
        // Intentionally left blank. Implement drops if rollback support is required.
    }
};
