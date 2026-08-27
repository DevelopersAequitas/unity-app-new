# Leader App – Backend API & Access Control Contract (Development & Production)

This document provides the complete API and Access Control Contract for the **Leader App** (Flutter / Frontend Integration). It covers environment configurations, authentication flows, the granular 21-flag permission matrix, tab/screen layout mappings, all 26 REST API endpoints with request/response schemas, and testing guides for both Development and Production.

---

## 1. Environment Configurations

| Setting | Development (Local / QA) | Staging | Production |
|---|---|---|---|
| **Base URL** | `http://localhost:8000/api/v1` (or local IP) | `https://staging-api.peersunity.com/api/v1` | `https://peersunity.com/api/v1` |
| **HTTP Headers** | `Accept: application/json`<br>`Content-Type: application/json` | `Accept: application/json`<br>`Content-Type: application/json` | `Accept: application/json`<br>`Content-Type: application/json` |
| **Auth Header** | `Authorization: Bearer <AUTH_TOKEN>` | `Authorization: Bearer <AUTH_TOKEN>` | `Authorization: Bearer <AUTH_TOKEN>` |
| **Test OTP** | `123456` (universal dev bypass) | `123456` (QA enabled) | Live SMS / Email 6-Digit OTP |
| **Token Lifetime** | 86400 seconds (24 Hours) | 86400 seconds (24 Hours) | 86400 seconds (24 Hours) |

---

## 2. Dynamic RBAC Architecture & 2-Way Web Sync

