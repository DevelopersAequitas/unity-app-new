# Dynamic RBAC System — API & Mobile Integration Documentation

This document provides detailed API specifications for the **Dynamic RBAC System**. All endpoints are designed for integration with mobile apps and web frontend clients.

---

## 📌 Base URL & Headers

- **Base URL:** `http://127.0.0.1:8000/api/v1`
- **Content-Type:** `application/json`
- **Accept:** `application/json`
- **Authorization:** `Bearer {SANCTUM_API_TOKEN}` *(Required for authenticated endpoints)*

---

## 📑 Overview of Endpoints

| Category | Endpoint | Method | Description |
|---|---|---|---|
| **User Scope** | `/api/v1/rbac/my-permissions` | `GET` | Get current user's roles, visible modules & scope |
| **Role Hierarchy** | `/api/v1/rbac/hierarchy` | `GET` | Fetch entire role hierarchy tree |
| | `/api/v1/rbac/roles` | `POST` | Create a new role |
| | `/api/v1/rbac/roles/update-parent` | `POST` | Relocate node in tree |
| | `/api/v1/rbac/roles/{id}/assignments` | `GET` | Get peers assigned to role |
| | `/api/v1/rbac/roles/{id}/assignments` | `POST` | Assign peer to role with scope |
| | `/api/v1/rbac/roles/{id}/assignments/{userId}` | `DELETE` | Revoke role assignment |
| **Admin Modules** | `/api/v1/rbac/modules` | `GET` | List all admin modules |
| | `/api/v1/rbac/modules` | `POST` | Create an admin module |
| | `/api/v1/rbac/modules/{id}` | `PUT` | Update an admin module |
| | `/api/v1/rbac/modules/{id}` | `DELETE` | Delete an admin module |
| | `/api/v1/rbac/modules/order` | `POST` | Update module sort order |
| **Admin Pages** | `/api/v1/rbac/pages` | `GET` | List all sub-pages |
| | `/api/v1/rbac/pages` | `POST` | Create a new admin page |
| | `/api/v1/rbac/pages/{id}` | `PUT` | Update an admin page |
| | `/api/v1/rbac/pages/{id}` | `DELETE` | Delete an admin page |
| **Permission Matrix** | `/api/v1/rbac/permission-matrix` | `GET` | Get role permissions matrix |
| | `/api/v1/rbac/permission-matrix` | `POST` | Update role permissions matrix |
| **Module Access** | `/api/v1/rbac/module-access` | `GET` | Get module visibility per role |
| | `/api/v1/rbac/module-access` | `POST` | Update module visibility per role |
| **Page Groups** | `/api/v1/rbac/page-groups` | `GET` | List page groups |
| | `/api/v1/rbac/page-groups` | `POST` | Create a page group |
| | `/api/v1/rbac/page-groups/{id}` | `PUT` | Update a page group |
| | `/api/v1/rbac/page-groups/{id}` | `DELETE` | Delete a page group |
| **Data Scope** | `/api/v1/rbac/data-scope` | `GET` | List active data scope assignments |
| | `/api/v1/rbac/data-scope` | `POST` | Create data scope rule |
| | `/api/v1/rbac/data-scope/{id}` | `DELETE` | Delete data scope rule |
| **Workflow Rules** | `/api/v1/rbac/workflow-rules` | `GET` | List workflow approval rules |
| | `/api/v1/rbac/workflow-rules` | `POST` | Create workflow approval rule |
| | `/api/v1/rbac/workflow-rules/{id}` | `PUT` | Update workflow approval rule |
| | `/api/v1/rbac/workflow-rules/{id}` | `DELETE` | Delete workflow approval rule |

---

## 1. User Permissions API (Mobile App Main Menu)

Returns the authenticated user's assigned roles, visible modules, accessible pages, and data scope.

### `GET /api/v1/rbac/my-permissions`

#### Headers
```http
Authorization: Bearer {TOKEN}
Accept: application/json
```

