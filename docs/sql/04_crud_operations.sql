-- ============================================================
-- DYNAMIC RBAC SYSTEM — CRUD OPERATIONS (REFERENCE / CHEAT SHEET)
-- Unity App - PeersUnity
-- NOTE: Do NOT execute this file as a batch script!
-- This is a developer reference guide. Copy individual queries
-- and replace '00000000-0000-0000-0000-000000000000' with actual UUIDs from your DB.
-- ============================================================


-- ════════════════════════════════════════════════════════════
-- 1. ROLE CRUD
-- ════════════════════════════════════════════════════════════

-- Create a new role
-- INSERT INTO roles (id, "key", name, description, role_type, scope_rule, status, is_assignable, role_code, hierarchy_depth, created_at, updated_at)
-- VALUES (gen_random_uuid(), 'regional_manager', 'Regional Manager', 'Manages regional circles', 'admin', 'optional', 'active', TRUE, 'regional_manager', 0, NOW(), NOW());

-- Update a role
-- UPDATE roles
-- SET name = 'Updated Role Name',
--     description = 'Updated description',
--     role_type = 'admin',
--     scope_rule = 'mandatory',
--     updated_at = NOW()
-- WHERE id = '<ROLE_UUID>';

-- Delete a role
-- DELETE FROM role_hierarchies WHERE child_role_id = '<ROLE_UUID>' OR parent_role_id = '<ROLE_UUID>';
-- DELETE FROM roles WHERE id = '<ROLE_UUID>';


-- ════════════════════════════════════════════════════════════
-- 2. ROLE HIERARCHY CRUD
-- ════════════════════════════════════════════════════════════

-- Add parent-child relationship
-- INSERT INTO role_hierarchies (id, parent_role_id, child_role_id, created_at, updated_at)
-- VALUES (gen_random_uuid(), '<PARENT_ROLE_UUID>', '<CHILD_ROLE_UUID>', NOW(), NOW());

-- Move a role to new parent (delete old + insert new)
-- DELETE FROM role_hierarchies WHERE child_role_id = '<ROLE_UUID>';
-- INSERT INTO role_hierarchies (id, parent_role_id, child_role_id, created_at, updated_at)
-- VALUES (gen_random_uuid(), '<NEW_PARENT_UUID>', '<ROLE_UUID>', NOW(), NOW());

-- Remove a parent-child link
-- DELETE FROM role_hierarchies
-- WHERE parent_role_id = '<PARENT_UUID>' AND child_role_id = '<CHILD_UUID>';


-- ════════════════════════════════════════════════════════════
-- 3. MODULE CRUD
-- ════════════════════════════════════════════════════════════

-- Create module
-- INSERT INTO admin_modules (id, name, slug, icon, sort_order, is_active, created_at, updated_at)
-- VALUES (gen_random_uuid(), 'Finance & Analytics', 'finance-analytics', 'bi-cash-stack', 17, TRUE, NOW(), NOW());

-- Update module
-- UPDATE admin_modules
-- SET name = 'Financial Management',
--     slug = 'financial-management',
--     icon = 'bi-cash-stack',
--     sort_order = 17,
--     is_active = TRUE,
--     updated_at = NOW()
-- WHERE id = '<MODULE_UUID>';

-- Delete module (cascades to pages + related records)
-- DELETE FROM admin_modules WHERE id = '<MODULE_UUID>';

-- Reorder modules
-- UPDATE admin_modules SET sort_order = 1 WHERE slug = 'dashboard';
-- UPDATE admin_modules SET sort_order = 2 WHERE slug = 'members';
-- UPDATE admin_modules SET sort_order = 3 WHERE slug = 'activities';


-- ════════════════════════════════════════════════════════════
-- 4. PAGE CRUD
-- ════════════════════════════════════════════════════════════

-- Create page
-- INSERT INTO admin_pages (id, module_id, name, route_name, slug, icon, sort_order, is_active, description, created_at, updated_at)
-- VALUES (gen_random_uuid(), '<MODULE_UUID>', 'Member Audit Logs', 'admin.users.audit-logs', 'member-audit-logs', 'bi-shield-check', 11, TRUE, NULL, NOW(), NOW());

-- Update page
-- UPDATE admin_pages
-- SET name = 'Updated Page Name',
--     route_name = 'admin.updated.route',
--     slug = 'updated-page',
--     sort_order = 5,
--     updated_at = NOW()
-- WHERE id = '<PAGE_UUID>';

-- Delete page (cascades to permissions + group items)
-- DELETE FROM admin_pages WHERE id = '<PAGE_UUID>';


-- ════════════════════════════════════════════════════════════
-- 5. PERMISSION MATRIX CRUD
-- ════════════════════════════════════════════════════════════