The Leader App is deeply integrated with the platform's **Dynamic RBAC Web System** (`https://peersunity.com/admin/rbac/permission-matrix`).

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│             Web Admin RBAC Control (https://peersunity.com/admin/rbac/permission-matrix)│
│  [roles] ◄──► [role_module_access] ◄──► [role_page_permissions] ◄──► [role_data_scope] │
└────────────────────────────────────────┬───────────────────────────────────────────────┘
                                         │  Two-Way Sync
                                         ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                              Leader App API & Access Control                           │
│  • 12 Granular Capabilities (access_dashboard, access_teams, access_finance, ...)      │
│  • 21-Flag Frontend Permission Matrix (can_access_dashboard, can_access_finance_tab,..) │
│  • LeaderPermissionService & LeaderRoleMatrixService (Auto-Detects RBAC Updates)        │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

### Module to Capability Mapping:
- `Dashboard` Module (`dashboard`) ➔ `access_dashboard` (`can_access_dashboard`)
- `Circles` Module (`circles`) ➔ `access_teams` (`can_access_teams_tab`, `can_manage_circles`)
- `Finance & Analytics` Module (`finance-analytics`) ➔ `access_finance`, `manage_finance` (`can_access_finance_tab`, `can_modify_finance_settings`, `can_view_overall_revenue`)
- `Peers` Module (`members`) ➔ `view_peers` (`can_access_peers_tab`, `can_view_peer_profile`, `can_view_peer_contact_info`) + `manage_peers` (`can_add_edit_peer`)
- `Activities` Module (`activities`) ➔ `request_actions` (`can_send_wishes`)
- `Referral Report` / `Activities` Module ➔ `view_reports` (`can_access_reports_tab`, `can_submit_reports`)
- `Coins` Module (`coins`) ➔ `coin_payouts` (`can_issue_coins`)
- `Role Management` Module (`role-management`) ➔ `manage_roles` (`can_access_role_management`)
- `Settings` Module (`settings`) ➔ `system_configs`
- `Role Data Scope` (District / State / Global) ➔ `regional_data` (`can_view_regional_scope`)

---

## 3. Authentication & Session Architecture

### 2.1 OTP Authentication Flow

```
[Flutter Frontend] ──── 1. POST /api/v1/auth/send-otp ───► [Backend OTP Gateway]
                                                                  │
                                                        Sends 6-digit OTP
                                                                  │
[Flutter Frontend] ◄─── Returns { success: true } ────────────────┘

[Flutter Frontend] ──── 2. POST /api/v1/auth/verify-otp ──► [Backend Auth Service]
                                                                  │
                                                      • Validates OTP
                                                      • Generates Bearer Token
                                                      • Computes Dynamic Permissions
                                                      • Resolves Managed Circles
                                                                  │
[Flutter Frontend] ◄─── Returns User + 21-Flag Matrix ────────────┘
```

---

## 3. Granular Role & Permission Access Matrix

The backend automatically resolves the user's role and returns the 21 permission flags below in `data.permissions`:

| Feature / Action Permission Flag | Circle Chair (`circleChair`) | Circle Founder (`circleFounder`) | Circle Director (`circleDirector`) | Industry Director (`industryDirector`) | District Exec Director (`districtExecDirector`) | Country Director (`countryDirector`) | Super Admin (`superAdmin`) |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `can_access_dashboard` | `true` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_view_overall_revenue` | `false` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_review_pending_peers` | `true` | `true` | `true` | `false` | `true` | `false` | `false` |
| `can_access_peers_tab` | `true` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_add_edit_peer` | `false` | `true` | `true` | `false` | `true` | `true` | `true` |
| `can_send_wishes` | `true` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_view_peer_profile` | `true` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_view_peer_contact_info` | `true` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_access_teams_tab` | `false` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_manage_circles` | `false` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_assign_circle_chair` | `false` | `true` | `true` | `false` | `true` | `true` | `true` |
| `can_access_finance_tab` | `false` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_modify_finance_settings` | `false` | `false` | `false` | `false` | `true` | `true` | `true` |
| `can_issue_coins` | `false` | `false` | `false` | `false` | `false` | `true` | `true` |
| `can_access_reports_tab` | `true` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_submit_reports` | `true` | `true` | `true` | `false` | `false` | `false` | `false` |
| `can_export_peer_data` | `false` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_export_financial_data` | `false` | `false` | `false` | `false` | `true` | `true` | `true` |
| `can_export_global_data` | `false` | `false` | `false` | `false` | `false` | `false` | `true` |
| `can_access_role_management` | `false` | `false` | `false` | `false` | `false` | `false` | `true` |
| `can_view_regional_scope` | `false` | `false` | `false` | `true` | `true` | `true` | `true` |

---

## 4. Screen & Tab Breakdown with Permission Flags

### 🏠 TAB 0: Dashboard Screen (`/home?tab=0`)
- **Top App Bar**:
  - `show_circle_selector`: `true` if user manages > 1 circle or role is `industryDirector` / `districtExecDirector` / `countryDirector` / `superAdmin`.
  - `show_notification_badge`: `true`
  - `show_role_badge`: `true`
- **Overall Revenue Banner**:
  - `is_visible`: `permissions.can_view_overall_revenue`
  - Metrics: `overall_revenue`, `overall_deals_closed`
- **Hero Overview Card**:
  - `is_visible`: `true`
  - Metrics: `impact_count`, `deals_amount`, `p2p_meetings_count`
- **Key Metrics 2x2 Grid**:
  - `total_peers` (Tap navigates to Tab 1 Peers)
  - `referrals_count` (Tap navigates to `/referrals`)
  - `testimonials_count` (Tap navigates to `/testimonials`)
  - `coins_count` (Tap navigates to `/peers-by-coins`)
- **Top 5 Impacters List**:
  - `is_visible`: `true`
- **Pending Peers Alert Card**:
  - `is_visible`: `permissions.can_review_pending_peers`

---

### 👥 TAB 1: Peers Screen (`/home?tab=1`)
- **Sub-tab 1.1: Peers Directory**:
  - Search & Status Filters: `All`, `Active`, `Needs Attention`, `At Risk`, `Pending`
  - Sort Options: `Impact`, `Deals`, `Coins`, `Attendance`
  - Tap opens Peer Profile (`/peer-profile`) if `can_view_peer_profile: true`
- **Sub-tab 1.2: Celebrations**:
  - Birthdays and Anniversaries for this week
  - "Wish" button active when `can_send_wishes: true`

---

### 🏢 TAB 2: Teams & Circles Screen (`/home?tab=2`)
- **Lock Check**:
  - If `permissions.can_access_teams_tab == false`: Displays locked access screen.
- **When Unlocked (`true`)**:
  - Summary metrics: `total_circles`, `avg_health`, `total_peers`, `total_revenue`
  - Industry filter & Circle cards
  - Tap opens Circle Details (`/circle-details`)

---

### 💳 TAB 3: Finance & Accounts Screen (`/home?tab=3`)
- **Lock Check**:
  - If `permissions.can_access_finance_tab == false`: Displays locked card.
- **When Unlocked (`true`)**:
  - KPI Cards: `total_collections`, `total_dues`, `projected_annual_revenue`, `coin_issuances_total`
  - Circle transactions and dues table
  - Settings: `can_modify_finance_settings`
  - Issue Coins: `can_issue_coins`

---

### 📊 TAB 4: Reports & Analytics Screen (`/home?tab=4`)
- **Sub-tab 4.1: Submit Report**:
  - `is_visible`: `permissions.can_submit_reports`
- **Sub-tab 4.2: Report History**:
  - List of past submitted reports with review status.
- **Sub-tab 4.3: Performance Analytics**:
  - Attendance % Spline Graph (6-month trend)
- **Sub-tab 4.4: Export Center**:
  - Exports based on `can_export_peer_data`, `can_export_financial_data`, `can_export_global_data`.

---

### ⚙️ TAB 5: Role & Permission Management Screen (`/role-management`)
- **Access Check**:
  - Accessible only if `permissions.can_access_role_management == true`.
- Allows toggling the 12 granular capabilities and adding/editing/deleting custom roles.

---

## 5. REST API Endpoints Specification

### 5.1 Authentication Endpoints

#### 1. Request Login OTP
- **Route:** `POST /api/v1/auth/send-otp`
- **Auth Required:** No
- **Request Payload:**
```json
{
  "email_or_phone": "arjun@peersglobal.in"
}
```
- **Response (200 OK):**
```json
{
  "success": true,
  "message": "OTP has been sent successfully to your registered email/phone.",
  "data": {
    "is_registered": true,
    "otp_expiry_seconds": 300
  }
}
```

#### 2. Verify Login OTP
- **Route:** `POST /api/v1/auth/verify-otp`
- **Auth Required:** No
- **Request Payload:**
```json
{
  "email_or_phone": "arjun@peersglobal.in",
  "otp": "123456"
}
```
- **Response (200 OK):**
```json
{
  "success": true,
  "message": "Authentication successful",
  "data": {
    "auth_token": "1|qwe87f654s89d7f6as5d...",
    "refresh_token": "def50200873491...",
    "token_type": "Bearer",
    "expires_in": 86400,
    "user": {
      "id": "019488a0-7b2c-74a9-a931-10c0e1234567",
      "name": "Arjun Patel",
      "email": "arjun@peersglobal.in",
      "phone": "+919876543209",
      "role": "circleChair",
      "custom_role_label": null,
      "regional_scope": "Own Circle",
      "member_since": "Jan 2023",
      "avatar_url": "https://cdn.peersglobal.in/avatars/arjun.png",
      "managed_circles": [
        {
          "id": "cir_101",
          "name": "Mumbai Tech Sunrise",
          "location": "Mumbai",
          "category": "Technology"
        }
      ]
    },
    "permissions": {
      "can_access_dashboard": true,
      "can_view_overall_revenue": false,
      "can_review_pending_peers": true,
      "can_access_peers_tab": true,
      "can_add_edit_peer": false,
      "can_send_wishes": true,
      "can_view_peer_profile": true,
      "can_view_peer_contact_info": true,
      "can_access_teams_tab": false,
      "can_manage_circles": false,
      "can_assign_circle_chair": false,
      "can_access_finance_tab": false,
      "can_modify_finance_settings": false,
      "can_issue_coins": false,
      "can_access_reports_tab": true,
      "can_submit_reports": true,
      "can_export_peer_data": false,
      "can_export_financial_data": false,
      "can_export_global_data": false,
      "can_access_role_management": false,
      "can_view_regional_scope": false
    }
  }
}
```

---

### 5.2 Dashboard Endpoints

#### 3. Get Dashboard Metrics
- **Route:** `GET /api/v1/dashboard/metrics`
- **Query Params:** `circle_id` (optional, string)
- **Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "circle_id": "cir_101",
    "circle_name": "Mumbai Tech Sunrise",
    "overall_revenue": "₹1.48Cr",
    "overall_deals_closed": "₹1.20Cr",
    "impact": 142,
    "deals": "₹86.4L",
    "p2p_meetings": 38,
    "total_peers": 56,
    "total_peers_growth": 4,
    "referrals": 28,
    "testimonials": 42,
    "coins": 3840,
    "pending_peers_count": 4
  }
}
```

#### 4. Get Top 5 Impacters
- **Route:** `GET /api/v1/dashboard/top-impacters`
- **Query Params:** `circle_id` (optional, string)
- **Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "rank": 1,
      "name": "Siddharth Verma",
      "company": "Apex Dynamics Pvt Ltd",
      "location": "Mumbai",
      "lives": 48,
      "coins": 1240
    },
    {
      "rank": 2,
      "name": "Ananya Roy",
      "company": "Veritas Health Tech",
      "location": "Mumbai",
      "lives": 36,
      "coins": 980
    },
    {
      "rank": 3,
      "name": "Rohan Deshmukh",
      "company": "Elevate Logistics",
      "location": "Mumbai",
      "lives": 29,
      "coins": 750
    },
    {
      "rank": 4,
      "name": "Pooja Hegde",
      "company": "Solace Architecture",
      "location": "Mumbai",
      "lives": 18,
      "coins": 520
    },
    {
      "rank": 5,
      "name": "Karan Singhal",
      "company": "Nexus FinServ",
      "location": "Mumbai",
      "lives": 11,
      "coins": 350
    }
  ]
}
```

---

### 5.3 Peers & Celebrations Endpoints

#### 5. List Peers
- **Route:** `GET /api/v1/peers`
- **Query Params:** `circle_id`, `status` (`All`, `Active`, `Needs Attention`, `At Risk`, `Pending`), `sort` (`Impact`, `Deals`, `Coins`, `Attendance`), `search`
- **Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": "peer_001",
      "name": "Siddharth Verma",
      "company": "Apex Dynamics Pvt Ltd",
      "circle": "Mumbai Tech Sunrise",
      "location": "Mumbai",
      "tags": "FinTech · Series A · B2B SaaS",
      "status": "Active",
      "impact_count": 48,
      "deals_formatted": "₹32.5L",
      "coins": 1240,
      "attendance": "94%"
    }
  ]
}
```