#### Response `200 OK`
```json
{
  "success": true,
  "is_admin": true,
  "admin_user": {
    "id": "98fee7aa-0d25-4dd7-b445-8b79ba689f44",
    "name": "Global Admin",
    "email": "admin@peersunity.com"
  },
  "roles": [
    {
      "id": "2ee9a07b-c64d-4fc2-8b5f-3127d9785f6a",
      "key": "global_admin",
      "name": "Global Admin",
      "role_type": "admin",
      "scope_rule": "not_applicable"
    }
  ],
  "modules": [
    {
      "id": "9cb443a0-1234-4567-89ab-cdef01234567",
      "name": "Dashboard",
      "slug": "dashboard",
      "icon": "bi-speedometer2",
      "sort_order": 1,
      "is_active": true,
      "is_allowed": true,
      "pages": [
        {
          "id": "0145abe9-2efc-4882-9dce-e29620c445b2",
          "module_id": "22e2747a-cb26-4b2c-b226-49bf6196ebf9",
          "name": "DED Dashboard",
          "route_name": "admin.ded.dashboard",
          "slug": "ded-dashboard",
          "icon": null,
          "sort_order": 3,
          "is_active": true,
          "is_allowed": true,
          "permissions": {
            "view": true,
            "create": false,
            "edit": false,
            "delete": false,
            "approve": false,
            "reject": false,
            "export": true,
            "import": false,
            "print": false,
            "restore": false
          }
        }
      ]
    }
  ],
  "allowed_modules": [
    {
      "id": "22e2747a-cb26-4b2c-b226-49bf6196ebf9",
      "name": "Dashboard",
      "slug": "dashboard",
      "icon": "bi-speedometer2"
    }
  ],
  "data_scope": {
    "scope_type": "district",
    "is_global": false,
    "scope_ids": ["d9cf253e-8b72-478a-a6be-8ccaeb362bbd"],
    "circle_ids": ["d9cf253e-8b72-478a-a6be-8ccaeb362bbd"],
    "district_id": "8f3b2a1c-...",
    "state_id": "1a2b3c4d-...",
    "industry_ids": []
  }
}
```

---

## 2. Role Hierarchy API

### `GET /api/v1/rbac/hierarchy`

Fetch the entire role hierarchy tree, root roles, relationships, and scope entities.

#### Response `200 OK`
```json
{
  "success": true,
  "roles": [
    {
      "id": "2ee9a07b-c64d-4fc2-8b5f-3127d9785f6a",
      "key": "global_admin",
      "name": "Global Admin",
      "role_type": "admin",
      "scope_rule": "not_applicable",
      "hierarchy_depth": 0
    },
    {
      "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
      "key": "ded",
      "name": "DED",
      "role_type": "admin",
      "scope_rule": "mandatory",
      "hierarchy_depth": 1
    }
  ],
  "roots": [
    {
      "id": "2ee9a07b-c64d-4fc2-8b5f-3127d9785f6a",
      "key": "global_admin",
      "name": "Global Admin"
    }
  ],
  "parentToChildren": {
    "2ee9a07b-c64d-4fc2-8b5f-3127d9785f6a": [
      "a1b2c3d4-e5f6-7890-abcd-ef1234567890"
    ]
  },
  "childToParents": {
    "a1b2c3d4-e5f6-7890-abcd-ef1234567890": [
      "2ee9a07b-c64d-4fc2-8b5f-3127d9785f6a"
    ]
  }
}
```

### `POST /api/v1/rbac/roles`

Create a new role node.

#### Request Body
```json
{
  "name": "Regional Manager",
  "key": "regional_manager",
  "role_type": "admin",
  "scope_rule": "optional",
  "description": "Manages regional circles",
  "parent_role_ids": [
    "2ee9a07b-c64d-4fc2-8b5f-3127d9785f6a"
  ]
}
```

#### Response `201 Created`
```json
{
  "success": true,
  "message": "Role created successfully."
}
```

### `POST /api/v1/rbac/roles/update-parent`

Relocate a role in the hierarchy tree.

#### Request Body
```json
{
  "role_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "parent_role_ids": [
    "2ee9a07b-c64d-4fc2-8b5f-3127d9785f6a"
  ]
}
```

#### Response `200 OK`
```json
{
  "success": true,
  "message": "Role hierarchy updated successfully."
}
```

### `GET /api/v1/rbac/roles/{id}/assignments`

Fetch assigned peers for a role.

#### Response `200 OK`
```json
{
  "success": true,
  "role": {
    "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "name": "DED",
    "key": "ded",
    "scope_rule": "mandatory"
  },
  "assignments": [
    {
      "user_id": "98fee7aa-0d25-4dd7-b445-8b79ba689f44",
      "name": "Rahul Parmar",
      "email": "rahul@peersunity.com",
      "scope_id": "district-uuid-123",
      "scope_name": "District: Ahmedabad",
      "allowed_sections": ["Dashboard", "Activities"],
      "permission_type": "edit"
    }
  ],
  "available_peers": [
    {
      "id": "peer-uuid-456",
      "name": "Vinit Chavda",
      "email": "vinit@peersunity.com"
    }
  ]
}
```

