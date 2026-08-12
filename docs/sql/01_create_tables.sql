-- ============================================================
-- DYNAMIC RBAC SYSTEM — TABLE CREATION (DDL)
-- Unity App - PeersUnity
-- Run this FIRST before any other SQL file
-- ============================================================

-- Drop old tables if they were created with CHAR(36) types previously
DROP TABLE IF EXISTS workflow_approval_rules CASCADE;
DROP TABLE IF EXISTS role_data_scope CASCADE;
DROP TABLE IF EXISTS role_page_groups CASCADE;
DROP TABLE IF EXISTS page_group_items CASCADE;
DROP TABLE IF EXISTS page_groups CASCADE;
DROP TABLE IF EXISTS role_module_access CASCADE;
DROP TABLE IF EXISTS role_page_permissions CASCADE;
DROP TABLE IF EXISTS permissions CASCADE;
DROP TABLE IF EXISTS admin_pages CASCADE;
DROP TABLE IF EXISTS admin_modules CASCADE;

-- ────────────────────────────────────────────────────────────
-- 1. admin_modules — Sidebar sections (Dashboard, Members, etc.)
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admin_modules (
    id          UUID PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(100) NOT NULL UNIQUE,
    icon        VARCHAR(50) NULL,
    sort_order  INT DEFAULT 0,
    is_active   BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL
);

-- ────────────────────────────────────────────────────────────
-- 2. admin_pages — Individual pages inside modules
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admin_pages (
    id          UUID PRIMARY KEY,
    module_id   UUID NOT NULL,
    name        VARCHAR(100) NOT NULL,
    route_name  VARCHAR(255) NOT NULL,
    slug        VARCHAR(100) NOT NULL,
    icon        VARCHAR(50) NULL,
    sort_order  INT DEFAULT 0,
    is_active   BOOLEAN DEFAULT TRUE,
    description TEXT NULL,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,

    FOREIGN KEY (module_id) REFERENCES admin_modules(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_admin_pages_route_name ON admin_pages (route_name);
CREATE INDEX IF NOT EXISTS idx_admin_pages_module_id ON admin_pages (module_id);

-- ────────────────────────────────────────────────────────────
-- 3. permissions — Action types (view, create, edit, delete, etc.)
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS permissions (
    id          UUID PRIMARY KEY,
    "key"       VARCHAR(50) NOT NULL UNIQUE,
    name        VARCHAR(100) NOT NULL,
    description TEXT NULL,
    sort_order  INT DEFAULT 0,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL
);

-- ────────────────────────────────────────────────────────────
-- 4. role_page_permissions — THE PERMISSION MATRIX (core table)
--    Answers: "Can role X do action Y on page Z?"
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS role_page_permissions (
    id              UUID PRIMARY KEY,
    role_id         UUID NOT NULL,
    page_id         UUID NOT NULL,
    permission_id   UUID NOT NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (page_id) REFERENCES admin_pages(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    CONSTRAINT rpp_role_page_permission_unique UNIQUE (role_id, page_id, permission_id)
);

CREATE INDEX IF NOT EXISTS idx_rpp_role_id ON role_page_permissions (role_id);
CREATE INDEX IF NOT EXISTS idx_rpp_page_id ON role_page_permissions (page_id);

-- ────────────────────────────────────────────────────────────
-- 5. role_module_access — Module visibility per role
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS role_module_access (
    id          UUID PRIMARY KEY,
    role_id     UUID NOT NULL,
    module_id   UUID NOT NULL,
    is_visible  BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,

    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES admin_modules(id) ON DELETE CASCADE,
    CONSTRAINT uq_role_module UNIQUE (role_id, module_id)
);

-- ────────────────────────────────────────────────────────────
-- 6. page_groups — Logical grouping of pages
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS page_groups (
    id          UUID PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    is_active   BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL
);

-- ────────────────────────────────────────────────────────────
-- 7. page_group_items — Pages inside a page group
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS page_group_items (
    id              UUID PRIMARY KEY,
    page_group_id   UUID NOT NULL,
    page_id         UUID NOT NULL,
    sort_order      INT DEFAULT 0,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    FOREIGN KEY (page_group_id) REFERENCES page_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (page_id) REFERENCES admin_pages(id) ON DELETE CASCADE,
    CONSTRAINT uq_group_page UNIQUE (page_group_id, page_id)
);

-- ────────────────────────────────────────────────────────────
-- 8. role_page_groups — Assign page groups to roles
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS role_page_groups (
    id              UUID PRIMARY KEY,
    role_id         UUID NOT NULL,
    page_group_id   UUID NOT NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (page_group_id) REFERENCES page_groups(id) ON DELETE CASCADE,
    CONSTRAINT uq_role_page_group UNIQUE (role_id, page_group_id)
);

-- ────────────────────────────────────────────────────────────
-- 9. role_data_scope — Data filtering by geography/industry
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS role_data_scope (
    id              UUID PRIMARY KEY,
    role_id         UUID NULL,
    admin_user_id   UUID NULL,
    scope_type      VARCHAR(30) NOT NULL, -- global, circle, district, industry
    scope_id        UUID NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_rds_role_scope ON role_data_scope (role_id, scope_type);
CREATE INDEX IF NOT EXISTS idx_rds_user_scope ON role_data_scope (admin_user_id, scope_type);

-- ────────────────────────────────────────────────────────────
-- 10. workflow_approval_rules — Multi-step approval workflows
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS workflow_approval_rules (
    id                  UUID PRIMARY KEY,
    module_id           UUID NOT NULL,
    workflow_name       VARCHAR(100) NOT NULL,
    approver_role_id    UUID NOT NULL,
    step_order          INT DEFAULT 1,
    is_active           BOOLEAN DEFAULT TRUE,
    description         TEXT NULL,
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,

    FOREIGN KEY (module_id) REFERENCES admin_modules(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_role_id) REFERENCES roles(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_war_module_workflow ON workflow_approval_rules (module_id, workflow_name);

-- ────────────────────────────────────────────────────────────
-- 11. admin_user_roles — Extra columns for RBAC
--     (table already exists, these are ALTER statements)
-- ────────────────────────────────────────────────────────────
ALTER TABLE admin_user_roles
    ADD COLUMN IF NOT EXISTS allowed_sections JSON NULL,
    ADD COLUMN IF NOT EXISTS permission_type VARCHAR(10) DEFAULT 'edit';