-- Grant a permission to a role on a page
-- INSERT INTO role_page_permissions (id, role_id, page_id, permission_id, created_at, updated_at)
-- VALUES (gen_random_uuid(), '<ROLE_UUID>', '<PAGE_UUID>', (SELECT id FROM permissions WHERE "key" = 'view'), NOW(), NOW());

-- Grant multiple permissions at once
-- INSERT INTO role_page_permissions (id, role_id, page_id, permission_id, created_at, updated_at)
-- VALUES
--     (gen_random_uuid(), '<ROLE_UUID>', '<PAGE_UUID>', (SELECT id FROM permissions WHERE "key" = 'view'),   NOW(), NOW()),
--     (gen_random_uuid(), '<ROLE_UUID>', '<PAGE_UUID>', (SELECT id FROM permissions WHERE "key" = 'create'), NOW(), NOW()),
--     (gen_random_uuid(), '<ROLE_UUID>', '<PAGE_UUID>', (SELECT id FROM permissions WHERE "key" = 'edit'),   NOW(), NOW()),
--     (gen_random_uuid(), '<ROLE_UUID>', '<PAGE_UUID>', (SELECT id FROM permissions WHERE "key" = 'export'), NOW(), NOW());

-- Revoke a specific permission
-- DELETE FROM role_page_permissions
-- WHERE role_id = '<ROLE_UUID>'
--   AND page_id = '<PAGE_UUID>'
--   AND permission_id = (SELECT id FROM permissions WHERE "key" = 'delete');

-- Revoke ALL permissions for a role on a page
-- DELETE FROM role_page_permissions
-- WHERE role_id = '<ROLE_UUID>' AND page_id = '<PAGE_UUID>';

-- Revoke ALL permissions for a role (reset entire matrix)
-- DELETE FROM role_page_permissions WHERE role_id = '<ROLE_UUID>';


-- ════════════════════════════════════════════════════════════
-- 6. MODULE ACCESS CRUD
-- ════════════════════════════════════════════════════════════

-- Grant module visibility to a role
-- INSERT INTO role_module_access (id, role_id, module_id, is_visible, created_at, updated_at)
-- VALUES (gen_random_uuid(), '<ROLE_UUID>', '<MODULE_UUID>', TRUE, NOW(), NOW())
-- ON CONFLICT (role_id, module_id) DO UPDATE SET is_visible = TRUE, updated_at = NOW();

-- Revoke module visibility
-- UPDATE role_module_access
-- SET is_visible = FALSE, updated_at = NOW()
-- WHERE role_id = '<ROLE_UUID>' AND module_id = '<MODULE_UUID>';

-- Copy module access from one role to another
-- INSERT INTO role_module_access (id, role_id, module_id, is_visible, created_at, updated_at)
-- SELECT gen_random_uuid(), '<TARGET_ROLE_UUID>', module_id, is_visible, NOW(), NOW()
-- FROM role_module_access
-- WHERE role_id = '<SOURCE_ROLE_UUID>'
-- ON CONFLICT (role_id, module_id) DO UPDATE SET is_visible = EXCLUDED.is_visible, updated_at = NOW();


-- ════════════════════════════════════════════════════════════
-- 7. PAGE GROUP CRUD
-- ════════════════════════════════════════════════════════════

-- Create page group
-- INSERT INTO page_groups (id, name, slug, description, is_active, created_at, updated_at)
-- VALUES (gen_random_uuid(), 'Event Supervision', 'event-supervision', 'Event management pages', TRUE, NOW(), NOW());

-- Add pages to a group
-- INSERT INTO page_group_items (id, page_group_id, page_id, sort_order, created_at, updated_at)
-- VALUES
--     (gen_random_uuid(), '<GROUP_UUID>', '<PAGE_UUID_1>', 1, NOW(), NOW()),
--     (gen_random_uuid(), '<GROUP_UUID>', '<PAGE_UUID_2>', 2, NOW(), NOW());

-- Assign page group to a role
-- INSERT INTO role_page_groups (id, role_id, page_group_id, created_at, updated_at)
-- VALUES (gen_random_uuid(), '<ROLE_UUID>', '<GROUP_UUID>', NOW(), NOW());

-- Remove page group from a role
-- DELETE FROM role_page_groups
-- WHERE role_id = '<ROLE_UUID>' AND page_group_id = '<GROUP_UUID>';

-- Delete a page group
-- DELETE FROM page_groups WHERE id = '<GROUP_UUID>';


-- ════════════════════════════════════════════════════════════
-- 8. DATA SCOPE CRUD
-- ════════════════════════════════════════════════════════════

-- Create scope for a role (district)
-- INSERT INTO role_data_scope (id, role_id, admin_user_id, scope_type, scope_id, created_at, updated_at)
-- VALUES (gen_random_uuid(), '<ROLE_UUID>', NULL, 'district', '<DISTRICT_UUID>', NOW(), NOW());