#### 6. Get Peer Detailed Profile
- **Route:** `GET /api/v1/peers/:id`
- **Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": "peer_001",
    "name": "Siddharth Verma",
    "company": "Apex Dynamics Pvt Ltd",
    "designation": "Founder & CEO",
    "phone": "+919876543201",
    "email": "siddharth@apexdynamics.com",
    "circle": "Mumbai Tech Sunrise",
    "location": "Mumbai, India",
    "intro_video_url": "https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4",
    "attendance": "94%",
    "deals_closed": "₹32.5L",
    "coins_balance": 1240,
    "testimonials": [
      {
        "id": "tst_1",
        "endorser_name": "Kavitha Rao",
        "endorser_company": "Zenith AI",
        "content": "Outstanding leadership and integrity. Delivered exceptional value on our cross-circle tech partnership."
      }
    ],
    "referrals": [
      {
        "id": "ref_1",
        "client_name": "Tata Digital",
        "value": "₹12.0L",
        "status": "Closed"
      }
    ]
  }
}
```

#### 7. Get Celebrations
- **Route:** `GET /api/v1/peers/celebrations`
- **Query Params:** `circle_id` (optional, string)
- **Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "birthdays": [
      {
        "id": "cel_01",
        "peer_id": "peer_001",
        "name": "Siddharth Verma",
        "company": "Apex Dynamics",
        "date_formatted": "Today, 25 Aug",
        "is_today": true
      }
    ],
    "anniversaries": [
      {
        "id": "cel_02",
        "peer_id": "peer_003",
        "name": "Rohan Deshmukh",
        "company": "Elevate Logistics",
        "milestone": "3 Years in Circle",
        "date_formatted": "28 Aug",
        "is_today": false
      }
    ]
  }
}
```