### `POST /api/v1/rbac/roles/{id}/assignments`

Assign a peer to a role with scope and allowed sections.

#### Request Body
```json
{
  "admin_user_id": "peer-uuid-456",
  "scope_id": "district-uuid-123",
  "permission_type": "edit",
  "allowed_sections": ["Dashboard", "Activities", "Referral Report"]
}
```

#### Response `200 OK`
```json
{
  "success": true,
  "message": "Role assigned successfully."
}
```

---

## 3. Admin Modules API

### `GET /api/v1/rbac/modules`

#### Response `200 OK`
```json
{
  "success": true,
  "modules": [
    {
      "id": "m1-uuid",
      "name": "Dashboard",
      "slug": "dashboard",
      "icon": "bi-speedometer2",
      "sort_order": 1,
      "is_active": true,
      "pages_count": 4
    },
    {
      "id": "m2-uuid",
      "name": "Members",
      "slug": "members",
      "icon": "bi-people",
      "sort_order": 2,
      "is_active": true,
      "pages_count": 10
    }
  ]
}
```

### `POST /api/v1/rbac/modules`

#### Request Body
```json
{
  "name": "Finance & Analytics",
  "slug": "finance-analytics",
  "icon": "bi-cash-stack",
  "sort_order": 17,
  "is_active": true
}
```

#### Response `201 Created`
```json
{
  "success": true,
  "message": "Module created successfully.",
  "module": {
    "id": "new-module-uuid",
    "name": "Finance & Analytics",
    "slug": "finance-analytics",
    "icon": "bi-cash-stack",
    "sort_order": 17,
    "is_active": true
  }
}
```

### `PUT /api/v1/rbac/modules/{id}`

#### Request Body
```json
{
  "name": "Financial Management",
  "slug": "financial-management",
  "icon": "bi-cash-stack",
  "sort_order": 17,
  "is_active": true
}
```

#### Response `200 OK`
```json
{
  "success": true,
  "message": "Module updated successfully."
}
```

### `DELETE /api/v1/rbac/modules/{id}`

#### Response `200 OK`
```json
{
  "success": true,
  "message": "Module deleted successfully."
}
```

---

## 4. Admin Pages API

### `GET /api/v1/rbac/pages`

Query parameter: `?module={module_id}` *(optional)*

#### Response `200 OK`
```json
{
  "success": true,
  "pages": {
    "current_page": 1,
    "data": [
      {
        "id": "page-uuid-1",
        "module_id": "module-uuid-1",
        "name": "All Members",
        "route_name": "admin.users.index",
        "slug": "all-members",
        "icon": "bi-people",
        "sort_order": 1,
        "is_active": true,
        "module": {
          "id": "module-uuid-1",
          "name": "Members"
        }
      }
    ]
  }
}
```

### `POST /api/v1/rbac/pages`

#### Request Body
```json
{
  "module_id": "module-uuid-1",
  "name": "Member Audit Logs",
  "route_name": "admin.users.audit-logs",
  "slug": "member-audit-logs",
  "icon": "bi-shield-check",
  "sort_order": 11,
  "is_active": true
}
```

#### Response `201 Created`
```json
{
  "success": true,
  "message": "Page created successfully."
}
```

---

## 5. Permission Matrix API

### `GET /api/v1/rbac/permission-matrix?role_id={ROLE_ID}`

Fetch the matrix of active modules, pages, available permissions, and assigned permissions for a given role.

#### Response `200 OK`
```json
{
  "success": true,
  "selectedRole": {
    "id": "role-uuid-1",
    "name": "Chair",
    "key": "chair"
  },
  "permissions": [
    { "id": "p-1", "key": "view", "name": "View" },
    { "id": "p-2", "key": "create", "name": "Create" },
    { "id": "p-3", "key": "edit", "name": "Edit" },
    { "id": "p-4", "key": "delete", "name": "Delete" },
    { "id": "p-5", "key": "approve", "name": "Approve" }
  ],
  "modules": [
    {
      "id": "m-1",
      "name": "Members",
      "pages": [
        { "id": "page-1", "name": "All Members", "route_name": "admin.users.index" }
      ]
    }
  ],
  "currentPermissions": {
    "page-1": {
      "p-1": true,
      "p-5": true
    }
  }
}
```

### `POST /api/v1/rbac/permission-matrix`

Save/update the matrix of permissions for a role.

#### Request Body
```json
{
  "role_id": "role-uuid-1",
  "permissions": {
    "page-1": {
      "p-1": 1,
      "p-2": 1,
      "p-3": 0
    }
  }
}
```