-- Create scope for a specific user (overrides role scope)
-- INSERT INTO role_data_scope (id, role_id, admin_user_id, scope_type, scope_id, created_at, updated_at)
-- VALUES (gen_random_uuid(), NULL, '<ADMIN_USER_UUID>', 'circle', '<CIRCLE_UUID>', NOW(), NOW());

-- Create global scope
-- INSERT INTO role_data_scope (id, role_id, admin_user_id, scope_type, scope_id, created_at, updated_at)
-- VALUES (gen_random_uuid(), '<ROLE_UUID>', NULL, 'global', NULL, NOW(), NOW());

-- Delete a scope
-- DELETE FROM role_data_scope WHERE id = '<SCOPE_UUID>';


-- ════════════════════════════════════════════════════════════
-- 9. ROLE ASSIGNMENT CRUD
-- ════════════════════════════════════════════════════════════

-- Assign a role to an admin user
-- INSERT INTO admin_user_roles (id, user_id, role_id, allowed_sections, permission_type, created_at, updated_at)
-- VALUES (gen_random_uuid(), '<ADMIN_USER_UUID>', '<ROLE_UUID>', NULL, 'edit', NOW(), NOW());

-- Assign with allowed sections and view-only
-- INSERT INTO admin_user_roles (id, user_id, role_id, allowed_sections, permission_type, created_at, updated_at)
-- VALUES (gen_random_uuid(), '<ADMIN_USER_UUID>', '<ROLE_UUID>', '["Dashboard", "Activities", "Members"]', 'view', NOW(), NOW());

-- Update permission type for an assignment
-- UPDATE admin_user_roles
-- SET permission_type = 'view',
--     allowed_sections = '["Dashboard"]',
--     updated_at = NOW()
-- WHERE user_id = '<ADMIN_USER_UUID>' AND role_id = '<ROLE_UUID>';

-- Remove role from user
-- DELETE FROM admin_user_roles
-- WHERE user_id = '<ADMIN_USER_UUID>' AND role_id = '<ROLE_UUID>';

-- Remove all roles from user
-- DELETE FROM admin_user_roles WHERE user_id = '<ADMIN_USER_UUID>';


-- ════════════════════════════════════════════════════════════
-- 10. WORKFLOW RULES CRUD
-- ════════════════════════════════════════════════════════════

-- Create workflow rule
-- INSERT INTO workflow_approval_rules (id, module_id, workflow_name, approver_role_id, step_order, is_active, description, created_at, updated_at)
-- VALUES (gen_random_uuid(), '<MODULE_UUID>', 'Circle Join Request Approval', '<ROLE_UUID>', 1, TRUE, 'DED must approve first', NOW(), NOW());

-- Add second step to workflow
-- INSERT INTO workflow_approval_rules (id, module_id, workflow_name, approver_role_id, step_order, is_active, description, created_at, updated_at)
-- VALUES (gen_random_uuid(), '<MODULE_UUID>', 'Circle Join Request Approval', '<ROLE_UUID_2>', 2, TRUE, 'Global Admin final approval', NOW(), NOW());

-- Update workflow rule
-- UPDATE workflow_approval_rules
-- SET approver_role_id = '<NEW_ROLE_UUID>',
--     step_order = 2,
--     is_active = TRUE,
--     updated_at = NOW()
-- WHERE id = '<RULE_UUID>';

-- Delete workflow rule
-- DELETE FROM workflow_approval_rules WHERE id = '<RULE_UUID>';


-- ════════════════════════════════════════════════════════════
-- 11. DED / INDUSTRY DIRECTOR SCOPE ASSIGNMENT
-- ════════════════════════════════════════════════════════════

-- Assign DED to a district
-- DELETE FROM admin_ded_districts WHERE admin_user_id = '<ADMIN_USER_UUID>';
-- INSERT INTO admin_ded_districts (id, admin_user_id, user_id, district_id, district_name, state_id, state_name, created_at, updated_at)
-- VALUES (gen_random_uuid(), '<ADMIN_USER_UUID>', '<APP_USER_UUID>', '<DISTRICT_UUID>', 'Ahmedabad', '<STATE_UUID>', 'Gujarat', NOW(), NOW());

-- Assign Industry Director
-- DELETE FROM industry_director_assignments WHERE admin_user_id = '<ADMIN_USER_UUID>';
-- INSERT INTO industry_director_assignments (id, admin_user_id, industry_id, industry_name, assigned_by, is_active, created_at, updated_at)
-- VALUES (gen_random_uuid(), '<ADMIN_USER_UUID>', '<INDUSTRY_UUID>', 'IT & Software', '<ASSIGNER_UUID>', TRUE, NOW(), NOW());