#### 8. Send Celebration Wish
- **Route:** `POST /api/v1/peers/:id/send-wish`
- **Request Payload:**
```json
{
  "type": "birthday",
  "message": "Wishing you a very happy birthday! Have a wonderful year ahead."
}
```
- **Response (200 OK):**
```json
{
  "success": true,
  "message": "Wish sent to Siddharth Verma successfully!"
}
```

---

### 5.4 Teams & Circles Endpoints

#### 9. Teams Overview Summary
- **Route:** `GET /api/v1/teams/summary`
- **Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "total_circles": 12,
    "avg_health": 88,
    "total_peers": 420,
    "total_revenue": "₹4.8Cr"
  }
}
```

#### 10. List Circles Directory
- **Route:** `GET /api/v1/teams/circles`
- **Query Params:** `industry`, `status`, `search`
- **Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": "cir_101",
      "name": "Mumbai Tech Sunrise",
      "category": "Technology",
      "location": "Mumbai",
      "health_percentage": 94,
      "peers_count": 56,
      "revenue": "₹1.48Cr",
      "chair_name": "Arjun Patel",
      "founders_count": 2,
      "status": "Active"
    }
  ]
}
```

#### 11. Get Circle Details
- **Route:** `GET /api/v1/teams/circles/:id`
- **Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": "cir_101",
    "name": "Mumbai Tech Sunrise",
    "category": "Technology",
    "location": "Mumbai",
    "launch_date": "Jan 2022",
    "health_percentage": 94,
    "chair": {
      "id": "usr_987214",
      "name": "Arjun Patel",
      "email": "arjun@peersglobal.in",
      "phone": "+919876543209"
    },
    "founders": [
      {
        "id": "usr_110",
        "name": "Sanjana Mehta",
        "email": "sanjana@peersglobal.in"
      }
    ],
    "metrics": {
      "total_peers": 56,
      "attendance_rate": "92%",
      "monthly_revenue": "₹12.4L",
      "annual_revenue": "₹1.48Cr"
    },
    "members": [
      {
        "id": "peer_001",
        "name": "Siddharth Verma",
        "company": "Apex Dynamics Pvt Ltd",
        "status": "Active"
      }
    ]
  }
}
```

---

### 5.5 Finance Endpoints

#### 12. Get Finance Metrics
- **Route:** `GET /api/v1/finance/metrics`
- **Query Params:** `circle_id` (optional, string)
- **Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "total_collections": "₹84.5L",
    "total_dues": "₹12.2L",
    "projected_annual_revenue": "₹1.20Cr",
    "coin_issuances_total": 14500
  }
}
```