#### Response `200 OK`
```json
{
  "success": true,
  "message": "Permission matrix updated successfully.",
  "role_id": "role-uuid-1"
}
```

---

## 6. Module Access API

### `GET /api/v1/rbac/module-access?role_id={ROLE_ID}`

Fetch module visibility toggles for a role.

#### Response `200 OK`
```json
{
  "success": true,
  "selectedRole": {
    "id": "role-uuid-1",
    "name": "Chair",
    "key": "chair"
  },
  "modules": [
    { "id": "m-1", "name": "Dashboard", "slug": "dashboard" },
    { "id": "m-2", "name": "Members", "slug": "members" }
  ],
  "currentAccess": {
    "m-1": true,
    "m-2": true
  }
}
```

### `POST /api/v1/rbac/module-access`

#### Request Body
```json
{
  "role_id": "role-uuid-1",
  "modules": {
    "m-1": 1,
    "m-2": 1,
    "m-3": 0
  }
}
```

#### Response `200 OK`
```json
{
  "success": true,
  "message": "Module access updated successfully.",
  "role_id": "role-uuid-1"
}
```

---

## 7. Page Groups API

### `GET /api/v1/rbac/page-groups`

#### Response `200 OK`
```json
{
  "success": true,
  "groups": [
    {
      "id": "group-uuid-1",
      "name": "Membership Management",
      "slug": "membership-management",
      "description": "All pages related to member management",
      "is_active": true,
      "pages_count": 7,
      "pages": [
        { "id": "page-1", "name": "All Members", "route_name": "admin.users.index" }
      ]
    }
  ]
}
```

### `POST /api/v1/rbac/page-groups`

#### Request Body
```json
{
  "name": "Event Supervision",
  "slug": "event-supervision",
  "description": "Event management and attendance pages",
  "is_active": true,
  "page_ids": [
    "page-uuid-1",
    "page-uuid-2"
  ]
}
```

#### Response `201 Created`
```json
{
  "success": true,
  "message": "Page group created successfully."
}
```

---

## 8. Data Scope API

### `GET /api/v1/rbac/data-scope`

#### Response `200 OK`
```json
{
  "success": true,
  "scopes": {
    "current_page": 1,
    "data": [
      {
        "id": "scope-uuid-1",
        "role_id": "role-uuid-1",
        "admin_user_id": null,
        "scope_type": "district",
        "scope_id": "district-uuid-1",
        "role": { "name": "DED" }
      }
    ]
  },
  "circles": [{ "id": "c-1", "name": "Titanium Circle" }],
  "industries": [{ "id": "i-1", "name": "IT & Software" }],
  "districts": [{ "id": "d-1", "name": "Ahmedabad" }]
}
```

### `POST /api/v1/rbac/data-scope`

#### Request Body
```json
{
  "role_id": "role-uuid-1",
  "scope_type": "district",
  "scope_id": "district-uuid-1"
}
```

#### Response `201 Created`
```json
{
  "success": true,
  "message": "Data scope created successfully."
}
```

---

## 9. Workflow Approval Rules API

### `GET /api/v1/rbac/workflow-rules`

#### Response `200 OK`
```json
{
  "success": true,
  "rules": {
    "current_page": 1,
    "data": [
      {
        "id": "rule-uuid-1",
        "module_id": "module-uuid-1",
        "workflow_name": "Circle Join Request Approval",
        "step_order": 1,
        "is_active": true,
        "module": { "name": "Circles" },
        "approver_role": { "name": "Circle Director" }
      }
    ]
  }
}
```

### `POST /api/v1/rbac/workflow-rules`

#### Request Body
```json
{
  "module_id": "module-uuid-1",
  "workflow_name": "Member Registration Approval",
  "approver_role_id": "role-uuid-ded",
  "step_order": 1,
  "is_active": true,
  "description": "DED must approve registration first"
}
```

#### Response `201 Created`
```json
{
  "success": true,
  "message": "Workflow rule created successfully."
}
```

---

## 💡 Notes for Mobile App Integration

1. **Authentication Token:** Pass Sanctum Bearer token in the `Authorization` header for all requests:
   ```
   Authorization: Bearer 1|abcdef123456...
   ```
2. **Accept Header:** Always pass `Accept: application/json` so Laravel returns JSON errors (validation 422, unauthenticated 401, not found 404) instead of HTML redirects.
3. **User Menu Generation:** Call `GET /api/v1/rbac/my-permissions` upon app login to build the dynamic sidebar / bottom navigation tabs for the user.
