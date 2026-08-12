-- ============================================================
-- DYNAMIC RBAC SYSTEM — USEFUL QUERIES FOR DEVELOPERS
-- Unity App - PeersUnity
-- Reference file — run any query individually as needed
-- ============================================================


-- ════════════════════════════════════════════════════════════
-- SECTION A: ROLES & HIERARCHY
-- ════════════════════════════════════════════════════════════

-- A1: Get all roles
SELECT * FROM roles ORDER BY hierarchy_depth, name;

-- A2: Get all active roles
SELECT * FROM roles WHERE status = 'active' ORDER BY hierarchy_depth, name;

-- A3: Find a role by key
SELECT * FROM roles WHERE "key" = 'chair';

-- A4: Get all assignable roles
SELECT * FROM roles WHERE status = 'active' AND is_assignable = TRUE;

-- A5: Full hierarchy tree (parent → child)
SELECT
    p.name  AS parent_role,
    p.key   AS parent_key,
    c.name  AS child_role,
    c.key   AS child_key,
    c.hierarchy_depth
FROM role_hierarchies rh
JOIN roles p ON p.id = rh.parent_role_id
JOIN roles c ON c.id = rh.child_role_id
ORDER BY c.hierarchy_depth, p.name, c.name;

-- A6: Get children of a specific role
SELECT r.* FROM roles r
JOIN role_hierarchies rh ON rh.child_role_id = r.id
WHERE rh.parent_role_id = (SELECT id FROM roles WHERE "key" = 'global_admin');

-- A7: Get parents of a specific role
SELECT r.* FROM roles r
JOIN role_hierarchies rh ON rh.parent_role_id = r.id
WHERE rh.child_role_id = (SELECT id FROM roles WHERE "key" = 'chair');

-- A8: Root roles (no parents)
SELECT r.* FROM roles r
WHERE r.id NOT IN (SELECT child_role_id FROM role_hierarchies)
ORDER BY r.hierarchy_depth;

-- A9: Leaf roles (no children)
SELECT r.* FROM roles r
WHERE r.id NOT IN (SELECT parent_role_id FROM role_hierarchies)
ORDER BY r.hierarchy_depth;


-- ════════════════════════════════════════════════════════════
-- SECTION B: MODULES & PAGES
-- ════════════════════════════════════════════════════════════

-- B1: All active modules in sidebar order
SELECT * FROM admin_modules WHERE is_active = TRUE ORDER BY sort_order;

-- B2: All modules with page count
SELECT
    am.name        AS module_name,
    am.slug,
    am.icon,
    am.sort_order,
    COUNT(ap.id)   AS page_count
FROM admin_modules am
LEFT JOIN admin_pages ap ON ap.module_id = am.id AND ap.is_active = TRUE
WHERE am.is_active = TRUE
GROUP BY am.id, am.name, am.slug, am.icon, am.sort_order
ORDER BY am.sort_order;

-- B3: All pages with their module names
SELECT
    am.name        AS module,
    ap.name        AS page,
    ap.route_name,
    ap.slug,
    ap.sort_order
FROM admin_pages ap
JOIN admin_modules am ON am.id = ap.module_id
WHERE am.is_active = TRUE AND ap.is_active = TRUE
ORDER BY am.sort_order, ap.sort_order;

-- B4: Pages of a specific module
SELECT ap.* FROM admin_pages ap
JOIN admin_modules am ON am.id = ap.module_id
WHERE am.slug = 'members' AND ap.is_active = TRUE
ORDER BY ap.sort_order;

-- B5: Find page by route name
SELECT * FROM admin_pages WHERE route_name = 'admin.users.index';


-- ════════════════════════════════════════════════════════════
-- SECTION C: PERMISSIONS
-- ════════════════════════════════════════════════════════════

-- C1: All permissions
SELECT * FROM permissions ORDER BY sort_order;


-- ════════════════════════════════════════════════════════════
-- SECTION D: PERMISSION MATRIX (role_page_permissions)
-- ════════════════════════════════════════════════════════════

-- D1: All permissions for a specific role
SELECT
    r.name   AS role_name,
    am.name  AS module,
    ap.name  AS page,
    p.key    AS permission,
    p.name   AS permission_name