#### 13. Get Transactions & Dues Table
- **Route:** `GET /api/v1/finance/transactions`
- **Query Params:** `circle_id`, `status` (`Paid`, `Pending`, `Overdue`)
- **Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": "txn_8921",
      "peer_name": "Siddharth Verma",
      "circle_name": "Mumbai Tech Sunrise",
      "amount": "₹45,000",
      "type": "Annual Membership Fee",
      "status": "Paid",
      "date": "2026-08-15"
    }
  ]
}
```

---

### 5.6 Reports & Analytics Endpoints

#### 14. List Reports History
- **Route:** `GET /api/v1/reports`
- **Query Params:** `circle_id`, `type` (`weekly`, `monthly`)
- **Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": "rep_101",
      "circle_name": "Mumbai Tech Sunrise",
      "report_type": "Monthly",
      "period": "July 2026",
      "submitted_by": "Arjun Patel",
      "submitted_at": "2026-08-01T10:00:00Z",
      "status": "Approved",
      "attendance_percentage": 92,
      "deals_closed_value": "₹14.2L",
      "summary_text": "Strong monthly participation with 4 new peer referrals closed."
    }
  ]
}
```

#### 15. Submit Report
- **Route:** `POST /api/v1/reports`
- **Request Payload:**
```json
{
  "circle_id": "cir_101",
  "report_type": "Monthly",
  "period": "August 2026",
  "attendance_percentage": 94,
  "deals_closed_value": "₹18.5L",
  "content": "August monthly meeting conducted successfully with 52 peers present.",
  "action_items": "Follow up on pending renewals for Q3."
}
```
- **Response (201 Created):**
```json
{
  "success": true,
  "message": "Report submitted successfully!",
  "data": {
    "report_id": "rep_102"
  }
}
```

#### 16. Get 6-Month Attendance Trend
- **Route:** `GET /api/v1/reports/attendance-trend`
- **Query Params:** `circle_id` (optional, string)
- **Response (200 OK):**
```json
{
  "success": true,
  "data": [
    { "month": "Feb", "value": 72.0 },
    { "month": "Mar", "value": 78.0 },
    { "month": "Apr", "value": 74.0 },
    { "month": "May", "value": 82.0 },
    { "month": "Jun", "value": 87.0 },
    { "month": "Jul", "value": 90.0 }
  ]
}
```

---

### 5.7 Referrals, Testimonials & Coins Endpoints

