-- =============================================================================
-- PEERS GLOBAL UNITY — WHATSAPP BULK CAMPAIGN MODULE
-- PostgreSQL Standalone Schema Script
-- File: docs/sql/whatsapp_bulk_campaigns.sql
-- =============================================================================
-- Notes:
-- 1. This script is idempotent (uses CREATE TABLE IF NOT EXISTS and CREATE INDEX IF NOT EXISTS).
-- 2. Compatible with PostgreSQL 13+.
-- 3. Uses standard UUID primary keys and JSONB fields.
-- 4. Do not run via Laravel migrations. Execute manually in pgAdmin / psql.
-- =============================================================================

-- Enable pgcrypto / uuid-ossp if not already enabled (for gen_random_uuid())
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- -----------------------------------------------------------------------------
-- 1. Table: whatsapp_bulk_campaigns
-- Stores campaign definitions, selected template snapshot, variable mapping,
-- scheduling, aggregate counts, and execution status.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS whatsapp_bulk_campaigns (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    whatsapp_template_id UUID NULL,
    template_name_snapshot VARCHAR(255) NOT NULL,
    template_language VARCHAR(20) NOT NULL DEFAULT 'en_IN',
    template_category VARCHAR(50) NULL,
    template_snapshot JSONB NULL,
    variable_mapping JSONB NULL,
    media_configuration JSONB NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'draft',
    schedule_type VARCHAR(50) NOT NULL DEFAULT 'immediate',
    scheduled_at TIMESTAMPTZ NULL,
    started_at TIMESTAMPTZ NULL,
    completed_at TIMESTAMPTZ NULL,
    created_by UUID NULL,
    total_rows INTEGER NOT NULL DEFAULT 0,
    valid_count INTEGER NOT NULL DEFAULT 0,
    invalid_count INTEGER NOT NULL DEFAULT 0,
    duplicate_count INTEGER NOT NULL DEFAULT 0,
    sent_count INTEGER NOT NULL DEFAULT 0,
    delivered_count INTEGER NOT NULL DEFAULT 0,
    read_count INTEGER NOT NULL DEFAULT 0,
    failed_count INTEGER NOT NULL DEFAULT 0,
    metadata JSONB NULL,
    created_at TIMESTAMPTZ NULL,
    updated_at TIMESTAMPTZ NULL,
    CONSTRAINT fk_wbc_template FOREIGN KEY (whatsapp_template_id) 
        REFERENCES whatsapp_templates(id) ON DELETE SET NULL,
    CONSTRAINT fk_wbc_creator FOREIGN KEY (created_by) 
        REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_wbc_status ON whatsapp_bulk_campaigns(status);
CREATE INDEX IF NOT EXISTS idx_wbc_scheduled_at ON whatsapp_bulk_campaigns(scheduled_at);
CREATE INDEX IF NOT EXISTS idx_wbc_template_id ON whatsapp_bulk_campaigns(whatsapp_template_id);
CREATE INDEX IF NOT EXISTS idx_wbc_created_by ON whatsapp_bulk_campaigns(created_by);
CREATE INDEX IF NOT EXISTS idx_wbc_created_at ON whatsapp_bulk_campaigns(created_at);


-- -----------------------------------------------------------------------------
-- 2. Table: whatsapp_bulk_campaign_recipients
-- Stores each recipient's data, resolved template variables, current delivery
-- status, provider message ID, and granular timestamps.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS whatsapp_bulk_campaign_recipients (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    campaign_id UUID NOT NULL,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    original_phone VARCHAR(100) NULL,
    csv_row_number INTEGER NULL,
    custom_data JSONB NULL,
    resolved_parameters JSONB NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    whatsapp_message_id VARCHAR(255) NULL,
    attempt_count INTEGER NOT NULL DEFAULT 0,
    last_attempt_at TIMESTAMPTZ NULL,
    sent_at TIMESTAMPTZ NULL,
    delivered_at TIMESTAMPTZ NULL,
    read_at TIMESTAMPTZ NULL,
    failed_at TIMESTAMPTZ NULL,
    error_code VARCHAR(50) NULL,
    error_message TEXT NULL,
    created_at TIMESTAMPTZ NULL,
    updated_at TIMESTAMPTZ NULL,
    CONSTRAINT fk_wbcr_campaign FOREIGN KEY (campaign_id) 
        REFERENCES whatsapp_bulk_campaigns(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_wbcr_campaign_id ON whatsapp_bulk_campaign_recipients(campaign_id);
CREATE INDEX IF NOT EXISTS idx_wbcr_campaign_status ON whatsapp_bulk_campaign_recipients(campaign_id, status);
CREATE INDEX IF NOT EXISTS idx_wbcr_phone ON whatsapp_bulk_campaign_recipients(phone);
CREATE INDEX IF NOT EXISTS idx_wbcr_whatsapp_msg_id ON whatsapp_bulk_campaign_recipients(whatsapp_message_id);
CREATE INDEX IF NOT EXISTS idx_wbcr_status ON whatsapp_bulk_campaign_recipients(status);


-- -----------------------------------------------------------------------------
-- 3. Table: whatsapp_bulk_campaign_import_errors
-- Preserves CSV parsing and validation failures for auditing and download.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS whatsapp_bulk_campaign_import_errors (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    campaign_id UUID NULL,
    import_reference VARCHAR(100) NULL,
    row_number INTEGER NOT NULL,
    raw_data JSONB NULL,
    error_codes JSONB NULL,
    error_message TEXT NOT NULL,
    created_at TIMESTAMPTZ NULL,
    updated_at TIMESTAMPTZ NULL,
    CONSTRAINT fk_wbcie_campaign FOREIGN KEY (campaign_id) 
        REFERENCES whatsapp_bulk_campaigns(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_wbcie_campaign_id ON whatsapp_bulk_campaign_import_errors(campaign_id);
CREATE INDEX IF NOT EXISTS idx_wbcie_import_ref ON whatsapp_bulk_campaign_import_errors(import_reference);


-- -----------------------------------------------------------------------------
-- 4. Table: whatsapp_bulk_campaign_logs
-- Comprehensive audit and lifecycle log for campaign operations.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS whatsapp_bulk_campaign_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    campaign_id UUID NOT NULL,
    recipient_id UUID NULL,
    event_type VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    metadata JSONB NULL,
    created_at TIMESTAMPTZ NULL,
    updated_at TIMESTAMPTZ NULL,
    CONSTRAINT fk_wbcl_campaign FOREIGN KEY (campaign_id) 
        REFERENCES whatsapp_bulk_campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_wbcl_recipient FOREIGN KEY (recipient_id) 
        REFERENCES whatsapp_bulk_campaign_recipients(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_wbcl_campaign_id ON whatsapp_bulk_campaign_logs(campaign_id);
CREATE INDEX IF NOT EXISTS idx_wbcl_event_type ON whatsapp_bulk_campaign_logs(event_type);
CREATE INDEX IF NOT EXISTS idx_wbcl_created_at ON whatsapp_bulk_campaign_logs(created_at);