FROM role_page_permissions rpp
JOIN roles r          ON r.id = rpp.role_id
JOIN admin_pages ap   ON ap.id = rpp.page_id
JOIN admin_modules am ON am.id = ap.module_id
JOIN permissions p    ON p.id = rpp.permission_id
WHERE r.key = 'chair'
ORDER BY am.sort_order, ap.sort_order, p.sort_order;

-- D2: Permission matrix pivot for a role (view/create/edit/delete columns)
SELECT
    am.name AS module,
    ap.name AS page,
    MAX(CASE WHEN p.key = 'view'    THEN 1 ELSE 0 END) AS "view",
    MAX(CASE WHEN p.key = 'create'  THEN 1 ELSE 0 END) AS "create",
    MAX(CASE WHEN p.key = 'edit'    THEN 1 ELSE 0 END) AS "edit",
    MAX(CASE WHEN p.key = 'delete'  THEN 1 ELSE 0 END) AS "delete",
    MAX(CASE WHEN p.key = 'approve' THEN 1 ELSE 0 END) AS "approve",
    MAX(CASE WHEN p.key = 'reject'  THEN 1 ELSE 0 END) AS "reject",
    MAX(CASE WHEN p.key = 'export'  THEN 1 ELSE 0 END) AS "export",
    MAX(CASE WHEN p.key = 'import'  THEN 1 ELSE 0 END) AS "import",
    MAX(CASE WHEN p.key = 'print'   THEN 1 ELSE 0 END) AS "print",
    MAX(CASE WHEN p.key = 'restore' THEN 1 ELSE 0 END) AS "restore"
FROM role_page_permissions rpp
JOIN roles r          ON r.id = rpp.role_id
JOIN admin_pages ap   ON ap.id = rpp.page_id
JOIN admin_modules am ON am.id = ap.module_id
JOIN permissions p    ON p.id = rpp.permission_id
WHERE r.key = 'chair'
GROUP BY am.name, ap.name, am.sort_order, ap.sort_order
ORDER BY am.sort_order, ap.sort_order;

-- D3: Check if a role can do a specific action on a page
SELECT EXISTS(
    SELECT 1 FROM role_page_permissions rpp
    JOIN roles r        ON r.id = rpp.role_id
    JOIN admin_pages ap ON ap.id = rpp.page_id
    JOIN permissions p  ON p.id = rpp.permission_id
    WHERE r.key = 'chair'
      AND ap.route_name = 'admin.users.index'
      AND p.key = 'export'
) AS can_do;

-- D4: Count permissions per role
SELECT
    r.name             AS role_name,
    r.key              AS role_key,
    COUNT(rpp.id)      AS total_permissions
FROM roles r
LEFT JOIN role_page_permissions rpp ON rpp.role_id = r.id
GROUP BY r.id, r.name, r.key
ORDER BY total_permissions DESC;

-- D5: Pages with NO permissions assigned (orphaned)
SELECT ap.name, ap.route_name, am.name AS module
FROM admin_pages ap
JOIN admin_modules am ON am.id = ap.module_id
LEFT JOIN role_page_permissions rpp ON rpp.page_id = ap.id
WHERE rpp.id IS NULL AND ap.is_active = TRUE
ORDER BY am.sort_order, ap.sort_order;


-- ════════════════════════════════════════════════════════════
-- SECTION E: MODULE ACCESS (role_module_access)
-- ════════════════════════════════════════════════════════════

-- E1: Visible modules for a role
SELECT am.name, am.slug, am.icon
FROM role_module_access rma
JOIN admin_modules am ON am.id = rma.module_id
JOIN roles r          ON r.id = rma.role_id
WHERE r.key = 'chair' AND rma.is_visible = TRUE
ORDER BY am.sort_order;

-- E2: Module visibility matrix for ALL roles
SELECT
    r.name         AS role_name,
    am.name        AS module_name,
    rma.is_visible
FROM role_module_access rma
JOIN roles r          ON r.id = rma.role_id
JOIN admin_modules am ON am.id = rma.module_id
ORDER BY r.name, am.sort_order;

-- E3: Which roles can see a specific module?
SELECT r.name, r.key
FROM role_module_access rma
JOIN roles r          ON r.id = rma.role_id
JOIN admin_modules am ON am.id = rma.module_id
WHERE am.slug = 'events' AND rma.is_visible = TRUE;

-- E4: Roles with NO module access configured
SELECT r.name, r.key FROM roles r
LEFT JOIN role_module_access rma ON rma.role_id = r.id
WHERE rma.id IS NULL AND r.status = 'active'
ORDER BY r.name;


