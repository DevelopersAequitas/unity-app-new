# 🗄️ Dynamic RBAC — All Database Queries (Complete Reference)

Every query from the codebase, organized by controller/file, with the **equivalent raw SQL**.

---

## 1. Role Hierarchy Controller
**File:** [RoleHierarchyController.php](file:///c:/unity-app%2027-5-2026/unity-app%20-%20Copy/app/Http/Controllers/Admin/Rbac/RoleHierarchyController.php)

### 1.1 `index()` — Load tree page

```sql
-- Get all active roles ordered by hierarchy depth
SELECT * FROM roles WHERE status = 'active' ORDER BY hierarchy_depth;

-- Get all parent-child relationships
SELECT * FROM role_hierarchies;

-- Get all admin peers
SELECT * FROM admin_users ORDER BY name;

-- Get all districts
SELECT * FROM districts ORDER BY name;

-- Get all industries
SELECT * FROM industries ORDER BY name;

-- Get all circles
SELECT * FROM circles ORDER BY name;
```

### 1.2 `storeRole()` — Create a new role

```sql
-- Insert new role
INSERT INTO roles (id, key, name, description, role_type, scope_rule, status, is_assignable, role_code, hierarchy_depth, created_at, updated_at)
VALUES ('<UUID>', 'regional_manager', 'Regional Manager', 'Manages regions', 'user', 'not_applicable', 'active', 1, 'regional_manager', 0, NOW(), NOW());

-- Insert parent-child relationship
INSERT INTO role_hierarchies (id, parent_role_id, child_role_id, created_at, updated_at)
VALUES ('<UUID>', '<PARENT_ROLE_UUID>', '<NEW_ROLE_UUID>', NOW(), NOW());
```

### 1.3 `updateParent()` — Move role in hierarchy tree

```sql
-- Delete old parent relationships for this role
DELETE FROM role_hierarchies WHERE child_role_id = '<ROLE_UUID>';

-- Insert new parent relationships
INSERT INTO role_hierarchies (id, parent_role_id, child_role_id, created_at, updated_at)
VALUES ('<UUID>', '<NEW_PARENT_UUID>', '<ROLE_UUID>', NOW(), NOW());
```

### 1.4 `updateRole()` — Update role details

```sql
-- Update role fields
UPDATE roles
SET name = 'Updated Name', key = 'updated_key', role_code = 'updated_key',
    description = '...', role_type = 'admin', scope_rule = 'mandatory',
    updated_at = NOW()
WHERE id = '<ROLE_UUID>';

-- Delete old parent links and re-create
DELETE FROM role_hierarchies WHERE child_role_id = '<ROLE_UUID>';

INSERT INTO role_hierarchies (id, parent_role_id, child_role_id, created_at, updated_at)
VALUES ('<UUID>', '<PARENT_UUID>', '<ROLE_UUID>', NOW(), NOW());
```

### 1.5 `deleteRole()` — Delete a role

```sql
-- Remove all hierarchy references (as parent or child)
DELETE FROM role_hierarchies
WHERE child_role_id = '<ROLE_UUID>' OR parent_role_id = '<ROLE_UUID>';

-- Delete the role itself
DELETE FROM roles WHERE id = '<ROLE_UUID>';
```

### 1.6 `cloneProfile()` — Clone a role with its permissions

```sql
-- Check if key already exists
SELECT EXISTS(SELECT 1 FROM roles WHERE key = 'cloned_key') AS key_exists;

-- Create new cloned role
INSERT INTO roles (id, key, name, description, role_type, scope_rule, status, is_assignable, role_code, hierarchy_depth, created_at, updated_at)
VALUES ('<NEW_UUID>', 'cloned_key', 'Cloned Role', NULL, 'admin', 'optional', 'active', 1, 'cloned_key', 0, NOW(), NOW());

-- Insert parent relationships
INSERT INTO role_hierarchies (id, parent_role_id, child_role_id, created_at, updated_at)
VALUES ('<UUID>', '<PARENT_UUID>', '<NEW_UUID>', NOW(), NOW());

-- Copy permissions from source role
SELECT * FROM rbac_role_permission_groups WHERE role_id = '<SOURCE_ROLE_UUID>';

INSERT INTO rbac_role_permission_groups (id, role_id, permission_group_id, created_at, updated_at)
VALUES ('<UUID>', '<NEW_ROLE_UUID>', '<PERMISSION_GROUP_ID>', NOW(), NOW());
```

### 1.7 `getAssignments()` — Get peers assigned to a role

```sql
-- Get all users assigned to this role with their details
SELECT
    admin_user_roles.id AS assignment_id,
    admin_users.id AS user_id,
    admin_users.name,
    admin_users.email,
    admin_user_roles.allowed_sections,
    admin_user_roles.permission_type
FROM admin_user_roles
JOIN admin_users ON admin_user_roles.user_id = admin_users.id
WHERE admin_user_roles.role_id = '<ROLE_UUID>';

-- For DED roles: get district scope
SELECT * FROM admin_ded_districts WHERE admin_user_id = '<USER_UUID>';

-- For Industry Director roles: get industry scope
SELECT * FROM industry_director_assignments
WHERE admin_user_id = '<USER_UUID>' AND is_active = true;

-- For Circle roles: find app user by email
SELECT * FROM users WHERE LOWER(email) = '<lowercase_email>';

-- Find circle by leader column
SELECT * FROM circles WHERE chair_user_id = '<APP_USER_UUID>';
-- or: circle_director_user_id, circle_founder_user_id, vice_chair_user_id, secretary_user_id

-- Find circle via circle_members
SELECT circles.* FROM circles
JOIN circle_members ON circles.id = circle_members.circle_id
WHERE circle_members.user_id = '<APP_USER_UUID>'
  AND circle_members.role::text IN ('chair', 'circle_director', 'director')
  AND circle_members.deleted_at IS NULL;

-- Get available peers for assignment
SELECT id, name, email FROM admin_users ORDER BY name;
```

### 1.8 `enrichPeersWithScopes()` — Enrich peer data with scope info

```sql
-- DED District mappings
SELECT * FROM admin_ded_districts WHERE admin_user_id IN ('<id1>', '<id2>', ...);

-- Resolve district names to IDs
SELECT id, name FROM districts WHERE LOWER(TRIM(name)) IN ('ahmedabad', 'surat', ...);

-- Industry Director assignments
SELECT * FROM industry_director_assignments
WHERE admin_user_id IN ('<id1>', '<id2>', ...) AND is_active = true;

-- App users by email
SELECT * FROM users WHERE LOWER(TRIM(email)) IN ('email1@example.com', 'email2@example.com', ...);

-- City-to-district mapping
SELECT id, name FROM cities WHERE id IN ('<city_id1>', '<city_id2>', ...);
SELECT id, name FROM districts WHERE LOWER(TRIM(name)) IN ('city_name1', 'city_name2', ...);

-- Industry names mapping
SELECT id, name FROM industries;

-- Circle memberships
SELECT user_id, circle_id FROM circle_members WHERE user_id IN ('<uid1>', '<uid2>', ...);

-- Circle join requests
SELECT user_id, circle_id FROM circle_join_requests WHERE user_id IN ('<uid1>', '<uid2>', ...);

-- Circle leaders lookup
SELECT * FROM circles
WHERE circle_director_user_id IN ('<uid1>', '<uid2>', ...)
   OR circle_founder_user_id IN ('<uid1>', '<uid2>', ...)
   OR chair_user_id IN ('<uid1>', '<uid2>', ...)
   OR vice_chair_user_id IN ('<uid1>', '<uid2>', ...)
   OR secretary_user_id IN ('<uid1>', '<uid2>', ...);

-- Category to industry resolution
SELECT * FROM circle_categories WHERE id = '<category_uuid>';
```

### 1.9 `assignPeer()` / `performAssignment()` — Assign a peer to a role

```sql
-- Find admin user
SELECT * FROM admin_users WHERE id = '<ADMIN_USER_UUID>';

-- Get super role IDs (to detach conflicting roles)
SELECT id FROM roles WHERE key IN ('global_admin', 'global_founder');

-- Remove conflicting super roles when assigning a scoped role
DELETE FROM admin_user_roles
WHERE user_id = '<ADMIN_USER_UUID>' AND role_id IN ('<SUPER_ROLE_ID1>', '<SUPER_ROLE_ID2>');

-- Check if this assignment already exists
SELECT * FROM admin_user_roles
WHERE user_id = '<ADMIN_USER_UUID>' AND role_id = '<ROLE_UUID>';

-- INSERT new role assignment (if not exists)
INSERT INTO admin_user_roles (id, user_id, role_id, allowed_sections, permission_type, created_at, updated_at)
VALUES ('<UUID>', '<ADMIN_USER_UUID>', '<ROLE_UUID>', '["Dashboard","Activities"]', 'edit', NOW(), NOW());

-- UPDATE existing role assignment (if exists)
UPDATE admin_user_roles
SET allowed_sections = '["Dashboard","Activities"]', permission_type = 'edit', updated_at = NOW()
WHERE id = '<ASSIGNMENT_UUID>';
```

**For DED Scope Assignment:**
```sql
-- Resolve district and state
SELECT * FROM districts WHERE id = '<DISTRICT_UUID>';
SELECT * FROM states WHERE id = '<STATE_UUID>';

-- Find matching app user
SELECT * FROM users WHERE LOWER(email) = '<email>';

-- Delete old DED district mapping and insert new
DELETE FROM admin_ded_districts WHERE admin_user_id = '<ADMIN_USER_UUID>';

INSERT INTO admin_ded_districts (id, admin_user_id, user_id, district_id, district_name, state_id, state_name, created_at, updated_at)
VALUES ('<UUID>', '<ADMIN_USER_UUID>', '<APP_USER_UUID>', '<DISTRICT_UUID>', 'Ahmedabad', '<STATE_UUID>', 'Gujarat', NOW(), NOW());

-- Update app user's circle membership role to DED
UPDATE circle_members SET role = 'ded', updated_at = NOW()
WHERE user_id = '<APP_USER_UUID>' AND deleted_at IS NULL;
```

**For Industry Director Scope Assignment:**
```sql
SELECT * FROM industries WHERE id = '<INDUSTRY_UUID>';

DELETE FROM industry_director_assignments WHERE admin_user_id = '<ADMIN_USER_UUID>';

INSERT INTO industry_director_assignments (id, admin_user_id, industry_id, industry_name, assigned_by, is_active, created_at, updated_at)
VALUES ('<UUID>', '<ADMIN_USER_UUID>', '<INDUSTRY_UUID>', 'IT & Software', '<ASSIGNER_UUID>', true, NOW(), NOW());
```

**For Circle Scope Assignment:**
```sql
-- Find app user by email
SELECT * FROM users WHERE LOWER(email) = '<email>';

-- Clear old circle leader assignment
UPDATE circles SET chair_user_id = NULL, updated_at = NOW()
WHERE chair_user_id = '<APP_USER_UUID>';

-- Set new circle leader
UPDATE circles SET chair_user_id = '<APP_USER_UUID>', updated_at = NOW()
WHERE id = '<CIRCLE_UUID>';

-- Delete old circle_members role entries
DELETE FROM circle_members
WHERE user_id = '<APP_USER_UUID>' AND circle_members.role::text IN ('chair');

-- Check if user already a member of this circle
SELECT * FROM circle_members
WHERE circle_id = '<CIRCLE_UUID>' AND user_id = '<APP_USER_UUID>';

-- UPDATE existing membership role
UPDATE circle_members SET role = 'chair', status = 'approved', updated_at = NOW(), deleted_at = NULL
WHERE id = '<MEMBERSHIP_UUID>';

-- OR INSERT new membership
INSERT INTO circle_members (id, circle_id, user_id, role, status, created_at, updated_at)
VALUES ('<UUID>', '<CIRCLE_UUID>', '<APP_USER_UUID>', 'chair', 'approved', NOW(), NOW());

-- Clear permission cache
DELETE FROM tbl_permission_cache WHERE user_id = '<APP_USER_UUID>';
```

### 1.10 `removeAssignment()` — Revoke role from a peer

```sql
-- Delete role assignment
DELETE FROM admin_user_roles
WHERE user_id = '<USER_UUID>' AND role_id = '<ROLE_UUID>';

-- For DED: clean up district mapping
DELETE FROM admin_ded_districts WHERE admin_user_id = '<USER_UUID>';

-- For Industry Director: clean up
DELETE FROM industry_director_assignments WHERE admin_user_id = '<USER_UUID>';

-- For Circle: clear leader columns and memberships
UPDATE circles SET chair_user_id = NULL, updated_at = NOW()
WHERE chair_user_id = '<APP_USER_UUID>';

DELETE FROM circle_members
WHERE user_id = '<APP_USER_UUID>' AND circle_members.role::text IN ('chair');

DELETE FROM tbl_permission_cache WHERE user_id = '<APP_USER_UUID>';
```

### 1.11 `removeCurrentRole()` — Admin removes their own role

```sql
-- Get user's current roles for audit
SELECT roles.key FROM roles
JOIN admin_user_roles ON admin_user_roles.role_id = roles.id
WHERE admin_user_roles.user_id = '<ADMIN_UUID>';

-- Get or create 'user' role
SELECT * FROM roles WHERE key = 'user';

INSERT INTO roles (id, key, name, description, created_at, updated_at)
VALUES ('<UUID>', 'user', 'User', 'Default User Role', NOW(), NOW());

-- Delete all current role assignments
DELETE FROM admin_user_roles WHERE user_id = '<ADMIN_UUID>';

-- Assign default 'user' role
INSERT INTO admin_user_roles (id, user_id, role_id, created_at)
VALUES ('<UUID>', '<ADMIN_UUID>', '<USER_ROLE_UUID>', NOW());

-- Deactivate industry director assignments
UPDATE industry_director_assignments
SET is_active = false, updated_at = NOW()
WHERE admin_user_id = '<ADMIN_UUID>';

-- Delete DED district scope
DELETE FROM admin_ded_districts WHERE admin_user_id = '<ADMIN_UUID>';
```

---

## 2. Permission Matrix Controller
**File:** [RolePermissionMatrixController.php](file:///c:/unity-app%2027-5-2026/unity-app%20-%20Copy/app/Http/Controllers/Admin/Rbac/RolePermissionMatrixController.php)

### 2.1 `index()` — Load permission matrix page

```sql
-- Get all non-super active roles
SELECT * FROM roles
WHERE status = 'active' AND key NOT IN ('global_admin', 'global_founder')
ORDER BY hierarchy_depth, name;

-- Get active modules with active pages (filtered by role's visible modules)
SELECT * FROM admin_modules WHERE is_active = 1 ORDER BY sort_order;
SELECT * FROM admin_pages WHERE module_id IN (...) AND is_active = 1 ORDER BY sort_order;

-- Check if role has any module access rules
SELECT EXISTS(SELECT 1 FROM role_module_access WHERE role_id = '<ROLE_UUID>') AS has_access_rules;

-- Get visible module IDs for this role
SELECT module_id FROM role_module_access
WHERE role_id = '<ROLE_UUID>' AND is_visible = 1;

-- Get all permissions
SELECT * FROM permissions ORDER BY sort_order;

-- Get assigned page IDs for this role (current permissions)
SELECT DISTINCT page_id FROM role_page_permissions
WHERE role_id = '<ROLE_UUID>';
```

### 2.2 `update()` — Save permission matrix

```sql
-- Delete ALL existing permissions for this role
DELETE FROM role_page_permissions WHERE role_id = '<ROLE_UUID>';

-- Get the 'view' permission
SELECT * FROM permissions WHERE key = 'view';

-- Insert new page-level permissions (single checkbox mode)
INSERT INTO role_page_permissions (id, role_id, page_id, permission_id, created_at, updated_at)
VALUES ('<UUID>', '<ROLE_UUID>', '<PAGE_UUID>', '<VIEW_PERM_UUID>', NOW(), NOW());

-- OR: Insert granular permissions (2D matrix mode)
INSERT INTO role_page_permissions (id, role_id, page_id, permission_id, created_at, updated_at)
VALUES ('<UUID>', '<ROLE_UUID>', '<PAGE_UUID>', '<EDIT_PERM_UUID>', NOW(), NOW());
```

---

## 3. Module Access Controller
**File:** [RoleModuleAccessController.php](file:///c:/unity-app%2027-5-2026/unity-app%20-%20Copy/app/Http/Controllers/Admin/Rbac/RoleModuleAccessController.php)

### 3.1 `index()` — Load module visibility page

```sql
-- Get all non-super active roles
SELECT * FROM roles
WHERE status = 'active' AND key NOT IN ('global_admin', 'global_founder')
ORDER BY hierarchy_depth, name;

-- Get all active modules
SELECT * FROM admin_modules WHERE is_active = 1 ORDER BY sort_order;

-- Get current module access for selected role
SELECT * FROM role_module_access WHERE role_id = '<ROLE_UUID>';
```

### 3.2 `update()` — Save module visibility toggles

```sql
-- Get all module IDs
SELECT id FROM admin_modules;

-- For each module: update or create visibility toggle
-- (runs for EVERY module in the system)
INSERT INTO role_module_access (id, role_id, module_id, is_visible, created_at, updated_at)
VALUES ('<UUID>', '<ROLE_UUID>', '<MODULE_UUID>', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE is_visible = 1, updated_at = NOW();
-- (Eloquent `updateOrCreate` translates to INSERT...ON DUPLICATE KEY UPDATE)
```

---

## 4. Data Scope Controller
**File:** [RoleDataScopeController.php](file:///c:/unity-app%2027-5-2026/unity-app%20-%20Copy/app/Http/Controllers/Admin/Rbac/RoleDataScopeController.php)

### 4.1 `index()` — List data scope rules

```sql
-- Get all data scopes with their role, paginated
SELECT rds.*, r.name AS role_name
FROM role_data_scope rds
LEFT JOIN roles r ON r.id = rds.role_id
ORDER BY rds.created_at DESC
LIMIT 50 OFFSET 0;

-- Get roles (excluding super admins)
SELECT * FROM roles
WHERE status = 'active' AND key NOT IN ('global_admin', 'global_founder')
ORDER BY name;

-- Get circles, industries, districts for dropdown
SELECT id, name FROM circles ORDER BY name LIMIT 200;
SELECT id, name FROM industries ORDER BY name;
SELECT id, name FROM districts ORDER BY name;
```

### 4.2 `store()` — Create a data scope rule

```sql
INSERT INTO role_data_scope (id, role_id, admin_user_id, scope_type, scope_id, created_at, updated_at)
VALUES ('<UUID>', '<ROLE_UUID>', NULL, 'district', '<DISTRICT_UUID>', NOW(), NOW());
```

### 4.3 `destroy()` — Delete a data scope rule

```sql
DELETE FROM role_data_scope WHERE id = '<SCOPE_UUID>';
```

---

## 5. Workflow Approval Rules Controller
**File:** [WorkflowApprovalRuleController.php](file:///c:/unity-app%2027-5-2026/unity-app%20-%20Copy/app/Http/Controllers/Admin/Rbac/WorkflowApprovalRuleController.php)

### 5.1 `index()` — List workflow rules

```sql
-- Get all workflow rules with module and approver role
SELECT war.*, am.name AS module_name, r.name AS approver_role_name
FROM workflow_approval_rules war
JOIN admin_modules am ON am.id = war.module_id
JOIN roles r ON r.id = war.approver_role_id
ORDER BY war.module_id, war.workflow_name, war.step_order
LIMIT 50 OFFSET 0;

-- Get active modules
SELECT * FROM admin_modules WHERE is_active = 1 ORDER BY sort_order;

-- Get roles (excluding super admins)
SELECT * FROM roles
WHERE status = 'active' AND key NOT IN ('global_admin', 'global_founder')
ORDER BY name;
```

### 5.2 `store()` — Create a workflow rule

```sql
INSERT INTO workflow_approval_rules (id, module_id, workflow_name, approver_role_id, step_order, is_active, description, created_at, updated_at)
VALUES ('<UUID>', '<MODULE_UUID>', 'Circle Join Request Approval', '<ROLE_UUID>', 1, true, 'DED must approve first', NOW(), NOW());
```

### 5.3 `update()` — Update a workflow rule

```sql
UPDATE workflow_approval_rules
SET module_id = '<MODULE_UUID>', workflow_name = 'Updated Name',
    approver_role_id = '<ROLE_UUID>', step_order = 2,
    is_active = true, description = '...', updated_at = NOW()
WHERE id = '<RULE_UUID>';
```

### 5.4 `destroy()` — Delete a workflow rule

```sql
DELETE FROM workflow_approval_rules WHERE id = '<RULE_UUID>';
```

---

## 6. Admin Module Controller
**File:** [AdminModuleController.php](file:///c:/unity-app%2027-5-2026/unity-app%20-%20Copy/app/Http/Controllers/Admin/Rbac/AdminModuleController.php)

### 6.1 `index()` — List modules

```sql
-- Get all modules with page count
SELECT am.*, COUNT(ap.id) AS pages_count
FROM admin_modules am
LEFT JOIN admin_pages ap ON ap.module_id = am.id
GROUP BY am.id
ORDER BY am.sort_order;
```

### 6.2 `create()` — Get next sort order

```sql
SELECT MAX(sort_order) + 1 AS next_order FROM admin_modules;
```

### 6.3 `store()` — Create a module (with optional quick pages)

```sql
INSERT INTO admin_modules (id, name, slug, icon, sort_order, is_active, created_at, updated_at)
VALUES ('<UUID>', 'Finance & Analytics', 'finance-analytics', 'bi-cash-stack', 17, true, NOW(), NOW());

-- Quick pages (if submitted along with module)
INSERT INTO admin_pages (id, module_id, name, route_name, slug, icon, sort_order, is_active, created_at, updated_at)
VALUES ('<UUID>', '<MODULE_UUID>', 'Revenue Dashboard', 'admin.finance.revenue', 'revenue-dashboard', 'bi-cash-stack', 1, true, NOW(), NOW());
```

### 6.4 `update()` — Update a module

```sql
UPDATE admin_modules
SET name = 'Financial Management', slug = 'financial-management',
    icon = 'bi-cash-stack', sort_order = 17, is_active = true, updated_at = NOW()
WHERE id = '<MODULE_UUID>';
```

### 6.5 `destroy()` — Delete a module (cascades to pages)

```sql
DELETE FROM admin_modules WHERE id = '<MODULE_UUID>';
-- CASCADE: also deletes all admin_pages, role_page_permissions, and role_module_access for this module
```

### 6.6 `updateOrder()` — Reorder modules

```sql
-- Runs for each module in the new order
UPDATE admin_modules SET sort_order = 0 WHERE id = '<MODULE_1_UUID>';
UPDATE admin_modules SET sort_order = 1 WHERE id = '<MODULE_2_UUID>';
UPDATE admin_modules SET sort_order = 2 WHERE id = '<MODULE_3_UUID>';
-- ... etc
```

---

## 7. Admin Page Controller
**File:** [AdminPageController.php](file:///c:/unity-app%2027-5-2026/unity-app%20-%20Copy/app/Http/Controllers/Admin/Rbac/AdminPageController.php)

### 7.1 `index()` — List pages

```sql
-- Get modules for filter dropdown
SELECT * FROM admin_modules ORDER BY sort_order;

-- Get pages (optionally filtered by module), paginated
SELECT ap.*, am.name AS module_name
FROM admin_pages ap
JOIN admin_modules am ON am.id = ap.module_id
WHERE ap.module_id = '<SELECTED_MODULE_UUID>'  -- optional filter
ORDER BY ap.sort_order
LIMIT 50 OFFSET 0;
```

### 7.2 `store()` — Create a page

```sql
INSERT INTO admin_pages (id, module_id, name, route_name, slug, page_url, icon, sort_order, is_active, description, created_at, updated_at)
VALUES ('<UUID>', '<MODULE_UUID>', 'Member Audit Logs', 'admin.users.audit-logs', 'member-audit-logs', NULL, 'bi-shield-check', 11, true, NULL, NOW(), NOW());
```

### 7.3 `update()` — Update a page

```sql
UPDATE admin_pages
SET module_id = '<MODULE_UUID>', name = 'Updated Page', route_name = 'admin.updated.route',
    slug = 'updated-page', page_url = NULL, icon = 'bi-star',
    sort_order = 5, is_active = true, description = '...', updated_at = NOW()
WHERE id = '<PAGE_UUID>';
```

### 7.4 `destroy()` — Delete a page

```sql
DELETE FROM admin_pages WHERE id = '<PAGE_UUID>';
-- CASCADE: also deletes role_page_permissions and page_group_items for this page
```

---

## 8. Page Group Controller
**File:** [PageGroupController.php](file:///c:/unity-app%2027-5-2026/unity-app%20-%20Copy/app/Http/Controllers/Admin/Rbac/PageGroupController.php)

### 8.1 `index()` — List page groups

```sql
-- Get all page groups with page count and page details
SELECT pg.*, COUNT(pgi.id) AS pages_count
FROM page_groups pg
LEFT JOIN page_group_items pgi ON pgi.page_group_id = pg.id
GROUP BY pg.id
ORDER BY pg.name;

-- Pages within each group (eager loaded)
SELECT ap.*, am.name AS module_name
FROM admin_pages ap
JOIN page_group_items pgi ON pgi.page_id = ap.id
JOIN admin_modules am ON am.id = ap.module_id
WHERE pgi.page_group_id = '<GROUP_UUID>';
```

### 8.2 `store()` — Create a page group

```sql
INSERT INTO page_groups (id, name, slug, description, is_active, created_at, updated_at)
VALUES ('<UUID>', 'Event Supervision', 'event-supervision', 'Event management pages', true, NOW(), NOW());

-- Insert page group items
INSERT INTO page_group_items (id, page_group_id, page_id, sort_order, created_at, updated_at)
VALUES ('<UUID>', '<GROUP_UUID>', '<PAGE_UUID_1>', 1, NOW(), NOW());

INSERT INTO page_group_items (id, page_group_id, page_id, sort_order, created_at, updated_at)
VALUES ('<UUID>', '<GROUP_UUID>', '<PAGE_UUID_2>', 2, NOW(), NOW());
```

### 8.3 `update()` — Update a page group

```sql
UPDATE page_groups
SET name = 'Updated Group', slug = 'updated-group',
    description = '...', is_active = true, updated_at = NOW()
WHERE id = '<GROUP_UUID>';

-- Delete all existing items and re-create
DELETE FROM page_group_items WHERE page_group_id = '<GROUP_UUID>';

INSERT INTO page_group_items (id, page_group_id, page_id, sort_order, created_at, updated_at)
VALUES ('<UUID>', '<GROUP_UUID>', '<PAGE_UUID>', 1, NOW(), NOW());
```

### 8.4 `destroy()` — Delete a page group

```sql
DELETE FROM page_groups WHERE id = '<GROUP_UUID>';
-- CASCADE: also deletes page_group_items and role_page_groups
```

---

## 9. Permission Service (The Brain)
**File:** [PermissionService.php](file:///c:/unity-app%2027-5-2026/unity-app%20-%20Copy/app/Services/Admin/PermissionService.php)

### 9.1 `adminRoleIds()` — Get role IDs for an admin user

```sql
SELECT role_id FROM admin_user_roles WHERE user_id = '<ADMIN_UUID>';
```

### 9.2 `isSuperAdmin()` — Check if user is global admin

```sql
SELECT EXISTS(
    SELECT 1 FROM roles
    WHERE id IN ('<role_id_1>', '<role_id_2>')
      AND key IN ('global_admin', 'global_founder')
) AS is_super;
```

### 9.3 `hasDynamicRbacData()` — Check if RBAC data exists for roles

```sql
SELECT EXISTS(
    SELECT 1 FROM role_module_access
    WHERE role_id IN ('<role_id_1>', '<role_id_2>')
) AS has_data;
```

### 9.4 `pageByRoute()` — Find page by Laravel route name

```sql
SELECT * FROM admin_pages
WHERE route_name = 'admin.users.index' AND is_active = 1
LIMIT 1;
```

### 9.5 `hasPageAccess()` — Check if role has page access

```sql
-- Check direct page permission
SELECT EXISTS(
    SELECT 1 FROM role_page_permissions
    WHERE role_id IN ('<role_id_1>', '<role_id_2>') AND page_id = '<PAGE_UUID>'
) AS has_direct_access;

-- Check via page groups
SELECT DISTINCT page_group_items.page_id
FROM role_page_groups
JOIN page_group_items ON page_group_items.page_group_id = role_page_groups.page_group_id
JOIN page_groups ON page_groups.id = role_page_groups.page_group_id
WHERE role_page_groups.role_id IN ('<role_id_1>', '<role_id_2>')
  AND page_groups.is_active = 1;

-- Check if parent module is visible
SELECT EXISTS(
    SELECT 1 FROM role_module_access
    WHERE role_id IN ('<role_id_1>', '<role_id_2>')
      AND module_id = '<MODULE_UUID>'
      AND is_visible = 1
) AS module_visible;

-- Check if any page-level permissions exist for this module
SELECT EXISTS(
    SELECT 1 FROM role_page_permissions
    JOIN admin_pages ON admin_pages.id = role_page_permissions.page_id
    WHERE role_page_permissions.role_id IN ('<role_id_1>', '<role_id_2>')
      AND admin_pages.module_id = '<MODULE_UUID>'
) AS has_module_page_perms;
```

### 9.6 `can()` — Check if admin can do specific action on a page

```sql
-- Get permission ID by key
SELECT id FROM permissions WHERE key = 'export';

-- Check if role has this specific permission on this page
SELECT EXISTS(
    SELECT 1 FROM role_page_permissions
    WHERE role_id IN ('<role_id_1>', '<role_id_2>')
      AND page_id = '<PAGE_UUID>'
      AND permission_id = '<PERMISSION_UUID>'
) AS has_permission;
```

### 9.7 `visibleModules()` — Get sidebar modules for an admin

```sql
-- Get visible module IDs
SELECT DISTINCT module_id FROM role_module_access
WHERE role_id IN ('<role_id_1>', '<role_id_2>') AND is_visible = 1;

-- Get directly assigned page IDs
SELECT DISTINCT page_id FROM role_page_permissions
WHERE role_id IN ('<role_id_1>', '<role_id_2>');

-- Get pages accessible via groups
SELECT DISTINCT page_group_items.page_id
FROM role_page_groups
JOIN page_group_items ON page_group_items.page_group_id = role_page_groups.page_group_id
JOIN page_groups ON page_groups.id = role_page_groups.page_group_id
WHERE role_page_groups.role_id IN ('<role_id_1>', '<role_id_2>')
  AND page_groups.is_active = 1;

-- Get modules that have page-level permissions
SELECT DISTINCT admin_pages.module_id
FROM role_page_permissions
JOIN admin_pages ON admin_pages.id = role_page_permissions.page_id
WHERE role_page_permissions.role_id IN ('<role_id_1>', '<role_id_2>');

-- Get visible modules with their accessible pages
SELECT * FROM admin_modules
WHERE is_active = 1 AND id IN ('<visible_module_ids>')
ORDER BY sort_order;

SELECT * FROM admin_pages
WHERE is_active = 1 AND (id IN ('<accessible_page_ids>') OR module_id NOT IN ('<modules_with_page_perms>'))
ORDER BY sort_order;
```

### 9.8 `userPermissionTree()` — Full permission tree for API response

```sql
-- All the queries from visibleModules() PLUS:

-- Get permission details per page
SELECT role_page_permissions.page_id, permissions.key AS perm_key
FROM role_page_permissions
JOIN permissions ON permissions.id = role_page_permissions.permission_id
WHERE role_page_permissions.role_id IN ('<role_id_1>', '<role_id_2>');
```

### 9.9 `dataScope()` — Resolve data scope for admin

```sql
-- Check for global scope
SELECT EXISTS(
    SELECT 1 FROM roles
    WHERE id IN ('<role_ids>') AND key IN ('global_admin', 'global_founder')
) AS is_global_role;

SELECT EXISTS(
    SELECT 1 FROM role_data_scope
    WHERE role_id IN ('<role_ids>') AND scope_type = 'global'
) AS is_global_scope;

-- Get scopes (user-specific first, then role-based)
SELECT * FROM role_data_scope
WHERE (admin_user_id = '<ADMIN_UUID>' OR role_id IN ('<role_ids>'))
ORDER BY CASE WHEN admin_user_id IS NOT NULL THEN 0 ELSE 1 END;

-- For district scope: resolve circles
SELECT id FROM circles WHERE district_id = '<DISTRICT_UUID>';

-- For district: get state
SELECT state_id FROM districts WHERE id = '<DISTRICT_UUID>';

-- For industry scope: resolve circles
SELECT circles.id FROM circles
JOIN circle_industry (or industry relationship) -- via Industry model circles() relationship
WHERE industry_id IN ('<industry_ids>');
```

### 9.10 Cache invalidation queries

```sql
-- Get all users who have a specific role (for cache busting)
SELECT user_id FROM admin_user_roles WHERE role_id = '<ROLE_UUID>';
```

---

## 10. RBAC API Controller
**File:** [RbacUserPermissionController.php](file:///c:/unity-app%2027-5-2026/unity-app%20-%20Copy/app/Http/Controllers/Api/V1/RbacUserPermissionController.php)

### 10.1 `myPermissions()` — Get current user's RBAC summary

```sql
-- Find admin user by ID or email
SELECT * FROM admin_users
WHERE id = '<USER_UUID>' OR email = '<user_email>';

-- Get user's roles
SELECT roles.id, roles.key, roles.name, roles.role_type, roles.scope_rule
FROM admin_user_roles
JOIN roles ON admin_user_roles.role_id = roles.id
WHERE admin_user_roles.user_id = '<ADMIN_UUID>';

-- Then calls: userPermissionTree(), visibleModules(), dataScope()
-- (see Section 9 above for those queries)
```

---

## 11. DynamicRbacSeeder (Initial Data Setup)
**File:** [DynamicRbacSeeder.php](file:///c:/unity-app%2027-5-2026/unity-app%20-%20Copy/database/seeders/DynamicRbacSeeder.php)

### 11.1 Seed permissions

```sql
-- For each permission (firstOrCreate):
INSERT INTO permissions (id, key, name, description, sort_order, created_at, updated_at)
SELECT '<UUID>', 'view', 'View', 'View records and pages', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE key = 'view');
-- Repeated for: view, create, edit, delete, approve, reject, export, import, print, restore
```

### 11.2 Seed modules and pages

```sql
-- For each module (firstOrCreate by slug):
INSERT INTO admin_modules (id, name, slug, icon, sort_order, is_active, created_at, updated_at)
SELECT '<UUID>', 'Dashboard', 'dashboard', 'bi-speedometer2', 1, true, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_modules WHERE slug = 'dashboard');

-- For each page within each module (firstOrCreate by route_name):
INSERT INTO admin_pages (id, module_id, name, route_name, slug, icon, sort_order, is_active, created_at, updated_at)
SELECT '<UUID>', '<MODULE_UUID>', 'Main Dashboard', 'admin.dashboard', 'main-dashboard', NULL, 1, true, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.dashboard');
```

### 11.3 Seed page groups

```sql
-- Create page group (firstOrCreate by slug):
INSERT INTO page_groups (id, name, slug, description, is_active, created_at, updated_at)
SELECT '<UUID>', 'Membership Management', 'membership-management', 'All pages related to member management', true, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM page_groups WHERE slug = 'membership-management');

-- Find page by route and link to group:
SELECT id FROM admin_pages WHERE route_name = 'admin.users.index';

INSERT INTO page_group_items (id, page_group_id, page_id, sort_order, created_at, updated_at)
SELECT '<UUID>', '<GROUP_UUID>', '<PAGE_UUID>', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM page_group_items WHERE page_group_id = '<GROUP_UUID>' AND page_id = '<PAGE_UUID>');
```

### 11.4 Seed role module access

```sql
-- Get all module IDs by slug
SELECT id, slug FROM admin_modules;

-- For each role + module combo (firstOrCreate):
INSERT INTO role_module_access (id, role_id, module_id, is_visible, created_at, updated_at)
SELECT '<UUID>', '<ROLE_UUID>', '<MODULE_UUID>', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM role_module_access WHERE role_id = '<ROLE_UUID>' AND module_id = '<MODULE_UUID>');
```

### 11.5 Seed role page permissions

```sql
-- Get permission IDs
SELECT id, key FROM permissions;

-- Get accessible pages for role's modules
SELECT * FROM admin_pages
WHERE module_id IN ('<accessible_module_ids>') AND is_active = 1;

-- For each role + page + permission combo (firstOrCreate):
INSERT INTO role_page_permissions (id, role_id, page_id, permission_id, created_at, updated_at)
SELECT '<UUID>', '<ROLE_UUID>', '<PAGE_UUID>', '<PERM_UUID>', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM role_page_permissions
    WHERE role_id = '<ROLE_UUID>' AND page_id = '<PAGE_UUID>' AND permission_id = '<PERM_UUID>'
);
```

### 11.6 Seed role page groups

```sql
-- Find page group by slug
SELECT * FROM page_groups WHERE slug = 'membership-management';

-- Assign group to role (firstOrCreate):
INSERT INTO role_page_groups (id, role_id, page_group_id, created_at, updated_at)
SELECT '<UUID>', '<ROLE_UUID>', '<GROUP_UUID>', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM role_page_groups WHERE role_id = '<ROLE_UUID>' AND page_group_id = '<GROUP_UUID>');
```

---

## 12. Role Model Helper Queries
**File:** [Role.php](file:///c:/unity-app%2027-5-2026/unity-app%20-%20Copy/app/Models/Role.php)

```sql
-- Get parent roles
SELECT roles.* FROM roles
JOIN role_hierarchies ON role_hierarchies.parent_role_id = roles.id
WHERE role_hierarchies.child_role_id = '<ROLE_UUID>';

-- Get child roles
SELECT roles.* FROM roles
JOIN role_hierarchies ON role_hierarchies.child_role_id = roles.id
WHERE role_hierarchies.parent_role_id = '<ROLE_UUID>';

-- Find role ID by key
SELECT id FROM roles WHERE key = 'chair';

-- Normalized key lookup (fuzzy)
SELECT id, key FROM roles;
-- Then: PHP loops to find matching key after normalizing whitespace/case

-- Find or auto-create standard role
INSERT INTO roles (id, key, name, description, created_at, updated_at)
VALUES ('<UUID>', 'chair', 'Chair', 'Chair Role', NOW(), NOW());
```

---

## 13. Permission Model Helper Queries
**File:** [Permission.php](file:///c:/unity-app%2027-5-2026/unity-app%20-%20Copy/app/Models/Permission.php)

```sql
-- Get permission ID by key (cached)
SELECT id, key FROM permissions;
-- Returns map like: {'view': 'uuid-1', 'create': 'uuid-2', ...}
```

---

> [!TIP]
> **Total queries in this reference: 80+** covering every CRUD operation, permission check, hierarchy traversal, scope resolution, and cache invalidation in the entire RBAC system.
