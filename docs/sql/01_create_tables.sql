-- ============================================================
-- DYNAMIC RBAC SYSTEM — TABLE CREATION (DDL)
-- Unity App - PeersUnity
-- Run this FIRST before any other SQL file
-- ============================================================

-- ────────────────────────────────────────────────────────────
-- 1. admin_modules — Sidebar sections (Dashboard, Members, etc.)
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admin_modules (
    id          CHAR(36) PRIMARY KEY,
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
    id          CHAR(36) PRIMARY KEY,
    module_id   CHAR(36) NOT NULL,
    name        VARCHAR(100) NOT NULL,
    route_name  VARCHAR(255) NOT NULL,
    slug        VARCHAR(100) NOT NULL,
    icon        VARCHAR(50) NULL,
    sort_order  INT DEFAULT 0,
    is_active   BOOLEAN DEFAULT TRUE,
    description TEXT NULL,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,

    FOREIGN KEY (module_id) REFERENCES admin_modules(id) ON DELETE CASCADE,
    INDEX idx_admin_pages_route_name (route_name),
    INDEX idx_admin_pages_module_id (module_id)
);

-- ────────────────────────────────────────────────────────────
-- 3. permissions — Action types (view, create, edit, delete, etc.)
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS permissions (
    id          CHAR(36) PRIMARY KEY,
    `key`       VARCHAR(50) NOT NULL UNIQUE,
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
    id              CHAR(36) PRIMARY KEY,
    role_id         CHAR(36) NOT NULL,
    page_id         CHAR(36) NOT NULL,
    permission_id   CHAR(36) NOT NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (page_id) REFERENCES admin_pages(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY rpp_role_page_permission_unique (role_id, page_id, permission_id),
    INDEX idx_rpp_role_id (role_id),
    INDEX idx_rpp_page_id (page_id)
);

-- ────────────────────────────────────────────────────────────
-- 5. role_module_access — Module visibility per role
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS role_module_access (
    id          CHAR(36) PRIMARY KEY,
    role_id     CHAR(36) NOT NULL,
    module_id   CHAR(36) NOT NULL,
    is_visible  BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,

    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES admin_modules(id) ON DELETE CASCADE,
    UNIQUE KEY uq_role_module (role_id, module_id)
);

-- ────────────────────────────────────────────────────────────
-- 6. page_groups — Logical grouping of pages
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS page_groups (
    id          CHAR(36) PRIMARY KEY,
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
    id              CHAR(36) PRIMARY KEY,
    page_group_id   CHAR(36) NOT NULL,
    page_id         CHAR(36) NOT NULL,
    sort_order      INT DEFAULT 0,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    FOREIGN KEY (page_group_id) REFERENCES page_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (page_id) REFERENCES admin_pages(id) ON DELETE CASCADE,
    UNIQUE KEY uq_group_page (page_group_id, page_id)
);

-- ────────────────────────────────────────────────────────────
-- 8. role_page_groups — Assign page groups to roles
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS role_page_groups (
    id              CHAR(36) PRIMARY KEY,
    role_id         CHAR(36) NOT NULL,
    page_group_id   CHAR(36) NOT NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (page_group_id) REFERENCES page_groups(id) ON DELETE CASCADE,
    UNIQUE KEY uq_role_page_group (role_id, page_group_id)
);

-- ────────────────────────────────────────────────────────────
-- 9. role_data_scope — Data filtering by geography/industry
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS role_data_scope (
    id              CHAR(36) PRIMARY KEY,
    role_id         CHAR(36) NULL,
    admin_user_id   CHAR(36) NULL,
    scope_type      VARCHAR(30) NOT NULL COMMENT 'global, circle, district, industry',
    scope_id        CHAR(36) NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    INDEX idx_rds_role_scope (role_id, scope_type),
    INDEX idx_rds_user_scope (admin_user_id, scope_type)
);

-- ────────────────────────────────────────────────────────────
-- 10. workflow_approval_rules — Multi-step approval workflows
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS workflow_approval_rules (
    id                  CHAR(36) PRIMARY KEY,
    module_id           CHAR(36) NOT NULL,
    workflow_name       VARCHAR(100) NOT NULL,
    approver_role_id    CHAR(36) NOT NULL,
    step_order          INT DEFAULT 1,
    is_active           BOOLEAN DEFAULT TRUE,
    description         TEXT NULL,
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,

    FOREIGN KEY (module_id) REFERENCES admin_modules(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_role_id) REFERENCES roles(id) ON DELETE CASCADE,
    INDEX idx_war_module_workflow (module_id, workflow_name)
);

-- ────────────────────────────────────────────────────────────
-- 11. admin_user_roles — Extra columns for RBAC
--     (table already exists, these are ALTER statements)
-- ────────────────────────────────────────────────────────────
ALTER TABLE admin_user_roles
    ADD COLUMN IF NOT EXISTS allowed_sections JSON NULL AFTER role_id,
    ADD COLUMN IF NOT EXISTS permission_type VARCHAR(10) DEFAULT 'edit' AFTER allowed_sections;