-- ════════════════════════════════════════════════════════════
-- SECTION F: PAGE GROUPS
-- ════════════════════════════════════════════════════════════

-- F1: All page groups with page count
SELECT
    pg.name         AS group_name,
    pg.slug,
    pg.description,
    pg.is_active,
    COUNT(pgi.id)   AS pages_in_group
FROM page_groups pg
LEFT JOIN page_group_items pgi ON pgi.page_group_id = pg.id
GROUP BY pg.id, pg.name, pg.slug, pg.description, pg.is_active
ORDER BY pg.name;

-- F2: Pages inside a specific group
SELECT ap.name AS page, ap.route_name, pgi.sort_order
FROM page_group_items pgi
JOIN admin_pages ap ON ap.id = pgi.page_id
JOIN page_groups pg ON pg.id = pgi.page_group_id
WHERE pg.slug = 'membership-management'
ORDER BY pgi.sort_order;

-- F3: Which page groups does a role have?
SELECT pg.name, pg.slug, pg.description
FROM role_page_groups rpg
JOIN page_groups pg ON pg.id = rpg.page_group_id
JOIN roles r        ON r.id = rpg.role_id
WHERE r.key = 'chair';

-- F4: All pages accessible via page groups for a role
SELECT DISTINCT ap.name AS page, ap.route_name, pg.name AS via_group
FROM role_page_groups rpg
JOIN page_groups pg       ON pg.id = rpg.page_group_id
JOIN page_group_items pgi ON pgi.page_group_id = pg.id
JOIN admin_pages ap       ON ap.id = pgi.page_id
JOIN roles r              ON r.id = rpg.role_id
WHERE r.key = 'ded'
ORDER BY pg.name, ap.name;


-- ════════════════════════════════════════════════════════════
-- SECTION G: DATA SCOPE (role_data_scope)
-- ════════════════════════════════════════════════════════════

-- G1: All data scopes with resolved names
SELECT
    r.name           AS role_name,
    rds.scope_type,
    rds.scope_id,
    rds.admin_user_id,
    CASE
        WHEN rds.scope_type = 'circle'   THEN (SELECT name FROM circles   WHERE id = rds.scope_id)
        WHEN rds.scope_type = 'district' THEN (SELECT name FROM districts WHERE id = rds.scope_id)
        WHEN rds.scope_type = 'industry' THEN (SELECT name FROM industries WHERE id = rds.scope_id)
        ELSE 'Global'
    END AS scope_name
FROM role_data_scope rds
LEFT JOIN roles r ON r.id = rds.role_id
ORDER BY r.name;

-- G2: Get circles a DED can see (via district scope)
SELECT c.name AS circle_name, c.id
FROM circles c
WHERE c.district_id = (
    SELECT rds.scope_id FROM role_data_scope rds
    JOIN roles r ON r.id = rds.role_id
    WHERE r.key = 'ded' AND rds.scope_type = 'district'
    LIMIT 1
);

-- G3: User-specific scope overrides
SELECT rds.* FROM role_data_scope rds
WHERE rds.admin_user_id IS NOT NULL
ORDER BY rds.created_at DESC;


-- ════════════════════════════════════════════════════════════
-- SECTION H: ROLE ASSIGNMENTS (admin_user_roles)
-- ════════════════════════════════════════════════════════════

-- H1: All admin users with their roles
SELECT
    au.name            AS admin_name,
    au.email,
    STRING_AGG(r.name, ', ' ORDER BY r.name) AS roles,
    STRING_AGG(r.key, ', '  ORDER BY r.name) AS role_keys
FROM admin_user_roles aur
JOIN admin_users au ON au.id = aur.user_id
JOIN roles r        ON r.id = aur.role_id
GROUP BY au.id, au.name, au.email
ORDER BY au.name;

-- H2: Roles for a specific admin user
SELECT
    r.name             AS role_name,
    r.key              AS role_key,
    r.role_type,
    r.scope_rule,
    aur.permission_type,
    aur.allowed_sections
FROM admin_user_roles aur
JOIN roles r ON r.id = aur.role_id
WHERE aur.user_id = '00000000-0000-0000-0000-000000000000'; -- Replace with actual admin user UUID

-- H3: Users with a specific role
SELECT au.name, au.email, aur.permission_type
FROM admin_user_roles aur
JOIN admin_users au ON au.id = aur.user_id
JOIN roles r        ON r.id = aur.role_id
WHERE r.key = 'chair';