#### 17. List Referrals
- **Route:** `GET /api/v1/referrals`
- **Query Params:** `circle_id`, `status`
- **Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": "ref_501",
      "rank": 1,
      "peer_name": "Siddharth Verma",
      "company": "Apex Dynamics Pvt Ltd",
      "referrals_count": 14,
      "value_formatted": "₹18.4L",
      "status": "Active",
      "source": "Direct"
    }
  ]
}
```

#### 18. List Testimonials
- **Route:** `GET /api/v1/testimonials`
- **Query Params:** `circle_id`
- **Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": "tst_901",
      "author_name": "Kavitha Rao",
      "author_role": "Industry Director",
      "target_peer_name": "Siddharth Verma",
      "circle_name": "Mumbai Tech Sunrise",
      "content": "Siddharth's team delivered a state-of-the-art solution that increased efficiency by 40%.",
      "date": "2026-08-10"
    }
  ]
}
```

#### 19. Peers Leaderboard By Coins
- **Route:** `GET /api/v1/peers-by-coins`
- **Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "total_platform_coins": 3840,
    "leaderboard": [
      {
        "rank": 1,
        "peer_name": "Siddharth Verma",
        "circle_name": "Mumbai Tech Sunrise",
        "coins": 1240
      }
    ]
  }
}
```

---

### 5.8 Notifications Endpoints

#### 20. List Notifications
- **Route:** `GET /api/v1/notifications`
- **Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": "b3b9b47e-8c43-4e89-8d4e-03cb63065a7d",
      "title": "New Referral Received",
      "message": "You received a new lead from Ananya Roy.",
      "category": "referral",
      "is_unread": true,
      "created_at": "2026-08-25T08:30:00Z"
    }
  ]
}
```

#### 21. Mark Notifications As Read
- **Route:** `POST /api/v1/notifications/mark-read`
- **Method:** `POST`
- **Auth:** `Bearer <TOKEN>`
- **Description:** Mark notifications as read.
- **Request Body:**
```json
{
  "notification_ids": ["b3b9b47e-8c43-4e89-8d4e-03cb63065a7d"]
}
```
*(Pass `["all"]` to mark all notifications as read)*
- **Response (200 OK):**
```json
{
  "success": true,
  "message": "Notifications marked as read successfully."
}
```

---

### 5.9 Role & Permission Management (Matrix) Endpoints