-- H4: Users with no roles assigned
SELECT au.name, au.email FROM admin_users au
LEFT JOIN admin_user_roles aur ON aur.user_id = au.id
WHERE aur.id IS NULL
ORDER BY au.name;

-- H5: Users with multiple roles
SELECT
    au.name,
    au.email,
    COUNT(aur.id) AS role_count
FROM admin_user_roles aur
JOIN admin_users au ON au.id = aur.user_id
GROUP BY au.id, au.name, au.email
HAVING COUNT(aur.id) > 1
ORDER BY role_count DESC;


-- ════════════════════════════════════════════════════════════
-- SECTION I: WORKFLOW APPROVAL RULES
-- ════════════════════════════════════════════════════════════

-- I1: All workflow rules
SELECT
    war.workflow_name,
    am.name           AS module,
    r.name            AS approver_role,
    war.step_order,
    war.is_active,
    war.description
FROM workflow_approval_rules war
JOIN admin_modules am ON am.id = war.module_id
JOIN roles r          ON r.id = war.approver_role_id
ORDER BY war.workflow_name, war.step_order;

-- I2: Approvers for a specific workflow
SELECT r.name AS approver, war.step_order
FROM workflow_approval_rules war
JOIN roles r ON r.id = war.approver_role_id
WHERE war.workflow_name LIKE '%Circle Join%' AND war.is_active = TRUE
ORDER BY war.step_order;


-- ════════════════════════════════════════════════════════════
-- SECTION J: COMPLETE ACCESS REPORT (combine everything)
-- ════════════════════════════════════════════════════════════

-- J1: Full access report for a specific admin user
SELECT
    au.name            AS admin_name,
    r.name             AS role_name,
    am.name            AS module,
    rma.is_visible     AS module_visible,
    ap.name            AS page,
    STRING_AGG(p.key, ', ' ORDER BY p.sort_order) AS permissions
FROM admin_user_roles aur
JOIN admin_users au    ON au.id = aur.user_id
JOIN roles r           ON r.id = aur.role_id
LEFT JOIN role_module_access rma  ON rma.role_id = r.id
LEFT JOIN admin_modules am        ON am.id = rma.module_id
LEFT JOIN admin_pages ap          ON ap.module_id = am.id AND ap.is_active = TRUE
LEFT JOIN role_page_permissions rpp ON rpp.role_id = r.id AND rpp.page_id = ap.id
LEFT JOIN permissions p            ON p.id = rpp.permission_id
WHERE au.email = 'admin@peersunity.com'
GROUP BY au.name, r.name, am.name, rma.is_visible, ap.name, am.sort_order, ap.sort_order
ORDER BY am.sort_order, ap.sort_order;

-- J2: Roles with NO permissions configured
SELECT r.name, r.key FROM roles r
LEFT JOIN role_page_permissions rpp ON rpp.role_id = r.id
WHERE rpp.id IS NULL AND r.status = 'active'
ORDER BY r.name;

-- J3: Compare two roles side-by-side
SELECT
    ap.name AS page,
    MAX(CASE WHEN r.key = 'chair'     AND p.key = 'view' THEN '✅' ELSE '❌' END) AS chair_view,
    MAX(CASE WHEN r.key = 'chair'     AND p.key = 'edit' THEN '✅' ELSE '❌' END) AS chair_edit,
    MAX(CASE WHEN r.key = 'chair'     AND p.key = 'export' THEN '✅' ELSE '❌' END) AS chair_export,
    MAX(CASE WHEN r.key = 'secretary' AND p.key = 'view' THEN '✅' ELSE '❌' END) AS secretary_view,
    MAX(CASE WHEN r.key = 'secretary' AND p.key = 'edit' THEN '✅' ELSE '❌' END) AS secretary_edit,
    MAX(CASE WHEN r.key = 'secretary' AND p.key = 'export' THEN '✅' ELSE '❌' END) AS secretary_export
FROM admin_pages ap
CROSS JOIN roles r
CROSS JOIN permissions p
LEFT JOIN role_page_permissions rpp
    ON rpp.role_id = r.id AND rpp.page_id = ap.id AND rpp.permission_id = p.id
WHERE r.key IN ('chair', 'secretary') AND p.key IN ('view', 'edit', 'export')
GROUP BY ap.name, ap.sort_order
ORDER BY ap.sort_order;