#### 22. Get Capability Definitions & Role Matrix
- **Route:** `GET /api/v1/roles/matrix`
- **Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "capabilities": [
      {
        "id": "access_dashboard",
        "name": "Access Dashboard",
        "category": "Navigation & Access",
        "description": "Allows access to the primary metrics and impacter list dashboard."
      },
      {
        "id": "access_teams",
        "name": "Access Circles & Teams",
        "category": "Navigation & Access",
        "description": "Allows viewing circles, directors, and chairs directories."
      },
      {
        "id": "access_finance",
        "name": "Access Financial Analytics",
        "category": "Navigation & Access",
        "description": "Allows viewing fee collections, dues, and transaction histories."
      },
      {
        "id": "regional_data",
        "name": "View Regional Scope Data",
        "category": "Navigation & Access",
        "description": "Access and filter data beyond own local circle (District/Country level)."
      },
      {
        "id": "view_peers",
        "name": "View Peer Profiles",
        "category": "Core Operations",
        "description": "Allows viewing and browsing peer profile details and attendance stats."
      },
      {
        "id": "manage_peers",
        "name": "Add/Edit Peer Information",
        "category": "Core Operations",
        "description": "Allows coordinators to add new peers or edit biographical fields."
      },
      {
        "id": "request_actions",
        "name": "Endorse Testimonials & Referrals",
        "category": "Core Operations",
        "description": "Allows creating and endorsing peer testimonials and registering new referrals."
      },
      {
        "id": "view_reports",
        "name": "View Performance Reports",
        "category": "Core Operations",
        "description": "Allows accessing downloadable PDFs and spreadsheets of peer activities."
      },
      {
        "id": "manage_finance",
        "name": "Modify Financial Settings",
        "category": "Financial Control",
        "description": "Allows modifying annual fees, approval of dues, and updating ledger settings."
      },
      {
        "id": "coin_payouts",
        "name": "Issue Coin Payouts",
        "category": "Financial Control",
        "description": "Allows awarding platform coins directly to peers for special achievements."
      },
      {
        "id": "manage_roles",
        "name": "Manage App Roles (Matrix)",
        "category": "Administration",
        "description": "Allows altering permission rules and toggling capabilities per role."
      },
      {
        "id": "system_configs",
        "name": "System Global Settings",
        "category": "Administration",
        "description": "Allows modifying global server variables, maintenance modes, and metadata keys."
      }
    ],
    "roles": [
      {
        "id": "circleChair",
        "label": "Circle Chair",
        "is_system_role": true,
        "enabled_capabilities": [
          "access_dashboard",
          "view_peers",
          "request_actions",
          "view_reports"
        ]
      },
      {
        "id": "superAdmin",
        "label": "Super Admin",
        "is_system_role": true,
        "enabled_capabilities": [
          "access_dashboard",
          "access_teams",
          "access_finance",
          "regional_data",
          "view_peers",
          "manage_peers",
          "request_actions",
          "view_reports",
          "manage_finance",
          "coin_payouts",
          "manage_roles",
          "system_configs"
        ]
      }
    ]
  }
}
```

#### 23. Update Role Capability Assignments
- **Route:** `PUT /api/v1/roles/matrix`
- **Request Payload:**
```json
{
  "role_id": "circleChair",
  "enabled_capabilities": [
    "access_dashboard",
    "view_peers",
    "request_actions",
    "view_reports"
  ]
}
```
- **Response (200 OK):**
```json
{
  "success": true,
  "message": "Role capabilities updated successfully."
}
```

#### 24. Create Custom Role
- **Route:** `POST /api/v1/roles`
- **Request Payload:**
```json
{
  "label": "Regional Coordinator",
  "enabled_capabilities": [
    "access_dashboard",
    "view_peers",
    "regional_data"
  ]
}
```
- **Response (201 Created):**
```json
{
  "success": true,
  "message": "Custom role created successfully.",
  "data": {
    "id": "019488b0-a3df-7b56-8a9d-b4f0e9876543",
    "role_key": "regional_coordinator",
    "label": "Regional Coordinator",
    "is_system_role": false,
    "enabled_capabilities": [
      "access_dashboard",
      "view_peers",
      "regional_data"
    ]
  }
}
```

#### 25. Update Custom Role
- **Route:** `PUT /api/v1/roles/:id`
- **Request Payload:**
```json
{
  "label": "Senior Regional Coordinator",
  "enabled_capabilities": [
    "access_dashboard",
    "view_peers",
    "regional_data",
    "access_teams"
  ]
}
```

#### 26. Delete Custom Role
- **Route:** `DELETE /api/v1/roles/:id`
- **Response (200 OK):**
```json
{
  "success": true,
  "message": "Custom role deleted successfully."
}
```

---

## 6. Standard Error Handling Envelope

All error states (HTTP 400, 401, 403, 404, 422, 500) follow this consistent envelope:

```json
{
  "success": false,
  "error_code": "UNAUTHORIZED_ACCESS",
  "message": "You do not have permission to access the Finance tab.",
  "details": null
}
```

### Common Error Codes:
- `UNAUTHENTICATED` (401): Missing or expired Bearer token.
- `UNAUTHORIZED_ACCESS` (403): User role lacks capability for this tab/feature.
- `INVALID_CREDENTIALS` (422): Incorrect email/phone or expired OTP.
- `VALIDATION_ERROR` (422): Missing required fields in request payload.
- `RESOURCE_NOT_FOUND` (404): Circle, Peer, or Role ID does not exist.

---

## 7. Role-Wise Frontend Testing Flow

To test the Flutter Leader App for each role in Development/Staging:

1. **Circle Chair Testing**:
   - Send OTP with `arjun@peersglobal.in` (or any chair user).
   - Verify OTP with `123456`.
   - Result: Tab 0 (Dashboard), Tab 1 (Peers), and Tab 4 (Reports - Submit Report enabled) are accessible. Tab 2 (Teams), Tab 3 (Finance), and Tab 5 (Role Management) are locked.
2. **Circle Founder / Circle Director Testing**:
   - Send OTP with a Circle Founder or Director email.
   - Result: Tab 0, 1, 2, 3, 4 unlocked. `can_view_overall_revenue: true`. Tab 5 locked.
3. **District Exec Director Testing**:
   - Send OTP with DED user email.
   - Result: `regional_scope: "District Scope"`, `can_modify_finance_settings: true`, `can_export_financial_data: true`.
4. **Super Admin Testing**:
   - Send OTP with Super Admin email.
   - Result: All 6 tabs unlocked including Tab 5 Role Management.
